<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\PhoneNormalizer;
use App\Services\ContactFileParser;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        $search = $request->input('search');

        // Saved contacts
        $contacts = Contact::where('user_id', $userId)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->get()
            ->keyBy('phone');

        // All numbers the user has sent messages to
        $messagePhones = WhatsAppMessage::where('user_id', $userId)
            ->where('direction', 'outgoing')
            ->whereNotNull('to')
            ->select('to as phone', DB::raw('MAX(created_at) as last_contacted_at'))
            ->groupBy('to');

        if ($search) {
            $messagePhones->where('to', 'like', "%{$search}%");
        }

        $messagePhones = $messagePhones->get()
            ->mapWithKeys(function ($item) {
                $phone = $this->normalizePhone($item->phone);
                return [$phone => ['last_contacted_at' => $item->last_contacted_at, 'source' => 'message']];
            });

        // Names from campaign recipients
        $campaignNames = WhatsAppCampaign::where('user_id', $userId)
            ->with(['recipients' => function ($q) use ($search) {
                $q->when($search, function ($q2) use ($search) {
                    $q2->where('phone', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            }])
            ->get()
            ->pluck('recipients')
            ->flatten()
            ->whereNotNull('phone')
            ->mapWithKeys(function ($r) {
                $phone = $this->normalizePhone($r->phone);
                return [$phone => ['name' => $r->name, 'source' => 'campaign']];
            });

        // Merge everything by phone
        $merged = [];

        foreach ($contacts as $phone => $contact) {
            $merged[$phone] = [
                'id' => $contact->id,
                'phone' => $phone,
                'name' => $contact->name,
                'source' => $contact->source ?? 'manual',
                'last_contacted_at' => null,
                'is_saved' => true,
            ];
        }

        foreach ($campaignNames as $phone => $data) {
            if (isset($merged[$phone])) {
                if (empty($merged[$phone]['name']) && !empty($data['name'])) {
                    $merged[$phone]['name'] = $data['name'];
                }
                if ($merged[$phone]['source'] === 'manual') {
                    $merged[$phone]['source'] = 'campaign';
                }
            } else {
                $merged[$phone] = [
                    'id' => null,
                    'phone' => $phone,
                    'name' => $data['name'] ?? null,
                    'source' => 'campaign',
                    'last_contacted_at' => null,
                    'is_saved' => false,
                ];
            }
        }

        foreach ($messagePhones as $phone => $data) {
            if (isset($merged[$phone])) {
                $merged[$phone]['last_contacted_at'] = $data['last_contacted_at'];
                if ($merged[$phone]['source'] === 'manual') {
                    // keep manual
                } elseif ($merged[$phone]['source'] === 'campaign') {
                    // keep campaign
                } else {
                    $merged[$phone]['source'] = $data['source'];
                }
            } else {
                $merged[$phone] = [
                    'id' => null,
                    'phone' => $phone,
                    'name' => null,
                    'source' => $data['source'],
                    'last_contacted_at' => $data['last_contacted_at'],
                    'is_saved' => false,
                ];
            }
        }

        $items = collect($merged)->values()->sortByDesc('last_contacted_at')->values();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'required|string|max:50',
        ]);

        $userId = Auth::id();
        $phone = $this->normalizePhone($data['phone']);

        $contact = Contact::updateOrCreate(
            ['user_id' => $userId, 'phone' => $phone],
            ['name' => $data['name'] ?? null, 'source' => 'manual']
        );

        return response()->json([
            'success' => true,
            'message' => 'Contact saved.',
            'data' => $contact,
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:csv,txt,tsv,xlsx,xls,vcf',
        ]);

        $rows = ContactFileParser::parse($request->file('file'));

        // Hard cap to protect against runaway imports and memory exhaustion.
        $rows = array_slice($rows, 0, 5000);

        $userId = Auth::id();
        $now = now();
        $inserts = [];
        $imported = 0;
        $updated = 0;
        $invalid = 0;

        foreach ($rows as $row) {
            $phone = $this->normalizePhone($row['phone'] ?? '');
            if (empty($phone) || strlen($phone) < 7) {
                $invalid++;
                continue;
            }

            $name = trim($row['name'] ?? '');
            $name = $name !== '' ? $name : null;

            $inserts[$phone] = [
                'user_id' => $userId,
                'phone' => $phone,
                'name' => $name,
                'source' => 'imported',
                'updated_at' => $now,
            ];

            if (!isset($inserts[$phone]['created_at'])) {
                $inserts[$phone]['created_at'] = $now;
            }
        }

        if (empty($inserts)) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على أرقام هواتف صالحة في الملف المرفوع. يرجى التأكد من أن الملف يحتوي على أرقام هواتف.',
                'data' => [
                    'found' => count($rows),
                    'imported' => 0,
                    'updated' => 0,
                    'invalid' => $invalid,
                ],
            ], 422);
        }

        foreach (array_chunk($inserts, 500, true) as $chunk) {
            $existing = Contact::where('user_id', $userId)
                ->whereIn('phone', array_keys($chunk))
                ->pluck('phone')
                ->toArray();

            $new = array_values(array_diff_key($chunk, array_flip($existing)));
            if (!empty($new)) {
                Contact::insert($new);
                $imported += count($new);
            }

            $toUpdate = array_intersect_key($chunk, array_flip($existing));
            foreach ($toUpdate as $phone => $values) {
                Contact::where('user_id', $userId)->where('phone', $phone)->update([
                    'name' => $values['name'],
                    'source' => $values['source'],
                    'updated_at' => $now,
                ]);
                $updated++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'تمت معالجة جهات الاتصال بنجاح.',
            'data' => [
                'found' => count($rows),
                'imported' => $imported,
                'updated' => $updated,
                'total_saved' => $imported + $updated,
                'invalid' => $invalid,
            ],
        ]);
    }

    public function destroy($id)
    {
        $contact = Contact::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact deleted.',
        ]);
    }

    private function normalizePhone(string $phone): string
    {
        return PhoneNormalizer::normalize($phone);
    }
}

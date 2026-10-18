<?php

namespace App\Modules\MetaTest\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MetaTest\Models\MetaTestConnection;
use App\Modules\MetaTest\Models\MetaTestPhoneNumber;
use App\Modules\MetaTest\Models\MetaTestWaba;
use App\Modules\MetaTest\Services\MetaTestConnectionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MetaTestPhoneNumberController extends Controller
{
    public function index(): JsonResponse
    {
        $numbers = MetaTestPhoneNumber::where('user_id', Auth::id())
            ->with('waba')
            ->withCount(['messages as sent_count' => function ($q) {
                $q->where('direction', 'outgoing');
            }])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $numbers,
        ]);
    }

    public function sync(): JsonResponse
    {
        $connection = MetaTestConnection::where('user_id', Auth::id())
            ->where('status', MetaTestConnection::STATUS_CONNECTED)
            ->first();

        if (!$connection) {
            return response()->json([
                'success' => false,
                'message' => 'No connected Meta account.',
            ], 404);
        }

        try {
            $manager = new MetaTestConnectionManager(Auth::id());
            $connection = $manager->syncWABAsAndNumbers($connection);

            return response()->json([
                'success' => true,
                'data' => MetaTestPhoneNumber::where('user_id', Auth::id())
                    ->with('waba')
                    ->withCount(['messages as sent_count' => function ($q) {
                        $q->where('direction', 'outgoing');
                    }])
                    ->get(),
                'message' => 'Phone numbers synced from Meta.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add a new (unverified) phone number to one of the user's WABAs.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'waba_id' => 'required|integer',
            'cc' => 'required|string|max:5',
            'phone_number' => 'required|string|max:20',
            'display_name' => 'required|string|max:255',
        ]);

        $waba = MetaTestWaba::where('id', $data['waba_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $connection = MetaTestConnection::where('user_id', Auth::id())
            ->where('status', MetaTestConnection::STATUS_CONNECTED)
            ->first();

        if (!$connection) {
            return response()->json(['success' => false, 'message' => 'No connected Meta account.'], 404);
        }

        try {
            $manager = new MetaTestConnectionManager(Auth::id());
            $result = $manager->addPhoneNumber(
                $connection,
                $waba,
                ltrim($data['cc'], '+'),
                preg_replace('/\D/', '', $data['phone_number']),
                $data['display_name']
            );

            $phone = MetaTestPhoneNumber::firstOrCreate(
                [
                    'user_id' => Auth::id(),
                    'meta_phone_number_id' => (string) ($result['id'] ?? ''),
                ],
                [
                    'waba_id' => $waba->id,
                    'phone_number' => '+' . ltrim($data['cc'], '+') . preg_replace('/\D/', '', $data['phone_number']),
                    'display_name' => $data['display_name'],
                    'status' => 'PENDING',
                    'is_active' => false,
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $phone,
                'message' => 'Number added. Now send a verification code and verify it.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $this->metaErrorMessage($e)], 422);
        }
    }

    /**
     * Send the Meta ownership verification code (SMS or voice call).
     */
    public function sendCode(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'method' => 'sometimes|in:SMS,VOICE',
            'language' => 'sometimes|string|max:10',
        ]);

        $phone = MetaTestPhoneNumber::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $connection = MetaTestConnection::where('user_id', Auth::id())
            ->where('status', MetaTestConnection::STATUS_CONNECTED)
            ->firstOrFail();

        try {
            $manager = new MetaTestConnectionManager(Auth::id());
            $manager->requestPhoneVerificationCode(
                $connection,
                $phone->meta_phone_number_id,
                $data['method'] ?? 'SMS',
                $data['language'] ?? 'ar'
            );

            return response()->json(['success' => true, 'message' => 'Verification code sent.']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $this->metaErrorMessage($e)], 500);
        }
    }

    /**
     * Surface Meta's localized user-facing error text when available —
     * the generic exception message hides the real reason (e.g. number cap).
     */
    private function metaErrorMessage(\Throwable $e): string
    {
        if (method_exists($e, 'getMetaError')) {
            $meta = $e->getMetaError() ?? [];
            $msg = $meta['error_user_msg'] ?? $meta['error_user_title'] ?? $meta['message'] ?? null;
            if ($msg) {
                return trim($msg);
            }
        }

        return $e->getMessage();
    }

    /**
     * Submit the verification code received by SMS/voice.
     */
    public function verify(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['code' => 'required|string|max:10']);

        $phone = MetaTestPhoneNumber::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $connection = MetaTestConnection::where('user_id', Auth::id())
            ->where('status', MetaTestConnection::STATUS_CONNECTED)
            ->firstOrFail();

        try {
            $manager = new MetaTestConnectionManager(Auth::id());
            $manager->verifyPhoneCode($connection, $phone->meta_phone_number_id, $data['code']);

            $phone->update(['status' => 'VERIFIED']);

            return response()->json(['success' => true, 'message' => 'Number verified. You can now register it.']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $this->metaErrorMessage($e)], 422);
        }
    }

    /**
     * Remove a phone number: deregister + delete it from Meta when possible,
     * then drop the local row so the dashboard stays accurate.
     */
    public function destroy(int $id): JsonResponse
    {
        $phone = MetaTestPhoneNumber::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $connection = MetaTestConnection::where('user_id', Auth::id())
            ->where('status', MetaTestConnection::STATUS_CONNECTED)
            ->first();

        $metaResult = null;
        $metaNote = null;
        if ($connection && $phone->meta_phone_number_id) {
            try {
                $manager = new MetaTestConnectionManager(Auth::id());

                if ($phone->status === 'CONNECTED') {
                    try {
                        $manager->deregisterPhoneNumber($connection, $phone->meta_phone_number_id);
                    } catch (\Throwable $e) {
                        Log::warning('MetaTest: deregister failed before delete: ' . $e->getMessage());
                    }
                }

                $metaResult = $manager->deletePhoneNumber($connection, $phone->meta_phone_number_id);
            } catch (\Throwable $e) {
                $msg = $this->metaErrorMessage($e);

                // If Meta says the object is gone or the op is unsupported
                // (e.g. test-WABA numbers), local removal is still correct.
                if (preg_match('/does not exist|cannot be loaded|unsupported delete|does not support this operation/i', $msg)) {
                    $metaNote = 'Removed locally — Meta reports the number cannot be deleted or does not exist there.';
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Meta refused to delete this number: ' . $msg,
                    ], 422);
                }
            }
        }

        $phone->delete();

        return response()->json([
            'success' => true,
            'message' => $metaNote ?: 'Phone number deleted.',
            'meta_deleted' => $metaResult !== null,
        ]);
    }

    public function register(Request $request, int $id): JsonResponse
    {
        $phone = MetaTestPhoneNumber::where('id', $id)
            ->where('user_id', Auth::id())
            ->with('waba')
            ->firstOrFail();

        $connection = MetaTestConnection::where('user_id', Auth::id())
            ->where('status', MetaTestConnection::STATUS_CONNECTED)
            ->first();

        if (!$connection) {
            return response()->json([
                'success' => false,
                'message' => 'No connected Meta account.',
            ], 404);
        }

        try {
            $manager = new MetaTestConnectionManager(Auth::id());
            $result = $manager->registerPhoneNumber($connection, $phone->meta_phone_number_id);

            $phone->update(['status' => 'CONNECTED']);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Phone number registered.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

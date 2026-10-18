<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MessengerAutoReplyLog;
use App\Models\MessengerCampaign;
use App\Models\MessengerMessage;
use App\Models\MessengerPage;
use App\Models\User;
use Illuminate\Http\Request;

class MessengerAdminController extends Controller
{
    public function overview()
    {
        $stats = [
            'total_users' => User::whereHas('messengerPages')->count(),
            'connected_pages' => MessengerPage::where('status', 'connected')->count(),
            'total_pages' => MessengerPage::count(),
            'messages_sent' => MessengerMessage::where('direction', 'outgoing')->count(),
            'messages_received' => MessengerMessage::where('direction', 'incoming')->count(),
            'total_messages' => MessengerMessage::count(),
            'total_campaigns' => MessengerCampaign::count(),
            'running_campaigns' => MessengerCampaign::where('status', 'running')->count(),
            'ai_replies' => MessengerAutoReplyLog::where('mode', 'ai')->count(),
            'auto_replies' => MessengerAutoReplyLog::count(),
            'failed_messages' => MessengerMessage::where('status', 'failed')->count(),
        ];

        return response()->json(['success' => true, 'data' => $stats]);
    }

    public function pages(Request $request)
    {
        $query = MessengerPage::with('user:id,name,email')
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('page_name', 'like', "%{$search}%")
                    ->orWhere('page_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        return response()->json(['success' => true, 'data' => $query->paginate($request->input('per_page', 20))]);
    }

    public function messages(Request $request)
    {
        $query = MessengerMessage::with(['user:id,name,email', 'page:id,page_id,page_name'])
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('psid', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%");
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json(['success' => true, 'data' => $query->paginate($request->input('per_page', 20))]);
    }

    public function campaigns(Request $request)
    {
        $query = MessengerCampaign::with(['user:id,name,email', 'page:id,page_id,page_name'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json(['success' => true, 'data' => $query->paginate($request->input('per_page', 20))]);
    }
}

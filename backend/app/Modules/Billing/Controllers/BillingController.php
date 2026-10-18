<?php

namespace App\Modules\Billing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BillingController extends Controller
{
    /**
     * List user's billing transactions history.
     */
    public function transactions(Request $request)
    {
        try {
            $userId = Auth::id();

            $query = Transaction::forUser($userId)
                ->with(['plan', 'subscription'])
                ->orderBy('created_at', 'desc');

            if ($type = $request->input('type')) {
                $query->where('type', $type);
            }

            if ($status = $request->input('status')) {
                $query->where('status', $status);
            }

            $perPage = $request->input('per_page', 15);
            $perPage = min((int) $perPage, 100);

            $transactions = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $transactions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading transactions: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List user's invoices history.
     */
    public function invoices(Request $request)
    {
        try {
            $userId = Auth::id();

            $query = Invoice::forUser($userId)
                ->with(['subscription', 'transaction'])
                ->orderBy('created_at', 'desc');

            if ($status = $request->input('status')) {
                $query->where('status', $status);
            }

            $perPage = $request->input('per_page', 15);
            $perPage = min((int) $perPage, 100);

            $invoices = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $invoices,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading invoices: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a single invoice.
     */
    public function showInvoice(int $id)
    {
        try {
            $userId = Auth::id();

            $invoice = Invoice::forUser($userId)
                ->with(['subscription', 'transaction'])
                ->where('id', $id)
                ->first();

            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $invoice,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading invoice: ' . $e->getMessage(),
            ], 500);
        }
    }
}

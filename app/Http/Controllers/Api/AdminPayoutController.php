<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Services\Marketplace\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminPayoutController extends Controller
{
    public function index(Request $request)
    {
        $query = Payout::query()
            ->with('store')
            ->latest();

        /*
         * Tenant admin sees only
         * stores in their tenant.
         *
         * Platform admin normally has
         * tenant_id = null and sees all.
         */
        if ($request->user()->tenant_id !== null) {
            $query->whereHas(
                'store',
                fn ($storeQuery) =>
                    $storeQuery->where(
                        'tenant_id',
                        $request->user()->tenant_id
                    )
            );
        }

        return response()->json([
            'payouts' =>
                $query->get(),
        ]);
    }

    public function updateStatus(
        Request $request,
        Payout $payout,
        PayoutService $payoutService
    ) {
        $data = $request->validate([
            'status' => [
                'required',
                'in:processing,paid,failed,cancelled',
            ],

            'reference' => [
                'nullable',
                'string',
                'max:191',
            ],
        ]);

        /*
         * Prevent tenant admin from
         * managing another tenant.
         */
        if (
            $request->user()->tenant_id !== null
        ) {
            $payout->loadMissing('store');

            if (
                (int) $payout->store->tenant_id !==
                (int) $request->user()->tenant_id
            ) {
                abort(403);
            }
        }

        try {
            $updated = match (
                $data['status']
            ) {
                'processing' =>
                    $payoutService
                        ->markProcessing(
                            $payout
                        ),

                'paid' =>
                    $payoutService
                        ->markPaid(
                            $payout,
                            $data['reference']
                                ?? null
                        ),

                'failed' =>
                    $payoutService
                        ->markFailed(
                            $payout
                        ),

                'cancelled' =>
                    $payoutService
                        ->cancel(
                            $payout
                        ),
            };
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'status' =>
                    $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' =>
                'تم تحديث طلب السحب.',

            'payout' =>
                $updated,
        ]);
    }
}
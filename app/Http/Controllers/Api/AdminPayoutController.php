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
        $user = $request->user();

        $query = Payout::query()
            ->with('store')
            ->latest();

        // Platform admin can see all payouts.
        if (! $user->isPlatformAdmin()) {
            abort_unless($user->tenant_id, 403);

            $query->whereHas('store', function ($storeQuery) use ($user) {
                $storeQuery->where('tenant_id', $user->tenant_id);
            });
        }

        return response()->json([
            'payouts' => $query->get(),
        ]);
    }

    public function updateStatus(
        Request $request,
        Payout $payout,
        PayoutService $payoutService
    ) {
        $user = $request->user();

        // Verify tenant ownership before changing payout status.
        if (! $user->isPlatformAdmin()) {
            abort_unless($user->tenant_id, 403);

            $payout->loadMissing('store');

            abort_unless(
                $payout->store &&
                (int) $payout->store->tenant_id === (int) $user->tenant_id,
                403
            );
        }

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

        try {
            $updated = match ($data['status']) {
                'processing' => $payoutService->markProcessing($payout),
                'paid' => $payoutService->markPaid(
                    $payout,
                    $data['reference'] ?? null
                ),
                'failed' => $payoutService->markFailed($payout),
                'cancelled' => $payoutService->cancel($payout),
            };
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'status' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'تم تحديث طلب السحب.',
            'payout' => $updated,
        ]);
    }
}

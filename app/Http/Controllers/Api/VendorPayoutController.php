<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Models\Store;
use App\Services\Marketplace\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VendorPayoutController extends Controller
{
    public function index(Request $request)
    {
        $storeIds = $request->user()
            ->ownedStores()
            ->pluck('id');

        $payouts = Payout::query()
            ->whereIn(
                'store_id',
                $storeIds
            )
            ->latest()
            ->get();

        return response()->json([
            'payouts' => $payouts,
        ]);
    }

    public function store(
        Request $request,
        PayoutService $payoutService
    ) {
        $data = $request->validate([
            'store_id' => [
                'required',
                'integer',
            ],

            'amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'currency' => [
                'required',
                'string',
                'size:3',
            ],

            'method' => [
                'required',
                'string',
                'max:50',
            ],

            'destination_details' => [
                'nullable',
                'array',
            ],
        ]);

        $store = Store::query()
            ->whereKey(
                $data['store_id']
            )
            ->whereHas(
                'owner',
                fn ($query) =>
                    $query->whereKey(
                        $request->user()->id
                    )
            )
            ->first();

        /*
         * If your Store relation is not
         * called owner, ownership check
         * below handles it safely.
         */
        if (! $store) {
            $store = $request->user()
    ->ownedStores()
    ->where(
        'stores.id',
        $data['store_id']
    )
    ->first();
        }

if (! $store) {
    throw ValidationException::withMessages([
        'store_id' =>
            'هذا المتجر غير تابع لك.',
    ]);
}

        try {
            $payout = $payoutService->request(
                $store,
                (float) $data['amount'],
                $data['currency'],
                $data['method'],
                $data['destination_details']
                    ?? []
            );
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'amount' =>
                    $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' =>
                'تم إرسال طلب السحب بنجاح.',

            'payout' =>
                $payout,
        ], 201);
    }
}
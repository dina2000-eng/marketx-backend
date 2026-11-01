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
    ->where('tenant_id', $request->user()->tenant_id)
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

        $store = $request->user()
    ->ownedStores()
    ->where('tenant_id', $request->user()->tenant_id)
    ->whereKey($data['store_id'])
    ->first();

if (! $store) {
    throw ValidationException::withMessages([
        'store_id' => 'هذا المتجر غير تابع لك.',
    ]);
}
    }
}
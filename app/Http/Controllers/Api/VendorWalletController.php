<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SellerWallet;
use App\Models\VendorEarning;
use Illuminate\Http\Request;

class VendorWalletController extends Controller
{
    public function index(Request $request)
    {
        $storeIds = $request->user()
            ->ownedStores()
            ->pluck('id');

        $wallets = SellerWallet::query()
            ->whereIn('store_id', $storeIds)
            ->orderBy('currency')
            ->get();

        $earnings = VendorEarning::query()
            ->whereIn('store_id', $storeIds)
            ->latest()
            ->get();

        return response()->json([
            'wallets' => $wallets,
            'earnings' => $earnings,
        ]);
    }
}
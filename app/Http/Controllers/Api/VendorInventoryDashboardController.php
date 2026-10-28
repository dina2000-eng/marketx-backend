<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use Illuminate\Http\Request;

class VendorInventoryDashboardController extends Controller
{
    public function index(Request $request)
    {
        $store = $request->user()
            ->ownedStores()
            ->first();

        if(!$store){
            return response()->json([
                'message'=>'No store found'
            ],404);
        }

        $inventory = Inventory::where(
            'store_id',
            $store->id
        );

        return response()->json([

            'total_products'=>$inventory->count(),

            'total_quantity'=>$inventory->sum('quantity'),

            'low_stock'=>Inventory::where(
                'store_id',
                $store->id
            )
            ->lowStock()
            ->count(),

            'out_of_stock'=>Inventory::where(
                'store_id',
                $store->id
            )
            ->where(
                'quantity',
                0
            )
            ->count(),

        ]);
    }
}
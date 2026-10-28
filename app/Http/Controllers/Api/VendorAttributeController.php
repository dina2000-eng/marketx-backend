<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductAttribute;
use Illuminate\Http\Request;

class VendorAttributeController extends Controller
{

    public function index(Request $request)
    {
        $store = $request->user()->ownedStore()->first();

        if (!$store) {
            return response()->json([
                'message'=>'No store found'
            ],404);
        }


        return response()->json(
            $store->attributes()
                ->with('values')
                ->get()
        );
    }



    public function store(Request $request)
    {
        $store = $request->user()->ownedStore()->first();

        if (!$store) {
            return response()->json([
                'message'=>'No store found'
            ],404);
        }


        $data = $request->validate([
            'name'=>'required|string|max:255',
        ]);


        $attribute = ProductAttribute::create([
            'store_id'=>$store->id,
            'name'=>$data['name'],
        ]);


        return response()->json($attribute,201);
    }



    public function destroy(
        Request $request,
        ProductAttribute $attribute
    )
    {
        $this->authorizeAttribute($request,$attribute);

        $attribute->delete();

        return response()->json([
            'message'=>'Attribute deleted'
        ]);
    }



    private function authorizeAttribute(
        Request $request,
        ProductAttribute $attribute
    ): void
    {
        abort_unless(
            $attribute->store_id ===
            $request->user()->ownedStore()->first()->id,
            403,
            'Unauthorized'
        );
    }

}
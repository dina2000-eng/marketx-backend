<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttributeValue;
use App\Models\ProductAttribute;
use Illuminate\Http\Request;

class VendorAttributeValueController extends Controller
{

    public function index(
        Request $request,
        ProductAttribute $attribute
    )
    {
        $this->authorizeAttribute($request, $attribute);

        return response()->json(
            $attribute->values()->get()
        );
    }

    public function store(
        Request $request,
        ProductAttribute $attribute
    )
    {
        $this->authorizeAttribute($request, $attribute);

       $data = $request->validate([
    'name' => 'required|string|max:255',
    'sku' => 'nullable|string|max:255',
    'price' => 'required|numeric|min:0',
    'stock' => 'required|integer|min:0',

    'attribute_values' => 'nullable|array',

    'attribute_values.*' => 'exists:attribute_values,id',
]);

        $value = AttributeValue::create([
            'product_attribute_id'=>$attribute->id,
            'value'=>$data['value'],
        ]);

        return response()->json($value,201);
    }

    public function destroy(
        Request $request,
        AttributeValue $value
    )
    {
        $this->authorizeAttribute(
            $request,
            $value->attribute
        );

        $value->delete();

        return response()->json([
            'message'=>'Value deleted'
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
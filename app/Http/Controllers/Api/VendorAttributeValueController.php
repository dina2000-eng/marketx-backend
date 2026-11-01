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
) {
    $this->authorizeAttribute($request, $attribute);

    $data = $request->validate([
        'value' => 'required|string|max:255',
    ]);

    $value = $attribute->values()->create([
        'value' => $data['value'],
    ]);

    return response()->json($value, 201);
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
): void {
    abort_unless(
        $request->user()
            ->ownedStores()
            ->whereKey($attribute->store_id)
            ->exists(),
        403,
        'Unauthorized'
    );
}
}
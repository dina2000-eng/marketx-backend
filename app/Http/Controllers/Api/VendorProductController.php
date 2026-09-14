<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VendorProductController extends Controller
{
    public function index(Request $request)
    {
        $store = $request->user()->ownedStore()->first();

        if (!$store) {
            return response()->json(['message' => 'No store found'], 404);
        }

        return response()->json($store->products()->latest()->get());
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $store = $user->ownedStore()->first();

        if (!$store) {
            return response()->json(['message' => 'No store found'], 404);
        }

        $data = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'base_price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'currency' => 'required|string|size:3',
        ]);

        $product = Product::create([
            ...$data,
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
            'slug' => Str::slug($data['name']).'-'.Str::random(5),
            'status' => 'pending_review',
        ]);

        return response()->json($product, 201);
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeProduct($request, $product);

        $data = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'base_price' => 'sometimes|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
        ]);

        $product->update($data);

        return response()->json($product);
    }

    public function destroy(Request $request, Product $product)
    {
        $this->authorizeProduct($request, $product);
        $product->delete();

        return response()->json(['message' => 'Product deleted']);
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless(
            $product->store->owner_id === $request->user()->id,
            403,
            'Unauthorized'
        );
    }
}

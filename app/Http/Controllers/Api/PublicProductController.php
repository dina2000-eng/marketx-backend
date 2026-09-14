<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class PublicProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()
            ->where('status', 'approved')
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->search, fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->with(['store', 'category'])
            ->paginate(20);

        return response()->json($products);
    }

    public function show(Product $product)
    {
        abort_unless($product->status === 'approved', 404);

        return response()->json($product->load(['store', 'category']));
    }
}

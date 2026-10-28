<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use App\Http\Resources\ProductImageResource;
use Illuminate\Support\Facades\Storage;

class VendorProductImageController extends Controller
{

   public function index(Request $request, Product $product)
{
    $this->authorizeProduct($request, $product);

    $images = $product->images()
        ->orderBy('sort_order')
        ->get();

    return ProductImageResource::collection($images);
}


    public function store(Request $request, Product $product)
{
    $this->authorizeProduct($request, $product);

    $data = $request->validate([
        'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        'is_main' => 'nullable|boolean',
        'sort_order' => 'nullable|integer',
    ]);

    $path = $request->file('image')->store(
        'products',
        'public'
    );

   $isFirstImage = !$product->images()->exists();

$shouldBeMain = ($data['is_main'] ?? false) || $isFirstImage;

if ($shouldBeMain) {
    $product->images()->update([
        'is_main' => false
    ]);
}

$image = ProductImage::create([
    'product_id' => $product->id,
    'image_url' => $path,
    'is_main' => $shouldBeMain,
    'sort_order' => $data['sort_order']
    ?? (($product->images()->max('sort_order') ?? -1) + 1),
]);

    return response()->json(
        new ProductImageResource($image),
        201
    );
}



   public function destroy(Request $request, ProductImage $image)
{
    $product = $image->product;

    $this->authorizeProduct($request, $product);

    $wasMain = $image->is_main;

    if ($image->image_url) {
        Storage::disk('public')->delete($image->image_url);
    }

    $image->delete();

    // إذا حذفنا الصورة الرئيسية، اجعل أول صورة متبقية هي الرئيسية
    if ($wasMain) {
        $nextImage = $product->images()->first();

        if ($nextImage) {
            $nextImage->update([
                'is_main' => true
            ]);
        }
    }

    return response()->json([
        'message' => 'Image deleted'
    ]);
}



    public function makeMain(Request $request, ProductImage $image)
    {
        $product = $image->product;

        $this->authorizeProduct($request,$product);


        $product->images()
            ->update([
                'is_main'=>false
            ]);


        $image->update([
            'is_main'=>true
        ]);


        return new ProductImageResource($image);
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
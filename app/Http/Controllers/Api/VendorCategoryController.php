<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CategoryResource;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VendorCategoryController extends Controller
{

    public function index(Request $request)
    {
        $store = $request->user()->ownedStore()->first();

        if (!$store) {
            return response()->json([
                'message' => 'No store found'
            ], 404);
        }

        return CategoryResource::collection(
       $store->categories()
        ->with('children')
        ->whereNull('parent_id')
        ->orderBy('sort_order')
        ->get()
);
    }


    public function store(Request $request)
    {
        $store = $request->user()->ownedStore()->first();

        if (!$store) {
            return response()->json([
                'message' => 'No store found'
            ],404);
        }


        $data = $request->validate([
            'parent_id' => [
            'nullable',
          Rule::exists('categories','id')
          ->where('store_id',$store->id),
         ],
            'name' => 'required|string|max:255',
            'image_url' => 'nullable|string',
        ]);


        $category = Category::create([
            ...$data,
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
            'slug' => Str::slug($data['name']),
            'is_active' => true,
        ]);


     return new CategoryResource($category);
    }


    public function update(Request $request, Category $category)
    {
        $this->authorizeCategory($request,$category);


        $data = $request->validate([
            'parent_id' => [
            'nullable',
          Rule::exists('categories','id')
          ->where('store_id',$category->store_id),
         ],
            'name'=>'sometimes|string|max:255',
            'image_url'=>'nullable|string',
        ]);


        $category->update($data);


        return new CategoryResource($category);
    }



    public function destroy(Request $request, Category $category)
    {
        $this->authorizeCategory($request,$category);

        $category->delete();

        return response()->json([
            'message'=>'Category deleted'
        ]);
    }



    private function authorizeCategory(Request $request, Category $category): void
    {
        abort_unless(
            $category->store_id === $request->user()->ownedStore()->first()->id,
            403,
            'Unauthorized'
        );
    }

}
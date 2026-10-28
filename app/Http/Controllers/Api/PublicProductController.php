<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class PublicProductController extends Controller
{
    /**
     * Display approved marketplace products.
     */
    public function index(Request $request)
    {
        $query = Product::query()

            // Only approved products
            ->where('status', 'approved')

            // Store must be active
            ->whereHas('store', function ($query) {
                $query->where('status', 'active');
            })

            // Category must be active if product has a category
            ->where(function ($query) {
                $query
                    ->whereNull('category_id')
                    ->orWhereHas('category', function ($categoryQuery) {
                        $categoryQuery->where('is_active', true);
                    });
            });

        /*
         * Search
         *
         * Searches:
         * - product name
         * - product description
         * - store name
         * - category name
         */
        if ($request->filled('search')) {
            $search = trim(
                $request->string('search')->toString()
            );

            $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where(
                        'name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'description',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhereHas(
                        'store',
                        function ($storeQuery) use ($search) {
                            $storeQuery->where(
                                'name',
                                'like',
                                "%{$search}%"
                            );
                        }
                    )
                    ->orWhereHas(
                        'category',
                        function ($categoryQuery) use ($search) {
                            $categoryQuery->where(
                                'name',
                                'like',
                                "%{$search}%"
                            );
                        }
                    );
            });
        }

        /*
         * Category filter
         *
         * Can receive either:
         * category=Electronics
         * or category_id=3
         */
        if ($request->filled('category_id')) {
            $query->where(
                'category_id',
                $request->integer('category_id')
            );
        } elseif ($request->filled('category')) {
            $category = trim(
                $request->string('category')->toString()
            );

            $query->whereHas(
                'category',
                function ($categoryQuery) use ($category) {
                    $categoryQuery->where(
                        'name',
                        $category
                    );
                }
            );
        }

        /*
         * Discounted products only
         */
        if ($request->boolean('discounted')) {
            $query
                ->whereNotNull('compare_price')
                ->whereColumn(
                    'compare_price',
                    '>',
                    'base_price'
                );
        }

        /*
         * Sorting
         */
        switch ($request->get('sort')) {
            case 'price_asc':
                $query->orderBy(
                    'base_price',
                    'asc'
                );
                break;

            case 'price_desc':
                $query->orderBy(
                    'base_price',
                    'desc'
                );
                break;

            case 'name_asc':
                $query->orderBy(
                    'name',
                    'asc'
                );
                break;

            case 'name_desc':
                $query->orderBy(
                    'name',
                    'desc'
                );
                break;

            case 'oldest':
                $query->orderBy(
                    'created_at',
                    'asc'
                );
                break;

            case 'newest':
            default:
                $query->latest();
                break;
        }

        /*
         * Relationships
         */
        $query->with([
            'store',
            'category',
            'images',

            'variants' => function ($query) {
                $query
                    ->where(
                        'is_active',
                        true
                    )
                    ->with(
                        'inventory'
                    );
            },
        ]);

        /*
         * Pagination
         *
         * Default = 20
         * Max = 50
         */
        $perPage = $request->integer(
            'per_page',
            20
        );

        $perPage = max(
            1,
            min(
                $perPage,
                50
            )
        );

        $products = $query
            ->paginate($perPage)
            ->withQueryString();

        return ProductResource::collection(
            $products
        );
    }

    /**
     * Display one public product.
     */
    public function show(Product $product)
    {
        // Product must be approved
        abort_unless(
            $product->status === 'approved',
            404
        );

        // Store must be active
        abort_unless(
            $product->store &&
            $product->store->status === 'active',
            404
        );

        // If product has category,
        // category must be active
        if ($product->category_id !== null) {
            abort_unless(
                $product->category &&
                $product->category->is_active,
                404
            );
        }

        $product->load([
            'store',
            'category',
            'images',

            'variants' => function ($query) {
                $query
                    ->where(
                        'is_active',
                        true
                    )
                    ->with(
                        'inventory'
                    );
            },
        ]);

        return new ProductResource(
            $product
        );
    }
}
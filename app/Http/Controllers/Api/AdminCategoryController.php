<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;

class AdminCategoryController extends Controller
{
    public function index(Request $request)
    {
        $admin = $request->user();

        $this->authorizeAdmin($admin);

        $query = Category::with([
            'store',
            'children',
        ])
            ->latest();

        if ($admin->role === 'tenant_admin') {
            $query->where(
                'tenant_id',
                $admin->tenant_id
            );
        }

        return CategoryResource::collection(
            $query->get()
        );
    }


    public function show(
        Request $request,
        Category $category
    ) {
        $admin = $request->user();

        $this->authorizeAdmin($admin);
        $this->authorizeCategory(
            $admin,
            $category
        );

        return new CategoryResource(
            $category->load([
                'store',
                'children',
                'products',
            ])
        );
    }


    public function destroy(
        Request $request,
        Category $category
    ) {
        $admin = $request->user();

        $this->authorizeAdmin($admin);
        $this->authorizeCategory(
            $admin,
            $category
        );

        $category->delete();

        return response()->json([
            'message' => 'Category deleted',
        ]);
    }


    public function toggleStatus(
        Request $request,
        Category $category
    ) {
        $admin = $request->user();

        $this->authorizeAdmin($admin);
        $this->authorizeCategory(
            $admin,
            $category
        );

        $category->update([
            'is_active' =>
                ! $category->is_active,
        ]);

        return response()->json([
            'message' =>
                'Category status updated',

            'category' =>
                new CategoryResource(
                    $category->fresh([
                        'store',
                        'children',
                    ])
                ),
        ]);
    }


    private function authorizeAdmin(
        $admin
    ): void {
        abort_unless(
            $admin->isPlatformAdmin() ||
            $admin->role === 'tenant_admin',
            403,
            'Unauthorized'
        );
    }


    private function authorizeCategory(
        $admin,
        Category $category
    ): void {
        if (
            $admin->role === 'tenant_admin'
        ) {
            abort_unless(
                $category->tenant_id ===
                    $admin->tenant_id,
                403,
                'This category does not belong to your tenant.'
            );
        }
    }
}
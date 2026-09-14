<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProductController extends Controller
{
    public function pending(Request $request): JsonResponse
    {
        $admin = $request->user();

        if (! $admin->isPlatformAdmin() && $admin->role !== 'tenant_admin') {
            return response()->json([
                'message' => 'غير مصرح لك بمراجعة المنتجات.',
            ], 403);
        }

        $query = Product::with(['store', 'tenant'])
            ->where('status', 'pending_review')
            ->latest();

        if ($admin->role === 'tenant_admin') {
            $query->where('tenant_id', $admin->tenant_id);
        }

        return response()->json([
            'products' => $query->get(),
        ]);
    }


    public function approve(Request $request, Product $product): JsonResponse
    {
        $admin = $request->user();

        if (! $admin->isPlatformAdmin() && $admin->role !== 'tenant_admin') {
            return response()->json([
                'message' => 'غير مصرح لك بالموافقة على المنتجات.',
            ], 403);
        }

        if (
            $admin->role === 'tenant_admin' &&
            $product->tenant_id !== $admin->tenant_id
        ) {
            return response()->json([
                'message' => 'هذا المنتج لا يتبع للـTenant الخاص بك.',
            ], 403);
        }

        if ($product->status !== 'pending_review') {
            return response()->json([
                'message' => 'هذا المنتج تمت مراجعته مسبقاً.',
            ], 422);
        }

        $product->update([
         'status' => 'approved',
         'rejection_reason' => null,
]);

        return response()->json([
            'message' => 'تمت الموافقة على المنتج بنجاح.',
            'product' => $product->fresh(['store', 'tenant']),
        ]);
    }


    public function reject(Request $request, Product $product): JsonResponse
    {
        $admin = $request->user();

        if (! $admin->isPlatformAdmin() && $admin->role !== 'tenant_admin') {
            return response()->json([
                'message' => 'غير مصرح لك برفض المنتجات.',
            ], 403);
        }

        if (
            $admin->role === 'tenant_admin' &&
            $product->tenant_id !== $admin->tenant_id
        ) {
            return response()->json([
                'message' => 'هذا المنتج لا يتبع للـTenant الخاص بك.',
            ], 403);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $product->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return response()->json([
            'message' => 'تم رفض المنتج.',
            'product' => $product->fresh(['store', 'tenant']),
        ]);
    }
}
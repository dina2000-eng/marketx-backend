<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user();

        if (! $admin->isPlatformAdmin() && $admin->role !== 'tenant_admin') {
            return response()->json([
                'message' => 'غير مصرح لك بالوصول إلى المتاجر.',
            ], 403);
        }

        $query = Store::with(['owner', 'tenant'])
            ->latest();

        if ($admin->role === 'tenant_admin') {
            $query->where('tenant_id', $admin->tenant_id);
        }

        return response()->json([
            'stores' => $query->get(),
        ]);
    }

    public function approve(Request $request, Store $store): JsonResponse
    {
        $admin = $request->user();

        if (! $admin->isPlatformAdmin() && $admin->role !== 'tenant_admin') {
            return response()->json([
                'message' => 'غير مصرح لك بالموافقة على المتاجر.',
            ], 403);
        }

        if (
            $admin->role === 'tenant_admin' &&
            $store->tenant_id !== $admin->tenant_id
        ) {
            return response()->json([
                'message' => 'هذا المتجر لا يتبع للـTenant الخاص بك.',
            ], 403);
        }

        if ($store->status !== 'pending') {
            return response()->json([
                'message' => 'هذا المتجر تمت مراجعته مسبقاً.',
            ], 422);
        }

        $store->update([
            'status' => 'active',
            'rejection_reason' => null,
        ]);

        return response()->json([
            'message' => 'تمت الموافقة على المتجر بنجاح.',
            'store' => $store->fresh(['owner', 'tenant']),
        ]);
    }

    public function reject(Request $request, Store $store): JsonResponse
    {
        $admin = $request->user();

        if (! $admin->isPlatformAdmin() && $admin->role !== 'tenant_admin') {
            return response()->json([
                'message' => 'غير مصرح لك برفض المتاجر.',
            ], 403);
        }

        if (
            $admin->role === 'tenant_admin' &&
            $store->tenant_id !== $admin->tenant_id
        ) {
            return response()->json([
                'message' => 'هذا المتجر لا يتبع للـTenant الخاص بك.',
            ], 403);
        }

        if ($store->status !== 'pending') {
            return response()->json([
                'message' => 'هذا المتجر تمت مراجعته مسبقاً.',
            ], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $store->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return response()->json([
            'message' => 'تم رفض المتجر.',
            'store' => $store->fresh(['owner', 'tenant']),
        ]);
    }

    public function suspend(Request $request, Store $store): JsonResponse
    {
        $admin = $request->user();

        if (! $admin->isPlatformAdmin() && $admin->role !== 'tenant_admin') {
            return response()->json([
                'message' => 'غير مصرح لك بتعليق المتاجر.',
            ], 403);
        }

        if (
            $admin->role === 'tenant_admin' &&
            $store->tenant_id !== $admin->tenant_id
        ) {
            return response()->json([
                'message' => 'هذا المتجر لا يتبع للـTenant الخاص بك.',
            ], 403);
        }

        if ($store->status !== 'active') {
            return response()->json([
                'message' => 'يمكن تعليق المتجر النشط فقط.',
            ], 422);
        }

        $store->update([
            'status' => 'suspended',
        ]);

        return response()->json([
            'message' => 'تم تعليق المتجر.',
            'store' => $store->fresh(['owner', 'tenant']),
        ]);
    }
}

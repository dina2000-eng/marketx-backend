<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\VendorRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminVendorRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user();

        if (! $admin->isPlatformAdmin() && $admin->role !== 'tenant_admin') {
            return response()->json([
                'message' => 'غير مصرح لك بالوصول إلى طلبات Vendor.',
            ], 403);
        }

        $query = VendorRequest::with(['user', 'tenant'])
            ->latest();

        if ($admin->role === 'tenant_admin') {
            $query->where('tenant_id', $admin->tenant_id);
        }

        return response()->json([
            'vendor_requests' => $query->get(),
        ]);
    }
public function approve(
    Request $request,
    VendorRequest $vendorRequest
): JsonResponse {
    $admin = $request->user();

    if (
        ! $admin->isPlatformAdmin() &&
        $admin->role !== 'tenant_admin'
    ) {
        return response()->json([
            'message' => 'غير مصرح لك بالموافقة على طلبات Vendor.',
        ], 403);
    }

    if (
        $admin->role === 'tenant_admin' &&
        $vendorRequest->tenant_id !== $admin->tenant_id
    ) {
        return response()->json([
            'message' => 'هذا الطلب لا يتبع للـTenant الخاص بك.',
        ], 403);
    }

    [$vendorRequest, $store] = DB::transaction(function () use (
        $vendorRequest,
        $admin
    ) {
              $vendorRequest = VendorRequest::whereKey($vendorRequest->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($vendorRequest->status !== 'pending') {
            return [null, null];
        }
        $vendorRequest->update([
            'status' => 'approved',
            'rejection_reason' => null,
            'reviewed_at' => now(),
            'reviewed_by' => $admin->id,
        ]);

        $user = $vendorRequest->user;

        $user->update([
            'role' => 'vendor_owner',
            'status' => 'active',
        ]);

        if ($user->ownedStores()->count() === 0) {
            $baseSlug = \Illuminate\Support\Str::slug(
                $vendorRequest->business_name
            );

            $slug = $baseSlug;
            $counter = 1;

            while (
                \App\Models\Store::where(
                    'tenant_id',
                    $vendorRequest->tenant_id
                )->where('slug', $slug)->exists()
            ) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            $store = \App\Models\Store::create([
                'tenant_id' => $vendorRequest->tenant_id,
                'owner_id' => $user->id,
                'name' => $vendorRequest->business_name,
                'slug' => $slug,
                'country_code' => $vendorRequest->tenant->country_code,
                'status' => 'active',
            ]);
        } else {
            $store = $user->ownedStores()->first();
        }

        return [$vendorRequest, $store];
    });

       if (! $vendorRequest) {
        return response()->json([
            'message' => 'هذا الطلب تمت مراجعته مسبقاً.',
        ], 422);
    }

     return response()->json([
        'message' => 'تم قبول طلب Vendor وإنشاء المتجر بنجاح.',
        'vendor_request' => $vendorRequest->fresh([
            'user',
            'tenant',
        ]),
        'store' => $store,
    ]);

}

    public function reject(Request $request, VendorRequest $vendorRequest): JsonResponse
    {
        $admin = $request->user();

        if (! $admin->isPlatformAdmin() && $admin->role !== 'tenant_admin') {
            return response()->json([
                'message' => 'غير مصرح لك برفض طلبات Vendor.',
            ], 403);
        }

        if (
            $admin->role === 'tenant_admin' &&
            $vendorRequest->tenant_id !== $admin->tenant_id
        ) {
            return response()->json([
                'message' => 'هذا الطلب لا يتبع للـTenant الخاص بك.',
            ], 403);
        }

        if ($vendorRequest->status !== 'pending') {
            return response()->json([
                'message' => 'هذا الطلب تمت مراجعته مسبقاً.',
            ], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $vendorRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'reviewed_at' => now(),
            'reviewed_by' => $admin->id,
        ]);

        return response()->json([
            'message' => 'تم رفض طلب Vendor.',
            'vendor_request' => $vendorRequest->fresh(['user', 'tenant']),
        ]);
    }
}
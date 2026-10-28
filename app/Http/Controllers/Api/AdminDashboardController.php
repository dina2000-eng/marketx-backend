<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\VendorRequest;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        $admin = $request->user();

        if (
            ! $admin->isPlatformAdmin() &&
            $admin->role !== 'tenant_admin'
        ) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $tenantId = $admin->role === 'tenant_admin'
            ? $admin->tenant_id
            : null;

        $products = Product::query();
        $stores = Store::query();
        $vendorRequests = VendorRequest::query();
        $orders = Order::query();
        $categories = Category::query();

        if ($tenantId !== null) {
            $products->where('tenant_id', $tenantId);
            $stores->where('tenant_id', $tenantId);
            $vendorRequests->where('tenant_id', $tenantId);
            $orders->where('tenant_id', $tenantId);
            $categories->where('tenant_id', $tenantId);
        }

        return response()->json([
            'pending_products' => (clone $products)
                ->where('status', 'pending_review')
                ->count(),

            'total_products' => (clone $products)
                ->count(),

            'total_stores' => (clone $stores)
                ->count(),

            'pending_vendor_requests' => (clone $vendorRequests)
                ->where('status', 'pending')
                ->count(),

            'total_orders' => (clone $orders)
                ->count(),

            'total_categories' => (clone $categories)
                ->count(),
        ]);
    }
}
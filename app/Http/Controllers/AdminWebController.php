<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorRequest;
use App\Services\Marketplace\VendorEarningService;
use App\Services\Orders\AdminOrderService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Payout;
use App\Services\Marketplace\PayoutService;
use Illuminate\Validation\ValidationException;

class AdminWebController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $admin = User::whereNull('tenant_id')
            ->where('role', 'platform_admin')
            ->where('status', 'active')
            ->where(function ($query) use ($validated) {
                $query->where('email', $validated['login'])
                    ->orWhere('phone', $validated['login']);
            })
            ->first();

        if (! $admin || ! Hash::check($validated['password'], $admin->password)) {
            return back()
                ->withErrors(['login' => 'بيانات الدخول غير صحيحة.'])
                ->onlyInput('login');
        }

        Auth::guard('web')->login($admin);
        $request->session()->regenerate();

        $admin->forceFill([
            'last_login_at' => now(),
        ])->save();

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function dashboard()
    {
        $stats = [
            'pending_products' => Product::where('status', 'pending_review')->count(),
            'total_products' => Product::count(),
            'total_stores' => Store::count(),
            'pending_vendor_requests' => VendorRequest::where('status', 'pending')->count(),
            'total_orders' => Order::count(),
            'total_categories' => Category::count(),
        ];

        $products = Product::with(['store', 'tenant', 'category', 'images'])
            ->where('status', 'pending_review')
            ->latest()
            ->paginate(15);

        $vendorRequests = VendorRequest::with(['user', 'tenant'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        return view('admin.dashboard', compact(
            'stats',
            'products',
            'vendorRequests'
        ));
    }

    public function approveProduct(Product $product)
    {
        if ($product->status !== 'pending_review') {
            return back()->withErrors([
                'product' => 'هذا المنتج تمت مراجعته مسبقاً.',
            ]);
        }

        $product->update([
            'status' => 'approved',
            'rejection_reason' => null,
        ]);

        return back()->with('success', 'تمت الموافقة على المنتج بنجاح.');
    }

    public function rejectProduct(Request $request, Product $product)
    {
        if ($product->status !== 'pending_review') {
            return back()->withErrors([
                'product' => 'هذا المنتج تمت مراجعته مسبقاً.',
            ]);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $product->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return back()->with('success', 'تم رفض المنتج.');
    }

    public function products(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,pending_review,approved,rejected'],
        ]);

        $search = trim($validated['search'] ?? '');
        $status = $validated['status'] ?? 'all';

        $query = Product::with([
            'store',
            'tenant',
            'category',
            'images',
        ])->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $products = $query->paginate(15)->withQueryString();

        return view('admin.products', compact(
            'products',
            'search',
            'status'
        ));
    }

    public function approveVendorRequest(VendorRequest $vendorRequest)
    {
        if ($vendorRequest->status !== 'pending') {
            return back()->withErrors([
                'vendor' => 'هذا الطلب تمت مراجعته مسبقاً.',
            ]);
        }

        DB::transaction(function () use ($vendorRequest) {
            $vendorRequest->update([
                'status' => 'approved',
                'rejection_reason' => null,
                'reviewed_at' => now(),
                'reviewed_by' => Auth::guard('web')->id(),
            ]);

            $user = $vendorRequest->user;

            $user->update([
                'role' => 'vendor_owner',
                'status' => 'active',
            ]);

            if ($user->ownedStores()->count() === 0) {
                $baseSlug = Str::slug(
                    $vendorRequest->business_name
                );

                $slug = $baseSlug;
                $counter = 1;

                while (
                    Store::where('tenant_id', $vendorRequest->tenant_id)
                        ->where('slug', $slug)
                        ->exists()
                ) {
                    $slug = $baseSlug.'-'.$counter++;
                }

                Store::create([
                    'tenant_id' => $vendorRequest->tenant_id,
                    'owner_id' => $user->id,
                    'name' => $vendorRequest->business_name,
                    'slug' => $slug,
                    'country_code' => $vendorRequest->tenant->country_code,
                    'status' => 'active',
                ]);
            }
        });

        return back()->with('success', 'تم قبول طلب التاجر وإنشاء المتجر بنجاح.');
    }

    public function rejectVendorRequest(Request $request, VendorRequest $vendorRequest)
    {
        if ($vendorRequest->status !== 'pending') {
            return back()->withErrors([
                'vendor' => 'هذا الطلب تمت مراجعته مسبقاً.',
            ]);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $vendorRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'reviewed_at' => now(),
            'reviewed_by' => Auth::guard('web')->id(),
        ]);

        return back()->with('success', 'تم رفض طلب التاجر.');
    }

    public function stores()
    {
        $stores = Store::with(['owner', 'tenant'])
            ->latest()
            ->paginate(15);

        return view('admin.stores', compact('stores'));
    }

    public function vendorRequests(Request $request)
{
    $validated = $request->validate([
        'search' => ['nullable', 'string', 'max:100'],
        'status' => ['nullable', 'in:all,pending,approved,rejected'],
    ]);

    $search = trim($validated['search'] ?? '');
    $status = $validated['status'] ?? 'all';

    $query = VendorRequest::with([
        'user',
        'tenant',
        'reviewer',
    ])->latest();

    if ($status !== 'all') {
        $query->where('status', $status);
    }

    if ($search !== '') {
        $query->where(function ($builder) use ($search) {
            $builder->where('business_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });

            if (ctype_digit($search)) {
                $builder->orWhere('id', (int) $search);
            }
        });
    }

    $vendorRequests = $query->paginate(5)->withQueryString();

    return view('admin.vendor-requests', compact(
        'vendorRequests',
        'search',
        'status'
    ));
}

    public function approveStore(Store $store)
    {
        if ($store->status !== 'pending') {
            return back()->withErrors([
                'store' => 'هذا المتجر تمت مراجعته مسبقاً.',
            ]);
        }

        $store->update([
            'status' => 'active',
            'rejection_reason' => null,
        ]);

        return back()->with('success', 'تمت الموافقة على المتجر.');
    }

    public function rejectStore(Request $request, Store $store)
    {
        if ($store->status !== 'pending') {
            return back()->withErrors([
                'store' => 'هذا المتجر تمت مراجعته مسبقاً.',
            ]);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $store->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return back()->with('success', 'تم رفض المتجر.');
    }

    public function suspendStore(Store $store)
    {
        if ($store->status !== 'active') {
            return back()->withErrors([
                'store' => 'يمكن تعليق المتجر النشط فقط.',
            ]);
        }

        $store->update([
            'status' => 'suspended',
        ]);

        return back()->with('success', 'تم تعليق المتجر.');
    }

    public function orders()
    {
        $orders = Order::with([
            'user',
            'tenant',
            'items.store',
            'vendorOrders.store',
            'payments',
        ])->latest()->paginate(5);

        return view('admin.orders', compact('orders'));
    }

    public function updateOrderStatus(
        Request $request,
        Order $order,
        AdminOrderService $orderService,
        PaymentService $paymentService,
        VendorEarningService $earningService
    ) {
        $validated = $request->validate([
            'status' => [
                'required',
                'in:pending,confirmed,processing,shipped,delivered,cancelled',
            ],
        ]);

        $orderService->updateStatus(
            $order,
            $validated['status'],
            $paymentService,
            $earningService
        );

        return back()->with('success', 'تم تحديث حالة الطلب.');
    }

    public function categories()
{
    $categories = Category::with([
        'store',
        'tenant',
        'parent',
        'children',
        'products',
    ])
        ->latest()
        ->paginate(20);

    return view('admin.categories', compact('categories'));
}

public function toggleCategoryStatus(Category $category)
{
    $category->update([
        'is_active' => ! $category->is_active,
    ]);

    return back()->with('success', 'تم تحديث حالة التصنيف.');
}

public function deleteCategory(Category $category)
{
    $category->delete();

    return back()->with('success', 'تم حذف التصنيف بنجاح.');
}

public function payouts()
{
    $payouts = Payout::with([
        'store.tenant',
        'store.owner',
    ])
        ->latest()
        ->paginate(20);

    return view('admin.payouts', compact('payouts'));
}

public function updatePayoutStatus(
    Request $request,
    Payout $payout,
    PayoutService $payoutService
) {
    $data = $request->validate([
        'status' => [
            'required',
            'in:processing,paid,failed,cancelled',
        ],
        'reference' => [
            'nullable',
            'string',
            'max:191',
        ],
    ]);

    try {
        match ($data['status']) {
            'processing' => $payoutService->markProcessing($payout),
            'paid' => $payoutService->markPaid(
                $payout,
                $data['reference'] ?? null
            ),
            'failed' => $payoutService->markFailed($payout),
            'cancelled' => $payoutService->cancel($payout),
        };
    } catch (\RuntimeException $e) {
        throw ValidationException::withMessages([
            'status' => $e->getMessage(),
        ]);
    }

    return back()->with('success', 'تم تحديث طلب السحب.');
}
}

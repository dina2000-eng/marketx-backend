<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
        $products = Product::with([
            'store',
            'tenant',
            'category',
            'images',
        ])
            ->where('status', 'pending_review')
            ->latest()
            ->paginate(15);

        return view('admin.dashboard', compact('products'));
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
}

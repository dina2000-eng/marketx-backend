<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('web')->user();

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        if (
            $admin->role !== 'platform_admin' ||
            $admin->tenant_id !== null ||
            $admin->status !== 'active'
        ) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('admin.login')
                ->withErrors([
                    'login' => 'غير مصرح لك بالدخول إلى لوحة الإدارة.',
                ]);
        }

        return $next($request);
    }
}

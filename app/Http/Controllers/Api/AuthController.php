<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * تسجيل مستخدم جديد داخل Tenant معيّن.
     * تطبيق الموبايل مبني لكل عميل (Tenant) لحاله، فبيبعت tenant_id
     * أو tenant_slug مع كل طلب Auth عشان نعرف داخل أي منصة عم يسجّل.
     */
    public function register(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->where('tenant_id', $tenant->id),
            ],
            'phone' => [
                'nullable', 'string', 'max:30',
                Rule::unique('users', 'phone')->where('tenant_id', $tenant->id),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (empty($validated['email']) && empty($validated['phone'])) {
            return response()->json([
                'message' => 'لازم توفري بريد إلكتروني أو رقم هاتف على الأقل.',
            ], 422);
        }

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => 'customer',
            'status' => 'active',
        ]);

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * تسجيل الدخول بالبريد أو الهاتف + كلمة المرور، ضمن Tenant معيّن.
     */
    public function login(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        $validated = $request->validate([
            'login' => ['required', 'string'], // بريد إلكتروني أو رقم هاتف
            'password' => ['required', 'string'],
        ]);

        $user = User::where('tenant_id', $tenant->id)
            ->where(function ($query) use ($validated) {
                $query->where('email', $validated['login'])
                    ->orWhere('phone', $validated['login']);
            })
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'بيانات الدخول غير صحيحة.',
            ], 401);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'الحساب غير مفعّل أو موقوف حالياً.',
            ], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * إلغاء الـtoken الحالي فقط (مو كل أجهزة المستخدم).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'تم تسجيل الخروج بنجاح.',
        ]);
    }

    /**
     * بيانات المستخدم المسجّل حالياً + الـTenant تبعه.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->load('tenant'),
        ]);
    }

    /**
     * تحديد أي Tenant بيسجّل/بيدخل عليه المستخدم.
     * بيقبل tenant_id أو tenant_slug (حسب ما يبعت تطبيق الموبايل).
     */
    private function resolveTenant(Request $request): Tenant
    {
        $request->validate([
            'tenant_id' => ['required_without:tenant_slug', 'integer', 'exists:tenants,id'],
            'tenant_slug' => ['required_without:tenant_id', 'string', 'exists:tenants,slug'],
        ]);

        return $request->filled('tenant_id')
            ? Tenant::findOrFail($request->input('tenant_id'))
            : Tenant::where('slug', $request->input('tenant_slug'))->firstOrFail();
    }
}

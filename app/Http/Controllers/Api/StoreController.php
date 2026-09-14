<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isVendorOwner()) {
            return response()->json([
                'message' => 'يجب أن تكون Vendor Owner لإنشاء متجر.',
            ], 403);
        }

        if ($user->ownedStore()->exists()) {
            return response()->json([
                'message' => 'لديك متجر موجود بالفعل.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:255'],
            'cover_url' => ['nullable', 'string', 'max:255'],
        ]);

        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug;
        $counter = 1;

        while (
            Store::where('tenant_id', $user->tenant_id)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        $store = Store::create([
            'tenant_id' => $user->tenant_id,
            'owner_id' => $user->id,
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'phone' => $validated['phone'] ?? $user->phone,
            'email' => $validated['email'] ?? $user->email,
            'logo_url' => $validated['logo_url'] ?? null,
            'cover_url' => $validated['cover_url'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'تم إنشاء المتجر بنجاح، وهو الآن بانتظار موافقة الإدارة.',
            'store' => $store,
        ], 201);
    }

    public function myStore(Request $request): JsonResponse
    {
        $user = $request->user();

        $store = $user->ownedStore()->latest()->first();

        return response()->json([
            'store' => $store,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VendorRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $existingRequest = VendorRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($existingRequest) {
            return response()->json([
                'message' => 'لديك طلب Vendor قيد المراجعة بالفعل.',
            ], 422);
        }

        $vendorRequest = VendorRequest::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'business_name' => $validated['business_name'],
            'phone' => $validated['phone'] ?? $user->phone,
            'email' => $validated['email'] ?? $user->email,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'تم إرسال طلب Vendor بنجاح.',
            'vendor_request' => $vendorRequest,
        ], 201);
    }

    public function myRequest(Request $request): JsonResponse
    {
        $vendorRequest = VendorRequest::where('user_id', $request->user()->id)
            ->latest()
            ->first();

        return response()->json([
            'vendor_request' => $vendorRequest,
        ]);
    }
}

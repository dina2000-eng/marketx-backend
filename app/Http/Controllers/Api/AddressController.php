<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $addresses = Address::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $user->tenant_id)
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        return response()->json([
            'addresses' => $addresses,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $this->validateAddress($request);

        $data['country_code'] =
            strtoupper($data['country_code']);

        return DB::transaction(function () use (
            $user,
            $data
        ) {
            $hasAddresses = Address::query()
                ->where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->exists();

            $makeDefault =
                !$hasAddresses ||
                ($data['is_default'] ?? false);

            if ($makeDefault) {
                $this->clearDefault(
                    $user->id,
                    $user->tenant_id,
                );
            }

            $address = Address::create([
                ...$data,
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'is_default' => $makeDefault,
            ]);

            return response()->json([
                'message' =>
                    'Address created successfully.',
                'address' => $address,
            ], 201);
        });
    }

    public function update(
        Request $request,
        Address $address
    ): JsonResponse {
        $user = $request->user();

        $this->ensureOwner(
            $address,
            $user->id,
            $user->tenant_id,
        );

        $data = $this->validateAddress($request);

        $data['country_code'] =
            strtoupper($data['country_code']);

        return DB::transaction(function () use (
            $address,
            $data,
            $user
        ) {
            if ($data['is_default'] ?? false) {
                $this->clearDefault(
                    $user->id,
                    $user->tenant_id,
                );
            }

            $address->update($data);

            return response()->json([
                'message' =>
                    'Address updated successfully.',
                'address' =>
                    $address->fresh(),
            ]);
        });
    }

    public function destroy(
        Request $request,
        Address $address
    ): JsonResponse {
        $user = $request->user();

        $this->ensureOwner(
            $address,
            $user->id,
            $user->tenant_id,
        );

        return DB::transaction(function () use (
            $address,
            $user
        ) {
            $wasDefault =
                $address->is_default;

            $address->delete();

            if ($wasDefault) {
                $next = Address::query()
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->where(
                        'tenant_id',
                        $user->tenant_id
                    )
                    ->latest()
                    ->first();

                if ($next) {
                    $next->update([
                        'is_default' => true,
                    ]);
                }
            }

            return response()->json([
                'message' =>
                    'Address deleted successfully.',
            ]);
        });
    }

    public function makeDefault(
        Request $request,
        Address $address
    ): JsonResponse {
        $user = $request->user();

        $this->ensureOwner(
            $address,
            $user->id,
            $user->tenant_id,
        );

        DB::transaction(function () use (
            $address,
            $user
        ) {
            $this->clearDefault(
                $user->id,
                $user->tenant_id,
            );

            $address->update([
                'is_default' => true,
            ]);
        });

        return response()->json([
            'message' =>
                'Default address updated.',
            'address' =>
                $address->fresh(),
        ]);
    }

    private function validateAddress(
        Request $request
    ): array {
        return $request->validate([
            'label' => [
                'required',
                'string',
                'max:50',
            ],

            'recipient_name' => [
                'required',
                'string',
                'max:150',
            ],

            'phone' => [
                'required',
                'string',
                'max:30',
            ],

            'country_code' => [
                'required',
                'string',
                'size:2',
                'regex:/^[A-Za-z]{2}$/',
            ],

            'city' => [
                'required',
                'string',
                'max:100',
            ],

            'area' => [
                'nullable',
                'string',
                'max:150',
            ],

            'street' => [
                'required',
                'string',
                'max:255',
            ],

            'building' => [
                'nullable',
                'string',
                'max:100',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:500',
            ],

            'is_default' => [
                'sometimes',
                'boolean',
            ],
        ]);
    }

    private function clearDefault(
        int $userId,
        ?int $tenantId
    ): void {
        Address::query()
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->update([
                'is_default' => false,
            ]);
    }

    private function ensureOwner(
        Address $address,
        int $userId,
        ?int $tenantId
    ): void {
        abort_unless(
            (int) $address->user_id ===
                $userId &&
            (int) $address->tenant_id ===
                (int) $tenantId,
            404
        );
    }
}
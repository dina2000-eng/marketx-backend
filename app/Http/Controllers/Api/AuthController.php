<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * Register
     */
    public function register(
        Request $request
    ): JsonResponse {
        $tenant =
            $this->resolveTenant(
                $request
            );

        $request->merge([
            'email' => $request->filled('email')
                ? strtolower(trim($request->input('email')))
                : null,

            'phone' => $request->filled('phone')
                ? trim($request->input('phone'))
                : null,
        ]);
        $validated =
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:150',
                ],

                'email' => [
                    'nullable',
                    'email',
                    'max:255',

                    Rule::unique(
                        'users',
                        'email'
                    )->where(
                        'tenant_id',
                        $tenant->id
                    ),
                ],

                'phone' => [
                    'nullable',
                    'string',
                    'max:30',

                    Rule::unique(
                        'users',
                        'phone'
                    )->where(
                        'tenant_id',
                        $tenant->id
                    ),
                ],

                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                ],
            ]);

        if (
            empty($validated['email']) &&
            empty($validated['phone'])
        ) {
            return response()->json([
                'message' => 'لازم توفري بريد إلكتروني أو رقم هاتف على الأقل.',
            ], 422);
        }

        $user = User::create([
            'tenant_id' => $tenant->id,

            'name' => $validated['name'],

            'email' => $validated['email']
                ?? null,

            'phone' => $validated['phone']
                ?? null,

            'password' => $validated['password'],

            'role' => 'customer',

            'status' => 'active',
        ]);

        $token =
            $user->createToken(
                'mobile'
            )->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * Login
     */
    public function login(
        Request $request
    ): JsonResponse {
        $tenant =
            $this->resolveTenant(
                $request
            );

        $validated =
            $request->validate([
                'login' => [
                    'required',
                    'string',
                ],

                'password' => [
                    'required',
                    'string',
                ],
            ]);

        $login = trim($validated['login']);

        $user = User::where('tenant_id', $tenant->id)
            ->where(function ($query) use ($login) {
                $query
                    ->where('email', strtolower($login))
                    ->orWhere('phone', $login);
            })
            ->first();

        if (
            ! $user ||
            ! Hash::check(
                $validated['password'],
                $user->password
            )
        ) {
            return response()->json([
                'message' => 'بيانات الدخول غير صحيحة.',
            ], 401);
        }

        if (
            $user->status !==
            'active'
        ) {
            return response()->json([
                'message' => 'الحساب غير مفعّل أو موقوف حالياً.',
            ], 403);
        }

        $user
            ->forceFill([
                'last_login_at' => now(),
            ])
            ->save();

        $token =
            $user->createToken(
                'mobile'
            )->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function platformLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::whereNull('tenant_id')
            ->where('role', 'platform_admin')
            ->where(function ($query) use ($validated) {
                $query->where('email', $validated['login'])
                    ->orWhere('phone', $validated['login']);
            })
            ->first();

        if (
            ! $user ||
            ! Hash::check($validated['password'], $user->password)
        ) {
            return response()->json([
                'message' => 'بيانات الدخول غير صحيحة.',
            ], 401);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'الحساب غير مفعّل أو موقوف حالياً.',
            ], 403);
        }

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        $token = $user->createToken('platform-admin')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * forgotPassword
     */
    public function forgotPassword(
        Request $request
    ): JsonResponse {
        $tenant =
            $this->resolveTenant(
                $request
            );

        $validated =
            $request->validate([
                'email' => [
                    'required',
                    'email',
                    'max:255',
                ],
            ]);

        $email =
            strtolower(
                trim(
                    $validated['email']
                )
            );

        $user =
            User::where(
                'tenant_id',
                $tenant->id
            )
                ->where(
                    'email',
                    $email
                )
                ->first();

        /*
         * Important:
         * Same response even if email
         * does not exist.
         *
         * Prevents account enumeration.
         */
        if (! $user) {
            return response()->json([
                'message' => 'If an account exists for this email, a verification code has been sent.',
            ]);
        }

        if (
            $user->status !==
            'active'
        ) {
            return response()->json([
                'message' => 'If an account exists for this email, a verification code has been sent.',
            ]);
        }

        $code = DB::transaction(function () use ($tenant, $email) {
            $lockedUser = User::where('tenant_id', $tenant->id)
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            if (! $lockedUser || $lockedUser->status !== 'active') {
                return null;
            }

            $recentRequest = DB::table('password_reset_codes')
                ->where('tenant_id', $tenant->id)
                ->where('email', $email)
                ->where('created_at', '>=', now()->subSeconds(60))
                ->exists();

            if ($recentRequest) {
                return null;
            }

            DB::table('password_reset_codes')
                ->where('tenant_id', $tenant->id)
                ->where('email', $email)
                ->delete();

            $newCode = (string) random_int(100000, 999999);

            DB::table('password_reset_codes')->insert([
                'tenant_id' => $tenant->id,
                'email' => $email,
                'code_hash' => Hash::make($newCode),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(10),
                'verified_at' => null,
                'reset_token_hash' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $newCode;
        });

        if ($code === null) {
            return response()->json([
                'message' => 'If an account exists for this email, a verification code has been sent.',
            ]);
        }

        try {
            Mail::raw(
                "Your Vendo password reset code is: {$code}\n\n"
                ."This code expires in 10 minutes.\n\n"
                .'If you did not request this password reset, you can ignore this email.',
                function ($message) use ($email) {
                    $message
                        ->to($email)
                        ->subject(
                            'Vendo Password Reset Code'
                        );
                }
            );
        } catch (\Throwable $e) {
            /*
             * Delete the code if
             * email delivery failed.
             */
            DB::table(
                'password_reset_codes'
            )
                ->where(
                    'tenant_id',
                    $tenant->id
                )
                ->where(
                    'email',
                    $email
                )
                ->delete();

            report($e);

            return response()->json([
                'message' => 'Unable to send the verification code right now. Please try again.',
            ], 500);
        }

        return response()->json([
            'message' => 'If an account exists for this email, a verification code has been sent.',
        ]);
    }

    /**
     * Verify reset OTP
     */
    public function verifyResetCode(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $email = strtolower(trim($validated['email']));

        $result = DB::transaction(function () use (
            $tenant,
            $email,
            $validated
        ) {
            $reset = DB::table('password_reset_codes')
                ->where('tenant_id', $tenant->id)
                ->where('email', $email)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $reset) {
                return ['message' => 'Invalid or expired verification code.', 'status' => 422];
            }

            if (now()->greaterThan($reset->expires_at)) {
                DB::table('password_reset_codes')
                    ->where('id', $reset->id)
                    ->delete();

                return ['message' => 'The verification code has expired.', 'status' => 422];
            }

            if ($reset->verified_at !== null) {
                return ['message' => 'This verification code has already been used.', 'status' => 422];
            }

            if ($reset->attempts >= 5) {
                return ['message' => 'Too many incorrect attempts. Please request a new code.', 'status' => 429];
            }

            if (! Hash::check($validated['code'], $reset->code_hash)) {
                DB::table('password_reset_codes')
                    ->where('id', $reset->id)
                    ->increment('attempts');

                return ['message' => 'Invalid verification code.', 'status' => 422];
            }

            $resetToken = Str::random(64);

            DB::table('password_reset_codes')
                ->where('id', $reset->id)
                ->update([
                    'verified_at' => now(),
                    'reset_token_hash' => Hash::make($resetToken),
                    'updated_at' => now(),
                ]);

            return [
                'message' => 'Verification successful.',
                'reset_token' => $resetToken,
                'status' => 200,
            ];
        });

        $status = $result['status'];
        unset($result['status']);

        return response()->json($result, $status);
    }

    /**
     * Reset password
     */
    public function resetPassword(
        Request $request
    ): JsonResponse {
        $tenant = $this->resolveTenant($request);

        $validated = $request->validate([
            'email' => [
                'required',
                'email',
            ],
            'reset_token' => [
                'required',
                'string',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        $email = strtolower(trim($validated['email']));

        $result = DB::transaction(function () use (
            $tenant,
            $email,
            $validated
        ) {
            $reset = DB::table('password_reset_codes')
                ->where('tenant_id', $tenant->id)
                ->where('email', $email)
                ->whereNotNull('verified_at')
                ->whereNotNull('reset_token_hash')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $reset) {
                return [
                    'message' => 'Invalid password reset request.',
                    'status' => 422,
                ];
            }

            if (now()->greaterThan($reset->expires_at)) {
                DB::table('password_reset_codes')
                    ->where('id', $reset->id)
                    ->delete();

                return [
                    'message' => 'Password reset session has expired.',
                    'status' => 422,
                ];
            }

            if (! Hash::check(
                $validated['reset_token'],
                $reset->reset_token_hash
            )) {
                return [
                    'message' => 'Invalid password reset token.',
                    'status' => 422,
                ];
            }

            $user = User::where('tenant_id', $tenant->id)
                ->where('email', $email)
                ->first();

            if (! $user) {
                return [
                    'message' => 'Invalid password reset request.',
                    'status' => 422,
                ];
            }

            $user->update([
                'password' => $validated['password'],
            ]);

            // Logout all existing sessions after password reset.
            $user->tokens()->delete();

            DB::table('password_reset_codes')
                ->where('id', $reset->id)
                ->delete();

            return [
                'message' => 'Password reset successfully. You can now sign in with your new password.',
                'status' => 200,
            ];
        });

        return response()->json([
            'message' => $result['message'],
        ], $result['status']);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $normalized = [];

        if ($request->exists('email')) {
            $normalized['email'] = $request->filled('email')
                ? strtolower(trim($request->input('email')))
                : null;
        }

        if ($request->exists('phone')) {
            $normalized['phone'] = $request->filled('phone')
                ? trim($request->input('phone'))
                : null;
        }

        $request->merge($normalized);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->where('tenant_id', $user->tenant_id)
                    ->ignore($user->id),
            ],

            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
                Rule::unique('users', 'phone')
                    ->where('tenant_id', $user->tenant_id)
                    ->ignore($user->id),
            ],
        ]);

        $email = array_key_exists('email', $validated)
            ? (filled($validated['email'])
                ? strtolower(trim($validated['email']))
                : null)
            : $user->email;

        $phone = array_key_exists('phone', $validated)
            ? (filled($validated['phone'])
                ? trim($validated['phone'])
                : null)
            : $user->phone;

        if (empty($email) && empty($phone)) {
            return response()->json([
                'message' => 'Email or phone is required.',
            ], 422);
        }

        $user->update([
            'name' => trim($validated['name']),
            'email' => $email,
            'phone' => $phone,
        ]);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user->fresh()->load('tenant'),
        ]);
    }

    /**
     * Logout current token
     */
    public function logout(
        Request $request
    ): JsonResponse {
        $request
            ->user()
            ->currentAccessToken()
            ->delete();

        return response()->json([
            'message' => 'تم تسجيل الخروج بنجاح.',
        ]);
    }

    /**
     * Current user
     */
    public function me(
        Request $request
    ): JsonResponse {
        return response()->json([
            'user' => $request
                ->user()
                ->load(
                    'tenant'
                ),
        ]);
    }

    /**
     * Resolve tenant
     */
    private function resolveTenant(Request $request): Tenant
    {
        $request->validate([
            'tenant_id' => [
                'required_without:tenant_slug',
                'integer',
                'exists:tenants,id',
            ],
            'tenant_slug' => [
                'required_without:tenant_id',
                'string',
                'exists:tenants,slug',
            ],
        ]);

        $tenant = $request->filled('tenant_id')
            ? Tenant::find($request->input('tenant_id'))
            : Tenant::where('slug', $request->input('tenant_slug'))->first();

        if (! $tenant || $tenant->status !== 'active') {
            abort(403, 'This company is not active.');
        }

        return $tenant;
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => [
                'required',
                'string',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        if (! Hash::check(
            $validated['current_password'],
            $user->password
        )) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        if (Hash::check(
            $validated['password'],
            $user->password
        )) {
            return response()->json([
                'message' => 'New password must be different from the current password.',
            ], 422);
        }

        $user->update([
            'password' => $validated['password'],
        ]);

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }
}

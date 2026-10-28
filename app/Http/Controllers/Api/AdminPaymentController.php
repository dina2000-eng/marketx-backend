<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AdminPaymentController extends Controller
{
    public function refund(
        Request $request,
        Payment $payment,
        PaymentService $paymentService
    ) {
        /*
         * Validate refund request.
         */
        $data = $request->validate([
            'reason' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        /*
         * Load order before checking access.
         */
        $payment->loadMissing(
            'order'
        );

        if (! $payment->order) {
            throw ValidationException::withMessages([
                'refund' =>
                    'الطلب المرتبط بهذه الدفعة غير موجود.',
            ]);
        }

        /*
         * Tenant admin can refund only
         * orders inside their tenant.
         */
        if (
            $request->user()->tenant_id !== null &&
            (int) $payment->order->tenant_id !==
            (int) $request->user()->tenant_id
        ) {
            abort(
                403,
                'ليس لديك صلاحية لاسترداد هذه الدفعة.'
            );
        }

        try {
            $payment =
                $paymentService
                    ->refundPayment(
                        $payment,
                        null,
                        $data['reason']
                            ?? null
                    );
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'refund' =>
                    $e->getMessage(),
            ]);
        }

        /*
         * Get the refund record that
         * was created by PaymentService.
         */
        $refund =
            $payment
                ->refunds()
                ->latest()
                ->first();

        return response()->json([
            'message' =>
                'تم تسجيل استرداد الدفعة بنجاح.',

            'payment' =>
                $payment->fresh(),

            'refund' =>
                $refund,
        ]);
    }
}
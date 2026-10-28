<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function fakeSuccess(
        Request $request,
        Payment $payment,
        PaymentService $paymentService
    ): JsonResponse {
        abort_unless(
            app()->environment([
                'local',
                'testing',
            ]),
            404
        );

        abort_unless(
            (int) $payment->user_id ===
                (int) $request->user()->id,
            404
        );

        $payment =
            $paymentService->markPaid(
                $payment,
                'FAKE-TXN-' . $payment->id
            );

        return response()->json([
            'message' =>
                'Fake payment completed successfully.',
            'payment' =>
                $payment,
        ]);
    }

    public function fakeFailure(
        Request $request,
        Payment $payment,
        PaymentService $paymentService
    ): JsonResponse {
        abort_unless(
            app()->environment([
                'local',
                'testing',
            ]),
            404
        );

        abort_unless(
            (int) $payment->user_id ===
                (int) $request->user()->id,
            404
        );

        $payment =
            $paymentService->markFailed(
                $payment
            );

        return response()->json([
            'message' =>
                'Fake payment failed and reservation was released.',
            'payment' =>
                $payment,
        ]);
    }
}
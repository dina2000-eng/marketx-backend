<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayResolver;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaymentWebhookController
    extends Controller
{
    public function handle(
        Request $request,
        string $gateway,
        PaymentGatewayResolver $resolver,
        PaymentService $paymentService
    ) {
        try {
            $paymentGateway =
                $resolver->resolveByName(
                    $gateway
                );

            /*
             * Real gateway signature
             * verification will happen
             * inside verify().
             */
            $result =
                $paymentGateway->verify(
                    $request->all()
                );

            $gatewayReference =
                $result[
                    'gateway_reference'
                ] ?? null;

            if (! $gatewayReference) {
                throw new RuntimeException(
                    'Gateway reference is missing.'
                );
            }

            $payment =
                Payment::query()
                    ->where(
                        'gateway',
                        $gateway
                    )
                    ->where(
                        'gateway_reference',
                        $gatewayReference
                    )
                    ->firstOrFail();

            $status =
                $result['status']
                ?? null;

            if ($status === 'paid') {
                $paymentService
                    ->markPaid(
                        $payment,
                        $result[
                            'transaction_id'
                        ] ?? null
                    );
            } elseif (
                $status === 'failed'
            ) {
                $paymentService
                    ->markFailed(
                        $payment
                    );
            } else {
                throw new RuntimeException(
                    'Unsupported payment status.'
                );
            }

            Log::info(
                'PAYMENT WEBHOOK PROCESSED',
                [
                    'gateway' =>
                        $gateway,

                    'payment_id' =>
                        $payment->id,

                    'status' =>
                        $status,
                ]
            );

            return response()->json([
                'received' =>
                    true,
            ]);
        } catch (\Throwable $e) {
            Log::error(
                'PAYMENT WEBHOOK FAILED',
                [
                    'gateway' =>
                        $gateway,

                    'error' =>
                        $e->getMessage(),
                ]
            );

            return response()->json([
                'received' =>
                    false,

                'message' =>
                    $e->getMessage(),
            ], 400);
        }
    }
}
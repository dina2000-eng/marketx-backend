<?php

namespace App\Services\Payments\Gateways;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Support\Str;
use RuntimeException;

class FakePaymentGateway
    implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'fake';
    }

    public function supports(
        string $countryCode,
        string $currency
    ): bool {
        return
            app()->environment([
                'local',
                'testing',
            ]) &&
            config(
                'payments.fake_enabled',
                false
            );
    }

    public function initiate(
        Order $order,
        Payment $payment
    ): array {
        if (
            ! app()->environment([
                'local',
                'testing',
            ]) ||
            ! config(
                'payments.fake_enabled',
                false
            )
        ) {
            throw new RuntimeException(
                'Fake payment gateway is disabled.'
            );
        }

        $reference =
            'FAKE-' .
            strtoupper(
                Str::random(16)
            );

        $payment->update([
            'gateway' =>
                $this->name(),

            'gateway_reference' =>
                $reference,

            'status' =>
                'pending',
        ]);

        return [
            'gateway' =>
                $this->name(),

            'gateway_reference' =>
                $reference,

            'status' =>
                'pending',

            'checkout_url' =>
                null,

            'client_secret' =>
                null,
        ];
    }

    public function verify(
        array $payload
    ): array {
        if (
            ! app()->environment([
                'local',
                'testing',
            ]) ||
            ! config(
                'payments.fake_enabled',
                false
            )
        ) {
            throw new RuntimeException(
                'Fake payment gateway is disabled.'
            );
        }

        $status =
            $payload['status']
            ?? null;

        if (
            ! in_array(
                $status,
                [
                    'paid',
                    'failed',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Invalid fake webhook status.'
            );
        }

        $gatewayReference =
            $payload[
                'gateway_reference'
            ] ?? null;

        if (! $gatewayReference) {
            throw new RuntimeException(
                'Gateway reference is required.'
            );
        }

        return [
            'status' =>
                $status,

            'gateway_reference' =>
                $gatewayReference,

            'transaction_id' =>
                $payload[
                    'transaction_id'
                ] ?? null,
        ];
    }
}
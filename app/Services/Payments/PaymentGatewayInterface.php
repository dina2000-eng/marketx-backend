<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;

interface PaymentGatewayInterface
{
    public function name(): string;

    public function supports(
        string $countryCode,
        string $currency
    ): bool;

    public function initiate(
        Order $order,
        Payment $payment
    ): array;

    public function verify(
        array $payload
    ): array;
}
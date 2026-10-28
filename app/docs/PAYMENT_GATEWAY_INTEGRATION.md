# Vendo Payment Gateway Integration

Vendo uses a gateway-independent payment architecture.

A buyer can integrate any payment provider without modifying
the marketplace checkout, commission, inventory, wallet,
payout, or order logic.

## Payment Flow

1. Customer creates an order.
2. Vendo reserves inventory.
3. Payment gateway is initialized.
4. Customer completes payment.
5. Gateway sends a webhook to Vendo.
6. Vendo verifies the webhook.
7. Payment is marked as paid or failed.
8. Inventory reservation is finalized or released.
9. Vendor earnings are funded.
10. Vendor earnings become available after delivery.

## Adding a New Gateway

Create a gateway class:

```php
<?php

namespace App\Services\Payments\Gateways;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayInterface;

class CustomPaymentGateway
    implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'custom';
    }

    public function supports(
        string $countryCode,
        string $currency
    ): bool {
        return true;
    }

    public function initiate(
        Order $order,
        Payment $payment
    ): array {
        // Call provider API here.

        return [
            'gateway' => $this->name(),
            'gateway_reference' => null,
            'status' => 'pending',
            'checkout_url' => null,
            'client_secret' => null,
        ];
    }

    public function verify(
        array $payload
    ): array {
        // Verify provider webhook signature here.

        return [
            'status' => 'paid',
            'gateway_reference' => null,
            'transaction_id' => null,
        ];
    }
}
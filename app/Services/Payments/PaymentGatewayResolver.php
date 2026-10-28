<?php

namespace App\Services\Payments;

use App\Services\Payments\Gateways\FakePaymentGateway;

use RuntimeException;

class PaymentGatewayResolver
{
    /**
     * Registered payment gateways.
     */
    private function gateways(): array
    {
        return [
            app(
                FakePaymentGateway::class
            ),
        
        ];
    }

    /**
     * Resolve gateway by country
     * and currency.
     */
    public function resolve(
        string $countryCode,
        string $currency
    ): PaymentGatewayInterface {
        $countryCode =
            strtoupper($countryCode);

        $currency =
            strtoupper($currency);

        foreach (
            $this->gateways()
            as $gateway
        ) {
            if (
                $gateway->supports(
                    $countryCode,
                    $currency
                )
            ) {
                return $gateway;
            }
        }

        throw new RuntimeException(
            "No payment gateway is available for {$countryCode} / {$currency}."
        );
    }

    /**
     * Resolve gateway by its name.
     *
     * Used mainly by webhooks.
     */
    public function resolveByName(
        string $name
    ): PaymentGatewayInterface {
        $name =
            strtolower(
                trim($name)
            );

        foreach (
            $this->gateways()
            as $gateway
        ) {
            if (
                strtolower(
                    $gateway->name()
                ) === $name
            ) {
                return $gateway;
            }
        }

        throw new RuntimeException(
            "Payment gateway [{$name}] is not registered."
        );
    }

}
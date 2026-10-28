<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Console\Command;
use Throwable;

class ExpirePendingPayments extends Command
{
    protected $signature =
        'payments:expire';

    protected $description =
        'Expire pending online payments and release reserved inventory';

    public function handle(
        PaymentService $paymentService
    ): int {
        $expiredPayments =
            Payment::query()
                ->where(
                    'status',
                    'pending'
                )
                ->where(
                    'method',
                    'online'
                )
                ->whereNotNull(
                    'expires_at'
                )
                ->where(
                    'expires_at',
                    '<=',
                    now()
                )
                ->orderBy('id')
                ->get();

        if ($expiredPayments->isEmpty()) {
            $this->info(
                'No expired payments found.'
            );

            return self::SUCCESS;
        }

        $expiredCount = 0;
        $failedCount = 0;

        foreach (
            $expiredPayments
            as $payment
        ) {
            try {
                $paymentService->expire(
                    $payment
                );

                $expiredCount++;
            } catch (Throwable $e) {
                $failedCount++;

                report($e);

                $this->error(
                    'Failed to expire payment #' .
                    $payment->id .
                    ': ' .
                    $e->getMessage()
                );
            }
        }

        $this->info(
            "Expired payments: {$expiredCount}"
        );

        if ($failedCount > 0) {
            $this->warn(
                "Failed payments: {$failedCount}"
            );
        }

        return self::SUCCESS;
    }
}
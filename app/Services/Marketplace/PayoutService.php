<?php

namespace App\Services\Marketplace;

use App\Models\Payout;
use App\Models\SellerWallet;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PayoutService
{
    public function request(
        Store $store,
        float $amount,
        string $currency,
        string $method,
        array $destinationDetails = []
    ): Payout {
        return DB::transaction(function () use (
            $store,
            $amount,
            $currency,
            $method,
            $destinationDetails
        ) {
            $currency = strtoupper($currency);

            if ($amount <= 0) {
                throw new RuntimeException(
                    'Payout amount must be greater than zero.'
                );
            }

            $wallet = SellerWallet::query()
                ->where('store_id', $store->id)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                (float) $wallet->available_balance <
                $amount
            ) {
                throw new RuntimeException(
                    'Insufficient available balance.'
                );
            }

            /*
             * Reserve withdrawal amount.
             */
            $wallet->decrement(
                'available_balance',
                $amount
            );

            return Payout::create([
                'store_id' =>
                    $store->id,

                'amount' =>
                    $amount,

                'currency' =>
                    $currency,

                'method' =>
                    $method,

                'status' =>
                    'pending',

                'destination_details' =>
                    $destinationDetails,

                'requested_at' =>
                    now(),
            ]);
        });
    }

    public function markProcessing(
        Payout $payout
    ): Payout {
        return DB::transaction(function () use (
            $payout
        ) {
            $payout = Payout::query()
                ->whereKey($payout->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payout->status === 'processing') {
                return $payout;
            }

            if ($payout->status !== 'pending') {
                throw new RuntimeException(
                    'Only pending payouts can be processed.'
                );
            }

            $payout->update([
                'status' =>
                    'processing',

                'processed_at' =>
                    now(),
            ]);

            return $payout->fresh();
        });
    }

    public function markPaid(
        Payout $payout,
        ?string $reference = null
    ): Payout {
        return DB::transaction(function () use (
            $payout,
            $reference
        ) {
            $payout = Payout::query()
                ->whereKey($payout->id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Idempotency.
             */
            if ($payout->status === 'paid') {
                return $payout;
            }

            if (
                ! in_array(
                    $payout->status,
                    [
                        'pending',
                        'processing',
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'This payout cannot be marked as paid.'
                );
            }

            $wallet = SellerWallet::query()
                ->where(
                    'store_id',
                    $payout->store_id
                )
                ->where(
                    'currency',
                    $payout->currency
                )
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Money actually left Vendo.
             */
            $wallet->increment(
                'total_withdrawn',
                $payout->amount
            );

            $payout->update([
                'status' =>
                    'paid',

                'reference' =>
                    $reference
                    ?? $payout->reference,

                'processed_at' =>
                    $payout->processed_at
                    ?? now(),

                'paid_at' =>
                    now(),

                'failed_at' =>
                    null,
            ]);

            return $payout->fresh();
        });
    }

    public function markFailed(
        Payout $payout
    ): Payout {
        return $this->returnFunds(
            $payout,
            'failed'
        );
    }

    public function cancel(
        Payout $payout
    ): Payout {
        return $this->returnFunds(
            $payout,
            'cancelled'
        );
    }

    private function returnFunds(
        Payout $payout,
        string $status
    ): Payout {
        return DB::transaction(function () use (
            $payout,
            $status
        ) {
            $payout = Payout::query()
                ->whereKey($payout->id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Never refund wallet twice.
             */
            if (
                in_array(
                    $payout->status,
                    [
                        'failed',
                        'cancelled',
                    ],
                    true
                )
            ) {
                return $payout;
            }

            if ($payout->status === 'paid') {
                throw new RuntimeException(
                    'Paid payout cannot be reversed here.'
                );
            }

            $wallet = SellerWallet::query()
                ->where(
                    'store_id',
                    $payout->store_id
                )
                ->where(
                    'currency',
                    $payout->currency
                )
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Return reserved withdrawal
             * back to seller.
             */
            $wallet->increment(
                'available_balance',
                $payout->amount
            );

            $payout->update([
                'status' =>
                    $status,

                'failed_at' =>
                    $status === 'failed'
                        ? now()
                        : null,
            ]);

            return $payout->fresh();
        });
    }
}
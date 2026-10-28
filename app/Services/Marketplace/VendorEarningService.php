<?php

namespace App\Services\Marketplace;

use App\Models\Order;
use App\Models\SellerWallet;
use App\Models\VendorEarning;
use App\Models\VendorOrder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VendorEarningService
{
    public function createForVendorOrder(
        VendorOrder $vendorOrder
    ): VendorEarning {
        return DB::transaction(function () use (
            $vendorOrder
        ) {
            $existing =
                VendorEarning::query()
                    ->where(
                        'vendor_order_id',
                        $vendorOrder->id
                    )
                    ->lockForUpdate()
                    ->first();

            if ($existing) {
                return $existing;
            }

            $vendorOrder->loadMissing([
                'store',
                'order',
            ]);

            $commissionPct =
                $vendorOrder
                    ->store
                    ->default_commission_pct;

            if ($commissionPct === null) {
                $commissionPct =
                    config(
                        'marketplace.default_commission_pct',
                        10
                    );
            }

            $gross =
                (float)
                $vendorOrder->total_amount;

            $commissionAmount =
                round(
                    $gross *
                    ((float) $commissionPct / 100),
                    2
                );

            $netAmount =
                round(
                    $gross -
                    $commissionAmount,
                    2
                );

            return VendorEarning::create([
                'vendor_order_id' =>
                    $vendorOrder->id,

                'store_id' =>
                    $vendorOrder->store_id,

                'gross_amount' =>
                    $gross,

                'commission_pct' =>
                    $commissionPct,

                'commission_amount' =>
                    $commissionAmount,

                'net_amount' =>
                    $netAmount,

                'currency' =>
                    strtoupper(
                        $vendorOrder
                            ->order
                            ->currency
                    ),

                'status' =>
                    'pending',
            ]);
        });
    }

    public function fundOrder(
    Order $order
): void {
    $order->loadMissing(
        'vendorOrders'
    );

    foreach (
        $order->vendorOrders
        as $vendorOrder
    ) {
        $this->fundVendorOrder(
            $vendorOrder
        );
    }
}

    public function makeAvailable(
        VendorOrder $vendorOrder
    ): VendorEarning {
        return DB::transaction(function () use (
            $vendorOrder
        ) {
            $earning =
                VendorEarning::query()
                    ->where(
                        'vendor_order_id',
                        $vendorOrder->id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            if (
                $earning->status ===
                'available' ||
                $earning->status ===
                'paid'
            ) {
                return $earning;
            }

            if (
                $earning->status !==
                'funded'
            ) {
                throw new RuntimeException(
                    'Vendor earning is not funded yet.'
                );
            }

            $wallet =
                SellerWallet::query()
                    ->where(
                        'store_id',
                        $earning->store_id
                    )
                    ->where(
                        'currency',
                        $earning->currency
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            if (
                (float)
                $wallet->pending_balance <
                (float)
                $earning->net_amount
            ) {
                throw new RuntimeException(
                    'Seller pending balance is insufficient.'
                );
            }

            $wallet->decrement(
                'pending_balance',
                $earning->net_amount
            );

            $wallet->increment(
                'available_balance',
                $earning->net_amount
            );

            $wallet->increment(
                'total_earned',
                $earning->net_amount
            );

            $earning->update([
                'status' =>
                    'available',

                'available_at' =>
                    now(),
            ]);

            return $earning->fresh();
        });
    }

    public function reverse(
        VendorOrder $vendorOrder
    ): VendorEarning {
        return DB::transaction(function () use (
            $vendorOrder
        ) {
            $earning =
                VendorEarning::query()
                    ->where(
                        'vendor_order_id',
                        $vendorOrder->id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            if (
                $earning->status ===
                'reversed'
            ) {
                return $earning;
            }

            /*
             * If funds were already placed
             * in pending wallet balance,
             * remove them.
             */
            if (
                $earning->status ===
                'funded'
            ) {
                $wallet =
                    SellerWallet::query()
                        ->where(
                            'store_id',
                            $earning->store_id
                        )
                        ->where(
                            'currency',
                            $earning->currency
                        )
                        ->lockForUpdate()
                        ->first();

                if ($wallet) {
                    $amount = min(
                        (float)
                        $wallet->pending_balance,
                        (float)
                        $earning->net_amount
                    );

                    if ($amount > 0) {
                        $wallet->decrement(
                            'pending_balance',
                            $amount
                        );
                    }
                }
            }

            if (
                in_array(
                    $earning->status,
                    [
                        'available',
                        'paid',
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'Available or paid earnings require a refund/chargeback flow.'
                );
            }

            $earning->update([
                'status' =>
                    'reversed',
            ]);

            return $earning->fresh();
        });
    }

    public function fundVendorOrder(
    VendorOrder $vendorOrder
): VendorEarning {
    return DB::transaction(function () use (
        $vendorOrder
    ) {
        $earning =
            VendorEarning::query()
                ->where(
                    'vendor_order_id',
                    $vendorOrder->id
                )
                ->lockForUpdate()
                ->first();

        if (! $earning) {
            $earning =
                $this->createForVendorOrder(
                    $vendorOrder
                );
        }

        /*
         * Never fund twice.
         */
        if (
            in_array(
                $earning->status,
                [
                    'funded',
                    'available',
                    'paid',
                ],
                true
            )
        ) {
            return $earning;
        }

        if ($earning->status === 'reversed') {
            throw new RuntimeException(
                'Reversed earning cannot be funded.'
            );
        }

        $wallet =
            SellerWallet::query()
                ->where(
                    'store_id',
                    $earning->store_id
                )
                ->where(
                    'currency',
                    $earning->currency
                )
                ->lockForUpdate()
                ->first();

        if (! $wallet) {
            $wallet =
                SellerWallet::create([
                    'store_id' =>
                        $earning->store_id,

                    'currency' =>
                        $earning->currency,

                    'pending_balance' => 0,
                    'available_balance' => 0,
                    'total_earned' => 0,
                    'total_withdrawn' => 0,
                ]);
        }

        $wallet->increment(
            'pending_balance',
            $earning->net_amount
        );

        $earning->update([
            'status' => 'funded',
        ]);

        return $earning->fresh();
    });
}

public function refundVendorOrder(
    VendorOrder $vendorOrder
): VendorEarning {
    return DB::transaction(function () use (
        $vendorOrder
    ) {
        $earning = VendorEarning::query()
            ->where(
                'vendor_order_id',
                $vendorOrder->id
            )
            ->lockForUpdate()
            ->first();

        if (! $earning) {
            return $this
                ->createForVendorOrder(
                    $vendorOrder
                );
        }

        /*
         * Already reversed.
         */
        if ($earning->status === 'reversed') {
            return $earning;
        }

        /*
         * Money was never funded.
         */
        if ($earning->status === 'pending') {
            $earning->update([
                'status' =>
                    'reversed',
            ]);

            return $earning->fresh();
        }

        $wallet = SellerWallet::query()
            ->where(
                'store_id',
                $earning->store_id
            )
            ->where(
                'currency',
                $earning->currency
            )
            ->lockForUpdate()
            ->firstOrFail();

        /*
         * Seller money is still pending.
         */
        if ($earning->status === 'funded') {
            if (
                (float) $wallet->pending_balance <
                (float) $earning->net_amount
            ) {
                throw new RuntimeException(
                    'Seller pending balance is insufficient for refund.'
                );
            }

            $wallet->decrement(
                'pending_balance',
                $earning->net_amount
            );

            $earning->update([
                'status' =>
                    'reversed',
            ]);

            return $earning->fresh();
        }

        /*
         * Seller money became withdrawable.
         */
        if ($earning->status === 'available') {
            if (
                (float) $wallet->available_balance <
                (float) $earning->net_amount
            ) {
                throw new RuntimeException(
                    'Seller balance was already withdrawn. Manual refund reconciliation is required.'
                );
            }

            $wallet->decrement(
                'available_balance',
                $earning->net_amount
            );

            $wallet->decrement(
                'total_earned',
                $earning->net_amount
            );

            $earning->update([
                'status' =>
                    'reversed',
            ]);

            return $earning->fresh();
        }

        /*
         * Do not automatically claw money
         * back after seller payout.
         */
        if ($earning->status === 'paid') {
            throw new RuntimeException(
                'Seller earning has already been paid out.'
            );
        }

        throw new RuntimeException(
            'Unsupported earning status for refund.'
        );
    });
}
}
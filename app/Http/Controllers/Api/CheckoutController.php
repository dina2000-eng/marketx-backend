<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\VendorOrder;
use App\Services\Marketplace\VendorEarningService;
use App\Services\Payments\PaymentGatewayResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function checkout(
        Request $request,
        PaymentGatewayResolver $gatewayResolver,
        VendorEarningService $earningService
    ) {
        $user = $request->user();

        $data = $request->validate([
            'address_id' => [
                'nullable',
                'integer',
            ],

            'shipping_address' => [
                'nullable',
                'string',
                'max:1000',
                'required_without:address_id',
            ],

            'country_code' => [
                'nullable',
                'string',
                'size:2',
                'required_without:address_id',
            ],

            'payment_method' => [
                'required',
                'string',
                'in:cod,online',
            ],
        ]);

        /*
         * Resolve shipping information.
         */
        $shippingAddress =
            $data['shipping_address'] ?? null;

        $countryCode =
            isset($data['country_code'])
                ? strtoupper($data['country_code'])
                : null;

        if (!empty($data['address_id'])) {
            $address = Address::query()
                ->where('id', $data['address_id'])
                ->where('user_id', $user->id)
                ->where(
                    'tenant_id',
                    $user->tenant_id
                )
                ->firstOrFail();

            if (!$address->country_code) {
                throw ValidationException::withMessages([
                    'address_id' =>
                        'Please update the selected address and choose a country.',
                ]);
            }

            $shippingAddress =
                $address->formattedAddress();

            $countryCode =
                strtoupper(
                    $address->country_code
                );
        }

        return DB::transaction(
            function () use (
                $user,
                $data,
                $shippingAddress,
                $countryCode,
                $gatewayResolver,
                $earningService
            ) {
                /*
                 * Lock active cart.
                 */
                $cart = Cart::query()
                    ->where(
                        'tenant_id',
                        $user->tenant_id
                    )
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->where(
                        'status',
                        'active'
                    )
                    ->lockForUpdate()
                    ->first();

                if (!$cart) {
                    throw ValidationException::withMessages([
                        'cart' =>
                            'السلة غير موجودة.',
                    ]);
                }

                $cart->load([
                    'items.product.store',
                    'items.variant',
                ]);

                if ($cart->items->isEmpty()) {
                    throw ValidationException::withMessages([
                        'cart' =>
                            'السلة فارغة.',
                    ]);
                }

                /*
                 * Validate products and prepare
                 * inventory records.
                 *
                 * Inventory is the source of truth.
                 *
                 * For legacy products that were created
                 * before the inventory system existed,
                 * automatically create the missing
                 * Inventory record from variant.stock.
                 */
                $inventories = collect();

                foreach ($cart->items as $item) {
                    $product =
                        $item->product;

                    /*
                     * Product must still exist
                     * and be approved.
                     */
                    if (
                        !$product ||
                        $product->status !==
                            'approved'
                    ) {
                        throw ValidationException::withMessages([
                            'cart' =>
                                'أحد المنتجات لم يعد متاحاً.',
                        ]);
                    }

                    /*
                     * Store must be active.
                     */
                    if (
                        !$product->store ||
                        $product->store->status !==
                            'active'
                    ) {
                        throw ValidationException::withMessages([
                            'cart' =>
                                "المتجر الخاص بالمنتج {$product->name} غير متاح حالياً.",
                        ]);
                    }

                    /*
                     * Tenant isolation.
                     */
                    if (
                        $user->tenant_id !== null &&
                        (int) $product->tenant_id !==
                            (int) $user->tenant_id
                    ) {
                        throw ValidationException::withMessages([
                            'cart' =>
                                'أحد المنتجات غير متاح في هذه المنصة.',
                        ]);
                    }

                    /*
                     * Variant must exist.
                     */
                    if (!$item->variant) {
                        throw ValidationException::withMessages([
                            'cart' =>
                                "خيار المنتج {$product->name} غير موجود.",
                        ]);
                    }

                    $variant =
                        $item->variant;

                    /*
                     * Variant must belong
                     * to this product.
                     */
                    if (
                        (int) $variant->product_id !==
                        (int) $product->id
                    ) {
                        throw ValidationException::withMessages([
                            'cart' =>
                                'بيانات أحد المنتجات في السلة غير صحيحة.',
                        ]);
                    }

                    /*
                     * Variant must be active.
                     */
                    if (!$variant->is_active) {
                        throw ValidationException::withMessages([
                            'cart' =>
                                "أحد خيارات المنتج {$product->name} لم يعد متاحاً.",
                        ]);
                    }

                    /*
                     * Lock Inventory if it exists.
                     */
                    $inventory =
                        Inventory::query()
                            ->where(
                                'product_variant_id',
                                $variant->id
                            )
                            ->where(
                                'store_id',
                                $product->store_id
                            )
                            ->lockForUpdate()
                            ->first();

                    /*
                     * Legacy/self-healing support.
                     *
                     * Some older variants may have
                     * stock but no Inventory record.
                     */
                    if (!$inventory) {
                        $inventory =
                            Inventory::create([
                                'store_id' =>
                                    $product->store_id,

                                'product_variant_id' =>
                                    $variant->id,

                                'quantity' =>
                                    max(
                                        0,
                                        (int) $variant->stock
                                    ),

                                'reserved_quantity' =>
                                    0,

                                'low_stock_threshold' =>
                                    5,
                            ]);
                    }

                    /*
                     * Keep Inventory available for
                     * the order creation phase.
                     */
                    $inventories->put(
                        $variant->id,
                        $inventory
                    );

                    /*
                     * Available stock =
                     * physical quantity - reserved.
                     */
                    $availableQuantity =
                        $inventory
                            ->availableQuantity();

                    if (
                        $availableQuantity <
                        $item->quantity
                    ) {
                        $name =
                            $product->has_variants
                                ? $variant->name
                                : $product->name;

                        throw ValidationException::withMessages([
                            'cart' =>
                                "الكمية المطلوبة غير متوفرة للمنتج {$name}.",
                        ]);
                    }
                }

                /*
                 * One checkout = one currency.
                 */
                $currencies =
                    $cart->items
                        ->map(
                            fn ($item) =>
                                strtoupper(
                                    $item
                                        ->product
                                        ->currency
                                )
                        )
                        ->unique();

                if (
                    $currencies->count() !== 1
                ) {
                    throw ValidationException::withMessages([
                        'cart' =>
                            'لا يمكن إنشاء طلب يحتوي على أكثر من عملة.',
                    ]);
                }

                /*
                 * Calculate current total
                 * using current variant prices.
                 */
                $total = 0;

                foreach (
                    $cart->items
                    as $item
                ) {
                    $currentPrice =
                        (float)
                        $item
                            ->variant
                            ->price;

                    $total +=
                        $currentPrice *
                        $item->quantity;
                }

                $currency =
                    strtoupper(
                        $currencies->first()
                    );

                $isOnline =
                    $data['payment_method']
                    === 'online';

                /*
                 * Parent Order.
                 */
                $order = Order::create([
                    'tenant_id' =>
                        $user->tenant_id,

                    'user_id' =>
                        $user->id,

                    'status' =>
                        'pending',

                    'total_amount' =>
                        $total,

                    'currency' =>
                        $currency,

                    'payment_method' =>
                        $data['payment_method'],

                    'payment_status' =>
                        $isOnline
                            ? 'pending'
                            : 'unpaid',

                    'shipping_address' =>
                        $shippingAddress,

                    'country_code' =>
                        $countryCode,
                ]);

                /*
                 * Payment attempt.
                 *
                 * Online payment expires
                 * automatically after 30 minutes.
                 */
                $payment =
                    Payment::create([
                        'order_id' =>
                            $order->id,

                        'user_id' =>
                            $user->id,

                        'method' =>
                            $data[
                                'payment_method'
                            ],

                        'gateway' =>
                            null,

                        'status' =>
                            $isOnline
                                ? 'pending'
                                : 'unpaid',

                        'amount' =>
                            $total,

                        'currency' =>
                            $currency,

                        'expires_at' =>
                            $isOnline
                                ? now()
                                    ->addMinutes(30)
                                : null,

                        'idempotency_key' =>
                            (string)
                            Str::uuid(),
                    ]);

                /*
                 * Main order items.
                 */
                $orderItems = [];

                foreach (
                    $cart->items
                    as $item
                ) {
                    $product =
                        $item->product;

                    $variant =
                        $item->variant;

                    $inventory =
                        $inventories->get(
                            $variant->id
                        );

                    if (!$inventory) {
                        throw ValidationException::withMessages([
                            'cart' =>
                                "بيانات المخزون للمنتج {$product->name} غير متوفرة.",
                        ]);
                    }

                    $currentPrice =
                        (float)
                        $variant->price;

                    $lineTotal =
                        $currentPrice *
                        $item->quantity;

                    $orderItem =
                        $order
                            ->items()
                            ->create([
                                'product_id' =>
                                    $product->id,

                                'store_id' =>
                                    $product
                                        ->store_id,

                                'product_variant_id' =>
                                    $variant->id,

                                'product_name' =>
                                    $product->name,

                                'variant_name' =>
                                    $product
                                        ->has_variants
                                        ? $variant->name
                                        : null,

                                'quantity' =>
                                    $item->quantity,

                                'price' =>
                                    $currentPrice,

                                'total' =>
                                    $lineTotal,
                            ]);

                    $orderItems[
                        $item->id
                    ] = $orderItem;

                    /*
                     * ONLINE PAYMENT
                     *
                     * Do NOT deduct physical stock.
                     * Reserve it until payment
                     * succeeds or expires.
                     */
                    if ($isOnline) {
                        $inventory->increment(
                            'reserved_quantity',
                            $item->quantity
                        );

                        continue;
                    }

                    /*
                     * CASH ON DELIVERY
                     *
                     * Stock is immediately sold.
                     */
                    $inventory->decrement(
                        'quantity',
                        $item->quantity
                    );

                    InventoryTransaction::create([
                        'inventory_id' =>
                            $inventory->id,

                        'type' =>
                            'sale',

                        'quantity' =>
                            -$item->quantity,

                        'note' =>
                            'Checkout order #' .
                            $order->id,
                    ]);

                    /*
                     * Keep old variant.stock
                     * synchronized for now.
                     */
                    $inventory->refresh();

                    $variant->update([
                        'stock' =>
                            $inventory->quantity,
                    ]);
                }

                /*
                 * Split parent order
                 * into vendor orders.
                 */
                $itemsByStore =
                    $cart->items
                        ->groupBy(
                            fn ($item) =>
                                $item
                                    ->product
                                    ->store_id
                        );

                foreach (
                    $itemsByStore
                    as $storeId => $items
                ) {
                    $vendorTotal =
                        $items->sum(
                            fn ($item) =>
                                (float)
                                $item
                                    ->variant
                                    ->price *
                                $item->quantity
                        );

                    $vendorOrder =
                        VendorOrder::create([
                            'order_id' =>
                                $order->id,

                            'store_id' =>
                                $storeId,

                            'status' =>
                                'pending',

                            'total_amount' =>
                                $vendorTotal,
                        ]);

                    /*
                     * Initial seller earning.
                     */
                    $earningService
                        ->createForVendorOrder(
                            $vendorOrder
                        );

                    /*
                     * Vendor order items.
                     */
                    foreach (
                        $items
                        as $item
                    ) {
                        $currentPrice =
                            (float)
                            $item
                                ->variant
                                ->price;

                        $vendorOrder
                            ->items()
                            ->create([
                                'order_item_id' =>
                                    $orderItems[
                                        $item->id
                                    ]->id,

                                'product_variant_id' =>
                                    $item
                                        ->variant_id,

                                'quantity' =>
                                    $item->quantity,

                                'price' =>
                                    $currentPrice,

                                'total' =>
                                    $currentPrice *
                                    $item->quantity,
                            ]);
                    }
                }

                /*
                 * Initialize payment gateway
                 * only for online orders.
                 */
                $gatewayData = null;

                if ($isOnline) {
                    $gateway =
                        $gatewayResolver
                            ->resolve(
                                $countryCode,
                                $currency
                            );

                    $gatewayData =
                        $gateway
                            ->initiate(
                                $order,
                                $payment
                            );

                    /*
                     * Gateway may update:
                     * gateway,
                     * gateway_reference,
                     * metadata, etc.
                     */
                    $payment->refresh();
                }

                /*
                 * Remove cart items only after
                 * the whole checkout succeeded.
                 */
                $cart
                    ->items()
                    ->delete();

                return response()->json([
                    'message' =>
                        $isOnline
                            ? 'تم إنشاء الطلب وحجز المخزون، ويمكن الآن متابعة الدفع.'
                            : 'تم إنشاء الطلب بنجاح.',

                    'requires_payment' =>
                        $isOnline,

                    'order' =>
                        $order->load([
                            'items.variant',
                            'vendorOrders.store',
                        ]),

                    'payment' =>
                        $payment,

                    'gateway' =>
                        $gatewayData,
                ], 201);
            }
        );
    }
}
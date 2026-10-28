<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorInventoryTransactionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'inventory_id' => [
                'required',
                'integer',
                'exists:inventories,id',
            ],

            'type' => [
                'required',
                'in:purchase,sale,return,adjustment',
            ],

            /*
             * adjustment may be negative.
             * Other transaction types must be positive.
             */
            'quantity' => [
                'required',
                'integer',
                'not_in:0',
            ],

            'note' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        /*
         * purchase / sale / return must
         * always receive positive quantity.
         */
        if (
            $data['type'] !== 'adjustment' &&
            $data['quantity'] < 1
        ) {
            throw ValidationException::withMessages([
                'quantity' =>
                    'الكمية يجب أن تكون أكبر من صفر.',
            ]);
        }

        $transaction = DB::transaction(
            function () use (
                $request,
                $data
            ) {
                /*
                 * Lock inventory row to prevent
                 * simultaneous stock updates.
                 */
                $inventory = Inventory::query()
                    ->whereKey(
                        $data['inventory_id']
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                 * Vendor can modify only inventory
                 * belonging to one of his stores.
                 */
                $ownsInventory =
                    $request->user()
                        ->ownedStores()
                        ->where(
                            'id',
                            $inventory->store_id
                        )
                        ->exists();

                if (!$ownsInventory) {
                    abort(
                        403,
                        'غير مصرح لك بتعديل هذا المخزون.'
                    );
                }

                $quantity =
                    (int) $data['quantity'];

                /*
                 * Convert transaction quantity
                 * into actual inventory delta.
                 */
                switch ($data['type']) {
                    case 'sale':
                        $delta =
                            -abs($quantity);
                        break;

                    case 'purchase':
                    case 'return':
                        $delta =
                            abs($quantity);
                        break;

                    case 'adjustment':
                        /*
                         * Adjustment can be:
                         * +5 or -5
                         */
                        $delta =
                            $quantity;
                        break;

                    default:
                        $delta = 0;
                }

                $newQuantity =
                    $inventory->quantity +
                    $delta;

                /*
                 * Never allow physical quantity
                 * to become negative.
                 */
                if ($newQuantity < 0) {
                    throw ValidationException::withMessages([
                        'quantity' =>
                            'الكمية المطلوبة أكبر من المخزون المتوفر.',
                    ]);
                }

                /*
                 * quantity must never become
                 * lower than reserved quantity.
                 */
                if (
                    $newQuantity <
                    $inventory->reserved_quantity
                ) {
                    throw ValidationException::withMessages([
                        'quantity' =>
                            'لا يمكن خفض المخزون لأقل من الكمية المحجوزة.',
                    ]);
                }

                /*
                 * For sale, explicitly check
                 * available quantity.
                 */
                if (
                    $data['type'] === 'sale' &&
                    abs($quantity) >
                    $inventory->availableQuantity()
                ) {
                    throw ValidationException::withMessages([
                        'quantity' =>
                            'الكمية المطلوبة غير متوفرة.',
                    ]);
                }

                /*
                 * Update main stock source.
                 */
                $inventory->update([
                    'quantity' =>
                        $newQuantity,
                ]);

                /*
                 * Keep legacy ProductVariant.stock
                 * synchronized temporarily.
                 */
                $inventory->variant()
                    ->update([
                        'stock' =>
                            $newQuantity,
                    ]);

                /*
                 * Save transaction.
                 *
                 * sale       => negative
                 * purchase   => positive
                 * return     => positive
                 * adjustment => signed value
                 */
                return InventoryTransaction::create([
                    'inventory_id' =>
                        $inventory->id,

                    'type' =>
                        $data['type'],

                    'quantity' =>
                        $delta,

                    'note' =>
                        $data['note'] ?? null,
                ]);
            }
        );

        return response()->json([
            'message' =>
                'تم تحديث المخزون بنجاح.',

            'transaction' =>
                $transaction,

            'inventory' =>
                $transaction
                    ->inventory()
                    ->with('variant')
                    ->first(),
        ]);
    }

    public function index(
        Request $request,
        Inventory $inventory
    ) {
        /*
         * Prevent vendor from viewing
         * another store's inventory history.
         */
        $ownsInventory =
            $request->user()
                ->ownedStores()
                ->where(
                    'id',
                    $inventory->store_id
                )
                ->exists();

        if (!$ownsInventory) {
            return response()->json([
                'message' =>
                    'غير مصرح لك بعرض سجل هذا المخزون.',
            ], 403);
        }

        return response()->json(
            $inventory->transactions()
                ->latest()
                ->get()
        );
    }
}
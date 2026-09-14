<?php

use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\VendorOrderController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\AdminProductController;
use App\Http\Controllers\Api\AdminStoreController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\AdminVendorRequestController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\VendorRequestController;
use App\Http\Controllers\Api\VendorProductController;
use App\Http\Controllers\Api\PublicProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/products', [PublicProductController::class, 'index']);
Route::get('/products/{product}', [PublicProductController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::get('/products', [PublicProductController::class, 'index']);
Route::get('/products/{product}', [PublicProductController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/vendor-requests', [VendorRequestController::class, 'store']);
    Route::get('/vendor-requests/my-request', [VendorRequestController::class, 'myRequest']);
                                         
    Route::post('/stores', [StoreController::class, 'store']);
    Route::get('/stores/my-store', [StoreController::class, 'myStore']);

   Route::get('/cart', [CartController::class, 'index']);
   Route::post('/cart/items', [CartController::class, 'addItem']);
   Route::put('/cart/items/{item}', [CartController::class, 'updateItem']);
   Route::delete('/cart/items/{item}', [CartController::class, 'removeItem']);

   Route::post('/checkout', [CheckoutController::class, 'checkout']);
   Route::get('/orders', [OrderController::class, 'index']);
   Route::get('/orders/{order}', [OrderController::class, 'show']);
   Route::get('/vendor/orders', [VendorOrderController::class, 'index']);
   Route::put('/vendor/orders/{vendorOrder}/status', [VendorOrderController::class, 'updateStatus']);

    Route::prefix('vendor/products')->group(function () {
        Route::get('/', [VendorProductController::class, 'index']);
        Route::post('/', [VendorProductController::class, 'store']);
        Route::put('/{product}', [VendorProductController::class, 'update']);
        Route::delete('/{product}', [VendorProductController::class, 'destroy']);
    });
});

Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::get('/vendor-requests', [AdminVendorRequestController::class, 'index']);
    Route::post('/vendor-requests/{vendorRequest}/approve', [AdminVendorRequestController::class, 'approve']);
    Route::post('/vendor-requests/{vendorRequest}/reject', [AdminVendorRequestController::class, 'reject']);
    Route::get('/stores', [AdminStoreController::class, 'index']);
    Route::post('/stores/{store}/approve', [AdminStoreController::class, 'approve']);
    Route::post('/stores/{store}/reject', [AdminStoreController::class, 'reject']);
    Route::post('/stores/{store}/suspend', [AdminStoreController::class, 'suspend']);
    Route::get('/products/pending', [AdminProductController::class, 'pending']);
    Route::post('/products/{product}/approve', [AdminProductController::class, 'approve']);
    Route::post('/products/{product}/reject', [AdminProductController::class, 'reject']);
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::put('/orders/{order}/status', [AdminOrderController::class, 'updateStatus']);
});


<?php

use App\Http\Controllers\AdminWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::redirect('/login', '/admin/login')->name('login');

Route::get('/admin/login', [AdminWebController::class, 'showLogin'])
    ->name('admin.login');

Route::post('/admin/login', [AdminWebController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('admin.login.submit');

Route::middleware(['auth:web', 'platform.admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminWebController::class, 'dashboard'])
        ->name('dashboard');

    Route::post('/logout', [AdminWebController::class, 'logout'])
        ->name('logout');

    Route::get('/products', [AdminWebController::class, 'products'])
        ->name('products');

    Route::post('/products/{product}/approve', [AdminWebController::class, 'approveProduct'])
        ->name('products.approve');

    Route::post('/products/{product}/reject', [AdminWebController::class, 'rejectProduct'])
        ->name('products.reject');

    Route::post('/vendor-requests/{vendorRequest}/approve', [AdminWebController::class, 'approveVendorRequest'])
    ->name('vendor-requests.approve');

    Route::post('/vendor-requests/{vendorRequest}/reject', [AdminWebController::class, 'rejectVendorRequest'])
        ->name('vendor-requests.reject');

    Route::get('/stores', [AdminWebController::class, 'stores'])
    ->name('stores');

Route::post('/stores/{store}/approve', [AdminWebController::class, 'approveStore'])
    ->name('stores.approve');

Route::post('/stores/{store}/reject', [AdminWebController::class, 'rejectStore'])
    ->name('stores.reject');

Route::post('/stores/{store}/suspend', [AdminWebController::class, 'suspendStore'])
    ->name('stores.suspend');

    Route::get('/orders', [AdminWebController::class, 'orders'])
    ->name('orders');

Route::post('/orders/{order}/status', [AdminWebController::class, 'updateOrderStatus'])
    ->name('orders.status');

    Route::get('/categories', [AdminWebController::class, 'categories'])
    ->name('categories');

Route::post('/categories/{category}/toggle-status', [AdminWebController::class, 'toggleCategoryStatus'])
    ->name('categories.toggle-status');

Route::delete('/categories/{category}', [AdminWebController::class, 'deleteCategory'])
    ->name('categories.delete');

Route::get('/payouts', [AdminWebController::class, 'payouts'])
        ->name('payouts');

Route::post('/payouts/{payout}/status', [AdminWebController::class, 'updatePayoutStatus'])
        ->name('payouts.status');

Route::get('/vendor-requests', [AdminWebController::class, 'vendorRequests'])
    ->name('vendor-requests');
});

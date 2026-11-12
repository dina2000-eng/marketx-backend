
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
    ->name('admin.login.submit');

Route::middleware(['auth:web', 'platform.admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminWebController::class, 'dashboard'])
        ->name('dashboard');

    Route::post('/logout', [AdminWebController::class, 'logout'])
        ->name('logout');

    Route::post('/products/{product}/approve', [AdminWebController::class, 'approveProduct'])
        ->name('products.approve');

    Route::post('/products/{product}/reject', [AdminWebController::class, 'rejectProduct'])
        ->name('products.reject');
});

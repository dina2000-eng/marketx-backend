<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PublicProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\VendorRequestController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\VendorOrderController;
use App\Http\Controllers\Api\VendorCategoryController;
use App\Http\Controllers\Api\VendorProductController;
use App\Http\Controllers\Api\VendorProductImageController;
use App\Http\Controllers\Api\VendorProductVariantController;
use App\Http\Controllers\Api\VendorAttributeController;
use App\Http\Controllers\Api\VendorAttributeValueController;
use App\Http\Controllers\Api\VendorInventoryController;
use App\Http\Controllers\Api\VendorInventoryTransactionController;
use App\Http\Controllers\Api\VendorInventoryDashboardController;
use App\Http\Controllers\Api\AdminVendorRequestController;
use App\Http\Controllers\Api\AdminStoreController;
use App\Http\Controllers\Api\AdminProductController;
use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\AdminCategoryController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\VendorPayoutController;
use App\Http\Controllers\Api\AdminPayoutController;
use App\Http\Controllers\Api\VendorWalletController;
use App\Http\Controllers\Api\AdminPaymentController;
use App\Http\Controllers\Api\PaymentWebhookController;

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {

    Route::post(
        '/register',
        [AuthController::class, 'register']
    );

    Route::post(
        '/login',
        [AuthController::class, 'login']
    );

    Route::post(
    '/forgot-password',
    [
        AuthController::class,
        'forgotPassword'
    ])->middleware(
    'throttle:5,1'
);

Route::post(
    '/verify-reset-code',
    [
        AuthController::class,
        'verifyResetCode'
    ]
)->middleware(
    'throttle:10,1'
);

Route::post(
    '/reset-password',
    [
        AuthController::class,
        'resetPassword'
    ]
)->middleware(
    'throttle:5,1'
);

    Route::middleware('auth:sanctum')->group(function () {

    Route::put(
    '/profile',
    [
        AuthController::class,
        'updateProfile'
    ]
);
   Route::put(
    '/change-password',
    [
        AuthController::class,
        'changePassword'
    ]
 );

        Route::post(
            '/logout',
            [AuthController::class, 'logout']
        );

        Route::get(
            '/me',
            [AuthController::class, 'me']
        );
    });
});

Route::post(
    '/payments/webhooks/{gateway}',
    [PaymentWebhookController::class, 'handle']
);

/*
|--------------------------------------------------------------------------
| Public Marketplace
|--------------------------------------------------------------------------
*/

Route::get(
    '/products',
    [PublicProductController::class, 'index']
);

Route::get(
    '/products/{product}',
    [PublicProductController::class, 'show']
);

Route::get(
    '/categories',
    [CategoryController::class, 'index']
);


/*
|--------------------------------------------------------------------------
| Authenticated User Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Vendor Request
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/vendor-requests',
        [VendorRequestController::class, 'store']
    );

    Route::get(
        '/vendor-requests/my-request',
        [VendorRequestController::class, 'myRequest']
    );

    /*
    |-----------------------------------------------------------------------|
    | Adresses 
    |-----------------------------------------------------------------------|
    */
    Route::get(
    '/addresses',
    [AddressController::class, 'index']
);

Route::post(
    '/addresses',
    [AddressController::class, 'store']
);

Route::put(
    '/addresses/{address}',
    [AddressController::class, 'update']
);

Route::delete(
    '/addresses/{address}',
    [AddressController::class, 'destroy']
);

Route::post(
    '/addresses/{address}/default',
    [AddressController::class, 'makeDefault']
);

/* 
|------------------------------------------------------------------------|
| Payments
|------------------------------------------------------------------------|
*/

if (app()->environment([
    'local',
    'testing',
])) {
    Route::post(
        '/payments/{payment}/fake-success',
        [
            PaymentController::class,
            'fakeSuccess',
        ]
    );

    Route::post(
        '/payments/{payment}/fake-failure',
        [
            PaymentController::class,
            'fakeFailure',
        ]
    );
}

    /*
|--------------------------------------------------------------------------
| Wishlist
|--------------------------------------------------------------------------
*/

Route::get(
    '/wishlist',
    [WishlistController::class, 'index']
);

Route::post(
    '/wishlist/{product}',
    [WishlistController::class, 'store']
);

Route::delete(
    '/wishlist/{product}',
    [WishlistController::class, 'destroy']
);



    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/stores',
        [StoreController::class, 'store']
    );

    Route::get(
        '/stores/my-store',
        [StoreController::class, 'myStore']
    );

    Route::put(
        '/stores/my-store',
        [StoreController::class, 'update']
    );


    /*
    |--------------------------------------------------------------------------
    | Cart
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/cart',
        [CartController::class, 'index']
    );

    Route::post(
        '/cart/items',
        [CartController::class, 'addItem']
    );

    Route::put(
        '/cart/items/{item}',
        [CartController::class, 'updateItem']
    );

    Route::delete(
        '/cart/items/{item}',
        [CartController::class, 'removeItem']
    );


    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/checkout',
        [CheckoutController::class, 'checkout']
    );


    /*
    |--------------------------------------------------------------------------
    | Customer Orders
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/orders',
        [OrderController::class, 'index']
    );

    Route::get(
        '/orders/{order}',
        [OrderController::class, 'show']
    );

    Route::post(
        '/orders/{order}/cancel',
        [OrderController::class, 'cancel']
    );


    Route::middleware( 'role:vendor_owner')->group(function () {
        Route::get(
    '/vendor/wallet',
    [VendorWalletController::class, 'index']
);
    /*
    |--------------------------------------------------------------------------
    | Vendor Orders
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/vendor/orders',
        [VendorOrderController::class, 'index']
    );

    Route::put(
        '/vendor/orders/{vendorOrder}/status',
        [VendorOrderController::class, 'updateStatus']
    );


    /*
    |--------------------------------------------------------------------------
    | Vendor Categories
    |--------------------------------------------------------------------------
    */

    Route::prefix('vendor/categories')->group(function () {

        Route::get(
            '/',
            [VendorCategoryController::class, 'index']
        );

        Route::post(
            '/',
            [VendorCategoryController::class, 'store']
        );

        Route::put(
            '/{category}',
            [VendorCategoryController::class, 'update']
        );

        Route::delete(
            '/{category}',
            [VendorCategoryController::class, 'destroy']
        );
    });


    /*
    |--------------------------------------------------------------------------
    | Vendor Product Images
    |--------------------------------------------------------------------------
    */

    Route::prefix(
        'vendor/products/{product}/images'
    )->group(function () {

        Route::get(
            '/',
            [VendorProductImageController::class, 'index']
        );

        Route::post(
            '/',
            [VendorProductImageController::class, 'store']
        );
    });

    Route::delete(
        '/vendor/product-images/{image}',
        [VendorProductImageController::class, 'destroy']
    );

    Route::post(
        '/vendor/product-images/{image}/main',
        [VendorProductImageController::class, 'makeMain']
    );


    /*
    |--------------------------------------------------------------------------
    | Vendor Products
    |--------------------------------------------------------------------------
    */

    Route::prefix('vendor/products')->group(function () {

        Route::get(
            '/',
            [VendorProductController::class, 'index']
        );

        Route::post(
            '/',
            [VendorProductController::class, 'store']
        );

        Route::put(
            '/{product}',
            [VendorProductController::class, 'update']
        );

        Route::delete(
            '/{product}',
            [VendorProductController::class, 'destroy']
        );
    });


    /*
    |--------------------------------------------------------------------------
    | Vendor Product Variants
    |--------------------------------------------------------------------------
    */

    Route::prefix( 'vendor/products/{product}/variants'
    )->group(function () {

        Route::get(
            '/',
            [VendorProductVariantController::class, 'index']
        );

        Route::post(
            '/',
            [VendorProductVariantController::class, 'store']
        );
    });

    Route::put(
        '/vendor/variants/{variant}',
        [VendorProductVariantController::class, 'update']
    );

    Route::delete(
        '/vendor/variants/{variant}',
        [VendorProductVariantController::class, 'destroy']
    );

    Route::middleware([ 'auth:sanctum', 'role:vendor_owner'])->group(function () {
        Route::get(
    '/vendor/payouts',
    [VendorPayoutController::class, 'index']
);

Route::post(
    '/vendor/payouts',
    [VendorPayoutController::class, 'store']
);
 });

    /*
    |--------------------------------------------------------------------------
    | Vendor Attributes
    |--------------------------------------------------------------------------
    */

    Route::prefix(
        'vendor/attributes'
    )->group(function () {

        Route::get(
            '/',
            [VendorAttributeController::class, 'index']
        );

        Route::post(
            '/',
            [VendorAttributeController::class, 'store']
        );

        Route::delete(
            '/{attribute}',
            [VendorAttributeController::class, 'destroy']
        );
    });


    /*
    |--------------------------------------------------------------------------
    | Vendor Attribute Values
    |--------------------------------------------------------------------------
    */

    Route::prefix(
        'vendor/attributes/{attribute}/values'
    )->group(function () {

        Route::get(
            '/',
            [VendorAttributeValueController::class, 'index']
        );

        Route::post(
            '/',
            [VendorAttributeValueController::class, 'store']
        );
    });

    Route::delete(
        '/vendor/attribute-values/{value}',
        [VendorAttributeValueController::class, 'destroy']
    );


    /*
    |--------------------------------------------------------------------------
    | Vendor Inventory
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/vendor/inventory',
        [VendorInventoryController::class, 'index']
    );

    Route::post(
        '/vendor/inventory/transactions',
        [VendorInventoryTransactionController::class, 'store']
    );

    Route::get(
        '/vendor/inventory/{inventory}/transactions',
        [VendorInventoryTransactionController::class, 'index']
    );

    Route::get(
        '/vendor/inventory/dashboard',
        [VendorInventoryDashboardController::class, 'index']
    );
    });


    /*
    |------------------------------------------------------------------------|
    | Notifications 
    |------------------------------------------------------------------------|
    */
    Route::get(
    '/notifications',
    [NotificationController::class, 'index']
);

Route::get(
    '/notifications/unread-count',
    [NotificationController::class, 'unreadCount']
);

Route::post(
    '/notifications/{id}/read',
    [NotificationController::class, 'markAsRead']
);

Route::post(
    '/notifications/read-all',
    [NotificationController::class, 'markAllAsRead']
);
});


/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/

Route::middleware([ 'auth:sanctum', 'role:tenant_admin,platform_admin',
])
    ->prefix('admin')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/dashboard',
            [AdminDashboardController::class, 'index']
        );


        /*
        |--------------------------------------------------------------------------
        | Vendor Requests
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/vendor-requests',
            [AdminVendorRequestController::class, 'index']
        );

        Route::post(
            '/vendor-requests/{vendorRequest}/approve',
            [AdminVendorRequestController::class, 'approve']
        );

        Route::post(
            '/vendor-requests/{vendorRequest}/reject',
            [AdminVendorRequestController::class, 'reject']
        );


        /*
        |--------------------------------------------------------------------------
        | Stores
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/stores',
            [AdminStoreController::class, 'index']
        );

        Route::post(
            '/stores/{store}/approve',
            [AdminStoreController::class, 'approve']
        );

        Route::post(
            '/stores/{store}/reject',
            [AdminStoreController::class, 'reject']
        );

        Route::post(
            '/stores/{store}/suspend',
            [AdminStoreController::class, 'suspend']
        );


        /*
        |--------------------------------------------------------------------------
        | Products
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/products',
            [AdminProductController::class, 'index']
        );
        Route::get(
            '/products/pending',
            [AdminProductController::class, 'pending']
        );

        Route::post(
            '/products/{product}/approve',
            [AdminProductController::class, 'approve']
        );

        Route::post(
            '/products/{product}/reject',
            [AdminProductController::class, 'reject']
        );


        /*
        |--------------------------------------------------------------------------
        | Orders
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/orders',
            [AdminOrderController::class, 'index']
        );

        Route::put(
            '/orders/{order}/status',
            [AdminOrderController::class, 'updateStatus']
        );


        /*
        |--------------------------------------------------------------------------
        | Categories
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/categories',
            [AdminCategoryController::class, 'index']
        );

        Route::get(
            '/categories/{category}',
            [AdminCategoryController::class, 'show']
        );

        Route::delete(
            '/categories/{category}',
            [AdminCategoryController::class, 'destroy']
        );

        Route::post(
            '/categories/{category}/toggle-status',
            [AdminCategoryController::class, 'toggleStatus']
        );

        Route::middleware([
    'auth:sanctum',
    'role:tenant_admin,platform_admin',
])->group(function () {

    Route::get(
    '/payouts',
    [AdminPayoutController::class, 'index']
);

Route::patch(
    '/payouts/{payout}/status',
    [AdminPayoutController::class, 'updateStatus']
);
});

Route::post(
    '/payments/{payment}/refund',
    [AdminPaymentController::class, 'refund']
);
    });
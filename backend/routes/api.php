<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LazadaAuthController;
use App\Http\Controllers\Api\LazadaOrderController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PickListCommentController;
use App\Http\Controllers\Api\PickListCompletionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\ShopeeAuthController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Support\Facades\Route;

// Public routes
// Registration is disabled — new accounts are created via artisan tinker or direct DB insert
// Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Shopee OAuth callback (public — Shopee redirects here)
Route::get('/shopee/callback', [ShopeeAuthController::class, 'callback'])->middleware('throttle:oauth');
Route::post('/shopee/webhook', [\App\Http\Controllers\Api\ShopeeWebhookController::class, 'handle']);

// Lazada OAuth callback (public — Lazada redirects here after seller authorization)
Route::get('/lazada/callback', [LazadaAuthController::class, 'callback'])->middleware('throttle:oauth');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    // Notifications
    Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{id}', [\App\Http\Controllers\Api\NotificationController::class, 'destroy']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/today-picklist', [DashboardController::class, 'todayPickList']);
    Route::get('/dashboard/pending-picklist', [DashboardController::class, 'pendingPickList']);
    Route::post('/dashboard/picklist-completions', [PickListCompletionController::class, 'store'])->middleware('throttle:mutation');
    Route::delete('/dashboard/picklist-completions', [PickListCompletionController::class, 'destroy'])->middleware('throttle:mutation');
    Route::get('/dashboard/picklist-photos/{completion}', [PickListCompletionController::class, 'photo']);
    Route::get('/dashboard/picklist-comments', [PickListCommentController::class, 'index']);
    Route::post('/dashboard/picklist-comments', [PickListCommentController::class, 'store'])->middleware('throttle:mutation');
    Route::post('/dashboard/picklist-comments/read', [PickListCommentController::class, 'markRead'])->middleware('throttle:mutation');
    Route::delete('/dashboard/picklist-comments/{comment}', [PickListCommentController::class, 'destroy'])->middleware('throttle:mutation');

    // Workspace / team
    Route::get('/workspace', [WorkspaceController::class, 'show']);
    Route::get('/workspace/activity', [WorkspaceController::class, 'activity']);
    Route::post('/workspace/invites', [WorkspaceController::class, 'invite'])->middleware('throttle:mutation');
    Route::post('/workspace/invites/accept', [WorkspaceController::class, 'acceptInvite'])->middleware('throttle:mutation');
    Route::delete('/workspace/members/{memberId}', [WorkspaceController::class, 'removeMember'])->middleware('throttle:mutation');

    // Workspace credentials (owner only)
    Route::get('/workspace/credentials', [WorkspaceCredentialController::class, 'show']);
    Route::put('/workspace/credentials', [WorkspaceCredentialController::class, 'update'])->middleware('throttle:mutation');
    Route::delete('/workspace/credentials', [WorkspaceCredentialController::class, 'destroy'])->middleware('throttle:mutation');

    // Shopee store management
    Route::get('/shopee/auth-url', [ShopeeAuthController::class, 'authUrl'])->middleware('throttle:oauth');
    Route::get('/shopee/stores', [ShopeeAuthController::class, 'stores']);
    Route::post('/shopee/stores/{store}/reauthorize', [ShopeeAuthController::class, 'reauthorize']);
    Route::post('/shopee/stores/{store}/disconnect', [ShopeeAuthController::class, 'disconnect'])->middleware('throttle:mutation');
    Route::post('/shopee/stores/{store}/vacation-mode', [ShopeeAuthController::class, 'setVacationMode'])->middleware('throttle:mutation');
    Route::get('/shopee/stores/{store}/vacation-mode', [ShopeeAuthController::class, 'getVacationMode']);
    Route::delete('/shopee/stores/{store}', [ShopeeAuthController::class, 'unlink'])->middleware('throttle:mutation');

    // Lazada store management
    Route::get('/lazada/auth-url', [LazadaAuthController::class, 'authUrl'])->middleware('throttle:oauth');
    Route::post('/lazada/stores/{store}/reauthorize', [LazadaAuthController::class, 'reauthorize'])->middleware('throttle:oauth');
    Route::post('/lazada/stores/{store}/disconnect', [LazadaAuthController::class, 'disconnect'])->middleware('throttle:mutation');
    Route::delete('/lazada/stores/{store}', [LazadaAuthController::class, 'unlink'])->middleware('throttle:mutation');

    // Products / Stock & Price
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/grouped', [ProductController::class, 'indexGrouped']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::put('/products/{product}/stock', [ProductController::class, 'updateStock'])->middleware('throttle:mutation');
    Route::put('/products/{product}/price', [ProductController::class, 'updatePrice'])->middleware('throttle:mutation');
    Route::put('/products/{product}/stock-price', [ProductController::class, 'updateStockAndPrice'])->middleware('throttle:mutation');
    Route::put('/products/stock-by-sku', [ProductController::class, 'updateStockBySku'])->middleware('throttle:mutation');
    Route::put('/products/price-by-sku', [ProductController::class, 'updatePriceBySku'])->middleware('throttle:mutation');
    Route::post('/products/pull', [ProductController::class, 'pull'])->middleware('throttle:sync');
    Route::post('/products/push', [ProductController::class, 'pushSelected'])->middleware('throttle:sync');
    Route::post('/products/sync', [ProductController::class, 'sync'])->middleware('throttle:sync');

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->middleware('throttle:mutation');
    Route::post('/orders/sync', [OrderController::class, 'sync'])->middleware('throttle:sync');
    Route::post('/orders/shipping-labels', [OrderController::class, 'shippingLabelsBulk']);

    // Lazada Orders (separate from Shopee fulfillment)
    Route::post('/lazada/orders/sync', [LazadaOrderController::class, 'sync'])->middleware('throttle:sync');
    Route::post('/lazada/orders/awb-bulk', [LazadaOrderController::class, 'awbBulk'])->middleware('throttle:mutation');
    Route::post('/lazada/orders/{order}/cancel', [LazadaOrderController::class, 'cancel'])->middleware('throttle:mutation');
    Route::get('/lazada/orders/{order}/fulfillment-options', [LazadaOrderController::class, 'fulfillmentOptions']);
    Route::post('/lazada/orders/{order}/pack', [LazadaOrderController::class, 'pack'])->middleware('throttle:mutation');
    Route::get('/lazada/orders/{order}/awb', [LazadaOrderController::class, 'awb']);
    Route::post('/lazada/orders/{order}/ready-to-ship', [LazadaOrderController::class, 'readyToShip'])->middleware('throttle:mutation');

    // Order Fulfillment (Logistics)
    Route::get('/orders/{order}/shipping-parameters', [OrderController::class, 'shippingParameters']);
    Route::post('/orders/{order}/ship', [OrderController::class, 'ship'])->middleware('throttle:mutation');
    Route::get('/orders/{order}/shipping-label', [OrderController::class, 'shippingLabel']);

    // Promotions (live marketplace — Shopee Discount/Voucher, Lazada Seller Voucher)
    Route::get('/promotions', [PromotionController::class, 'index']);
    Route::get('/promotions/{platform}/{type}/{remoteId}', [PromotionController::class, 'show']);
    Route::post('/shopee/promotions/discounts', [PromotionController::class, 'createShopeeDiscount'])->middleware('throttle:mutation');
    Route::post('/shopee/promotions/discounts/{id}/items', [PromotionController::class, 'addShopeeDiscountItems'])->middleware('throttle:mutation');
    Route::put('/shopee/promotions/discounts/{id}', [PromotionController::class, 'updateShopeeDiscount'])->middleware('throttle:mutation');
    Route::post('/shopee/promotions/vouchers', [PromotionController::class, 'createShopeeVoucher'])->middleware('throttle:mutation');
    Route::put('/shopee/promotions/vouchers/{id}', [PromotionController::class, 'updateShopeeVoucher'])->middleware('throttle:mutation');
    Route::post('/lazada/promotions/vouchers', [PromotionController::class, 'createLazadaVoucher'])->middleware('throttle:mutation');
    Route::put('/lazada/promotions/vouchers/{id}', [PromotionController::class, 'updateLazadaVoucher'])->middleware('throttle:mutation');
    Route::post('/lazada/promotions/vouchers/{id}/activate', [PromotionController::class, 'activateLazadaVoucher'])->middleware('throttle:mutation');
    Route::post('/lazada/promotions/vouchers/{id}/deactivate', [PromotionController::class, 'deactivateLazadaVoucher'])->middleware('throttle:mutation');
    Route::post('/lazada/promotions/vouchers/{id}/skus', [PromotionController::class, 'mutateLazadaVoucherSkus'])->middleware('throttle:mutation');
});

Route::fallback(fn () => response()->json(['message' => 'Endpoint not found.'], 404));

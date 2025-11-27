<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\ShopTypeController;
use Illuminate\Support\Facades\Log;

// ヘルスチェックエンドポイント（ALB用）
Route::get('/health', function () {
    return response('', 200);
});

// テストエンドポイント
Route::get('/test', function () {
    Log::info('Test endpoint called');
    return response()->json(['message' => 'API is working']);
});

Route::post('/auth/mock-login', [AuthController::class, 'mockLogin']);
Route::get('/auth/google/redirect', [AuthController::class, 'googleRedirect']);
Route::get('/auth/google/callback', [AuthController::class, 'googleCallback']);
Route::post('/auth/google/login', [AuthController::class, 'googleLogin']);
Route::post('/auth/x/callback', [AuthController::class, 'xCallback']);
Route::post('/auth/x/login', [AuthController::class, 'xLogin']);
Route::post('/auth/x/refresh', [AuthController::class, 'xRefresh']);
Route::post('/auth/logout', [AuthController::class, 'logout']);

// 記録関連のAPI（認証必須）
Route::middleware([\App\Http\Middleware\AuthenticateUser::class])->group(function () {
    Route::post('/records', [RecordController::class, 'store']);
    Route::get('/records', [RecordController::class, 'index']);
    Route::get('/records/recent-for-chart', [RecordController::class, 'getRecentRecordsForChart']);
    Route::get('/records/overall-rating-statistics', [RecordController::class, 'getOverallRatingStatistics']);
    Route::get('/records/shop-type-statistics', [RecordController::class, 'getShopTypeStatistics']);
    Route::get('/records/shop-names', [RecordController::class, 'getShopNames']);
    Route::get('/records/girl-names', [RecordController::class, 'getGirlNames']);
    Route::get('/records/all-girl-names', [RecordController::class, 'getAllGirlNames']);
    Route::get('/records/shops', [RecordController::class, 'getShops']);
    Route::get('/records/shop-records', [RecordController::class, 'getShopRecords']);
    Route::get('/records/girl-records', [RecordController::class, 'getGirlRecords']);
    Route::put('/records/{id}', [RecordController::class, 'update']);
    Route::delete('/records/{id}', [RecordController::class, 'destroy']);
    Route::post('/records/{id}/publish', [RecordController::class, 'publish']);
    Route::delete('/records/{id}/publish', [RecordController::class, 'unpublish']);
    Route::get('/records/{id}/public-url', [RecordController::class, 'getPublicUrl']);
    Route::get('/shops', [\App\Http\Controllers\ShopController::class, 'getShop']);
    Route::post('/shops', [\App\Http\Controllers\ShopController::class, 'createOrUpdateShop']);
    Route::get('/girls', [\App\Http\Controllers\GirlController::class, 'getGirl']);
    Route::get('/girls/list', [\App\Http\Controllers\GirlController::class, 'getGirlList']);
    Route::post('/girls', [\App\Http\Controllers\GirlController::class, 'createOrUpdateGirl']);
    Route::get('/url-title', [\App\Http\Controllers\GirlController::class, 'getUrlTitle']);
    // お店の種類関連のAPI（認証必須：ユーザー別の並び順を適用するため）
    Route::get('/shop-types', [ShopTypeController::class, 'index']);
});


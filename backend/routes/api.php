<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\ShopTypeController;
use Illuminate\Support\Facades\Log;

// テストエンドポイント
Route::get('/test', function () {
    Log::info('Test endpoint called');
    return response()->json(['message' => 'API is working']);
});

Route::post('/auth/mock-login', [AuthController::class, 'mockLogin']);
Route::get('/auth/google/redirect', [AuthController::class, 'googleRedirect']);
Route::get('/auth/google/callback', [AuthController::class, 'googleCallback']);
Route::post('/auth/google/login', [AuthController::class, 'googleLogin']);
Route::post('/auth/logout', [AuthController::class, 'logout']);

// 記録関連のAPI
Route::post('/records', [RecordController::class, 'store']);
Route::get('/records', [RecordController::class, 'index']);
Route::put('/records/{id}', [RecordController::class, 'update']);
Route::delete('/records/{id}', [RecordController::class, 'destroy']);
Route::get('/records/shop-names', [RecordController::class, 'getShopNames']);
Route::get('/records/girl-names', [RecordController::class, 'getGirlNames']);

// お店の種類関連のAPI
Route::get('/shop-types', [ShopTypeController::class, 'index']);


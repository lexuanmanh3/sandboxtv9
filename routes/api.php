<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Frontend\TopupApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| B2B Partner API Ping / Health Check (Mở cho Test Connection của DailyB2B)
|--------------------------------------------------------------------------
*/
Route::match(['GET', 'HEAD', 'OPTIONS'], 'b2b/v1', function () {
    return response()->json([
        'ok' => true,
        'status' => 'OK',
        'message' => 'B2B API Gateway is healthy',
        'time' => now()->toIso8601String(),
    ]);
});

Route::match(['GET', 'HEAD', 'OPTIONS'], 'b2b/v1/ping', function () {
    return response()->json([
        'ok' => true,
        'status' => 'OK',
        'message' => 'pong',
    ]);
});

/*
|--------------------------------------------------------------------------
| B2B Partner API v1 Routes (Protected by HMAC, IP, Throttle)
|--------------------------------------------------------------------------
*/
Route::prefix('b2b/v1')
    ->name('api.b2b.v1.')
    ->middleware(['b2b.hmac', 'b2b.ip', 'b2b.throttle'])
    ->group(function () {
        Route::get('/services', \App\Http\Controllers\Api\B2B\V1\ServiceCatalogController::class . '@index')->name('services');
        Route::get('/credit', \App\Http\Controllers\Api\B2B\V1\CreditController::class . '@index')->name('credit');
        Route::post('/orders', \App\Http\Controllers\Api\B2B\V1\OrderController::class . '@create')->name('orders.create');
        Route::get('/orders/{id}', \App\Http\Controllers\Api\B2B\V1\OrderController::class . '@show')->name('orders.show')->whereNumber('id');
        Route::get('/orders', \App\Http\Controllers\Api\B2B\V1\OrderController::class . '@query')->name('orders.query');
    });

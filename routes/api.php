<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentApiController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Payment API routes
Route::prefix('payment')->group(function () {
    Route::post('/create', [PaymentApiController::class, 'createPayment'])->name('api.payment.create');
    Route::get('/callback', [PaymentApiController::class, 'handleCallback'])->name('api.payment.callback');
    Route::post('/callback', [PaymentApiController::class, 'handleCallback']);
    Route::get('/status', [PaymentApiController::class, 'checkStatus'])->name('api.payment.status');
    Route::post('/webhook', [PaymentApiController::class, 'handleWebhook'])->name('api.payment.webhook');
});

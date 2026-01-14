<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

Route::get('/', function () {
    return view('welcome');
});

// Payment routes
Route::get('/payment', [PaymentController::class, 'create'])->name('payment.create');
Route::post('/payment', [PaymentController::class, 'store'])->name('payment.store');
Route::get('/payment/return', [PaymentController::class, 'handleReturn'])->name('payment.return');
Route::post('/payment/webhook', [PaymentController::class, 'handleWebhook'])->name('payment.webhook');

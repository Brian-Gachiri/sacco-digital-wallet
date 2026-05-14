<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MpesaController;
use App\Http\Controllers\STKPushController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('v1/sacco/register', [AuthController::class, 'register']);
Route::post('v1/sacco/login', [AuthController::class, 'login']);
Route::post('v1/sacco/logout', [AuthController::class, 'logout']);

Route::get('/transactions', [AdminController::class, 'mpesaTransactions'])->middleware('auth:sanctum');
Route::post('/manual/reconciliation', [AdminController::class, 'triggerManualReconciliation'])->middleware('auth:sanctum');

Route::post('v1/sacco/stk/push', [MpesaController::class, 'pay']);
Route::post('v1/sacco/verify/stk/payment', [MpesaController::class, 'verifyPayment']);
Route::match(['get', 'post'], 'v1/sacco/validation', [MpesaController::class, 'mpesaValidation']);
Route::match(['get', 'post'], 'v1/sacco/transaction/confirmation', [MpesaController::class, 'mpesaConfirmation']);
Route::post('v1/sacco/stk/confirmation', [STKPushController::class, 'confirm']);
Route::post('v1/sacco/register/url', [MpesaController::class, 'mpesaRegisterUrls']);
Route::post('v1/sacco/simulate/c2b', [MpesaController::class, 'simulate']);
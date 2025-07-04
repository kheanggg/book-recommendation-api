<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthenticationController;
use App\Http\Controllers\Api\Auth\EmailVerificationController;

Route::get('/test', function () {
    return response()->json(['message' => 'API is222 working!']);
});

// Authentication routes
Route::post('/register', [AuthenticationController::class, 'register']);
Route::post('/login', [AuthenticationController::class, 'login']);
Route::middleware('auth:api')->post('/logout', [AuthenticationController::class, 'logout']);

Route::post('/email/resend', [EmailVerificationController::class, 'resend'])->name('verification.resend')->middleware('signed');
Route::post('/email/verify', [EmailVerificationController::class, 'verifyCode'])->name('verification.verify')->middleware('signed');
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

Route::post('/email/resend', [EmailVerificationController::class, 'resend']);
Route::post('/email/verify', [EmailVerificationController::class, 'verifyCode']);
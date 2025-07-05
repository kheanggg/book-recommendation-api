<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthenticationController;
use App\Http\Controllers\Api\Auth\EmailVerificationController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\TranscriptionController;
use App\Http\Controllers\Api\UserPreferenceController;

Route::get('/test', function () {
    return response()->json(['message' => 'API is222 working!']);
});

// Authentication routes
Route::post('/register', [AuthenticationController::class, 'register']);
Route::post('/login', [AuthenticationController::class, 'login']);
Route::middleware('auth:api')->post('/logout', [AuthenticationController::class, 'logout']);

// Email Verification
Route::post('/email/resend', [EmailVerificationController::class, 'resend'])->name('verification.resend')->middleware('signed');
Route::post('/email/verify', [EmailVerificationController::class, 'verifyCode'])->name('verification.verify')->middleware('signed');

// Book
Route::get('/books', [BookController::class, 'index']);
Route::middleware('auth:api')->get('/books/recommended', [BookController::class, 'booksByTopGenresAndRecentVisits']);
Route::get('/books/{id}', [BookController::class, 'show']);


Route::get('/genres', fn() => \App\Models\Genre::all());

// Voice Search
Route::post('/upload-audio', [TranscriptionController::class, 'uploadAudio']);
Route::post('/start-transcription', [TranscriptionController::class, 'startTranscription']);
Route::get('/get-transcription-result/{jobName}', [TranscriptionController::class, 'getTranscriptionResult']);

// User Preference
Route::middleware('auth:api')->post('/user-preferences', [UserPreferenceController::class, 'store']);
Route::middleware('auth:api')->get('/user-preferences', [UserPreferenceController::class, 'index']);
Route::middleware('auth:api')->post('/genre-interact', [UserPreferenceController::class, 'recordGenreInteraction']);
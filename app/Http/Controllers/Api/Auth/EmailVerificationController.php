<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use App\Notifications\VerifyEmail;
use App\Models\User;
use Carbon\Carbon;

class EmailVerificationController extends Controller
{
    // Handle resend verification code
    public function resend(Request $request, RateLimiter $limiter)
    {
        try {
            // Create a unique key for rate limiting based on the user's IP address
            $key = 'resend-email-'.$request->ip();

            // Check if the user has exceeded the allowed resend attempts (1 attempt per lockout window)
            if ($limiter->tooManyAttempts($key, 1)) {
                $seconds = $limiter->availableIn($key); // Get remaining cooldown time
                return response()->json([
                    'message' => 'Too many resend attempts. Try again later.',
                    'retry_after_seconds' => $seconds,
                ], 429);
            }

            $limiter->hit($key, 30); // 30 seconds lock

            // Validate the request data
            $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            // Find the user record in the database using the provided email
            $user = User::where('email', $request->email)->first();

            // Check if the user's email is already verified
            if ($user->hasVerifiedEmail()) {
                return response()->json(['message' => 'Email already verified.'], 400);
            }

            // Check if the user has reached the maximum number of code attempts (5) and if 10 minutes have not yet passed since the last resend
            if ($user->code_attempts >= 5 && !Carbon::parse($user->last_resend_at)->addMinutes(10)->timezone('Asia/Phnom_Penh')->isPast()) {
                return response()->json(['message' => 'Please wait before requesting again.']);
            }

            // If the last resend time exists and more than 10 minutes have passed since then, reset the resend and code attempt counters to allow the user to try again
            if ($user->last_resend_at && Carbon::parse($user->last_resend_at)->addMinutes(10)->timezone('Asia/Phnom_Penh')->isPast()) {
                $user->resend_attempts = 0;
                $user->code_attempts = 0;
                $user->save();
            }

            // If the user has already attempted to resend the code 5 times, block further attempts and instruct them to wait for 10 minutes
            if ($user->resend_attempts >= 5) {
                return response()->json(['message' => 'You\'ve reached 5 attempted. Try again after 10 minutes.'], 429);
            }

            // Random Code
            $user->last_resend_at = now();
            $code = rand(1000, 9999);
            $user->verification_code = $code;
            $user->verification_code_expires_at = now()->addMinutes(10);
            $user->resend_attempts += 1;
            $user->save();

            // Send verification email
            $user->notify(new VerifyEmail($code));

            return response()->json(['message' => 'Verification link sent!']);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation errors
            return response()->json(['errors' => $e->errors()], 422);

        } catch (\Exception $e) {
            // Handle unexpected errors
            return response()->json([
                'message' => 'An error occurred. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Handle verify code
    public function verifyCode(Request $request)
    {
        // Validate the request data
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string'
        ]);

        // Find the user record in the database using the provided email
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid email.'], 400);
        }
        
        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 400);
        }


        // Check if user has reached the maximum number of code attempts
        if ($user->code_attempts && $user->code_attempts >= 5) {
            return response()->json(['message' => 'You\'ve reached 5 attempts. Try again later.'], 400);
        }

        // If the code is incorrect, increment attempt count and return error
        if ($user->verification_code !== $request->code) {
            $user->code_attempts += 1;
            $user->save();
            return response()->json(['message' => 'Incorrect Code. Please try again.'], 400);
        }
        
        // If the code has expired, increment attempt count and return error
        if ($user->verification_code_expires_at && Carbon::parse($user->verification_code_expires_at)->timezone('Asia/Phnom_Penh')->isPast())
        {
            $user->code_attempts += 1;
            $user->save();
            return response()->json(['message' => 'Verification code has expired.'], 400);
        }

        // Reset verification status, timestamps, and resend attempts after successful email verification
        $user->last_resend_at = now();
        $user->email_verified_at = now();
        $user->verification_code = null;
        $user->verification_code_expires_at = null;
        $user->resend_attempts = 0;
        $user->save();

        $token = $user->createToken('Personal Access Token')->accessToken;

        return response()->json([
            'message' => 'Email verified successfully.',
            'token' => $token,
        ]);
    }
}

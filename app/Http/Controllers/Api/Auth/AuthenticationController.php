<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Notifications\VerifyEmail;
use Carbon\Carbon;


class AuthenticationController extends Controller
{

    private function sendVerificationCode(User $user, $resetResend = false)
    {
        if ($resetResend) {
            $user->resend_attempts = 0;
            $user->code_attempts = 0;
        }

        $code = rand(1000, 9999);
        $user->last_resend_at = now();
        $user->verification_code = $code;
        $user->verification_code_expires_at = now()->addMinutes(10);

        if (!$resetResend) {
            $user->resend_attempts += 1;
        }

        $user->save();

        $user->notify(new VerifyEmail($code));
    }

    // Handle user registration
    public function register(Request $request)
    {
        DB::beginTransaction();   
        try {
            // Validate the request data
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255',
                'password' => 'required|string|min:6|confirmed',
            ], [
                'name.required' => 'Please enter your full name.',
                'email.required' => 'We need your email address.',
                'email.email' => 'Your email address is not valid.',
                'email.unique' => 'This email is already registered.',
                'password.required' => 'Please provide a password.',
                'password.confirmed' => 'The password confirmation does not match.',
            ]);

            // Check if the user already exists
            $user = User::where('email', $request->email)->first();

            if ($user) {
                if ($user->hasVerifiedEmail()) {
                    return response()->json(['message' => 'This email is already registered and verified.'], 400);
                }

                if (($user->code_attempts >= 5 || $user->resend_attempts >= 5) && !Carbon::parse($user->last_resend_at)->addMinutes(10)->timezone('Asia/Phnom_Penh')->isPast()) {
                    $seconds = Carbon::parse($user->last_resend_at)->addMinutes(10)->diffInSeconds(now());
                    return response()->json([
                        'message' => 'Too many attempts. Please wait before trying later.',
                    ], 429);
                }

                // Update password and resend verification
                $user->password = Hash::make($request->password);
                $this->sendVerificationCode($user);

                DB::commit();
                return response()->json([
                    'message' => 'You have already registered but not verified. Please check your email or request a new verification code.'
                ], 409);
            }

            // Create the user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $this->sendVerificationCode($user, true);

            DB::commit();

            return response()->json([
                'message' => 'Verification code sent to your email. Please check your email.'
            ], 201);
        } catch (ValidationException $e) {
            // Rollback if error occurs
            DB::rollBack();

            // Handle validation errors
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            // Rollback if error occurs
            DB::rollBack();

            // Handle other exceptions
            return response()->json([
                'message' => 'Registration failed. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Handle user login
    public function login(Request $request)
    {
        try {
            // Validate the request data
            $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ], [
                'email.required' => 'We need your email address.',
                'email.email' => 'Your email address is not valid.',
                'password.required' => 'Password is required.',
            ]);

            // Attempt to authenticate the user
            if (!Auth::attempt($request->only('email', 'password'))) {
                return response()->json(['message' => 'Wrong email or password.'], 401);
            }

            // Retrieve the currently authenticated user
            $user = Auth::user();

            // Check if the user has verified their email
            if (!$user->hasVerifiedEmail()) {
                return response()->json([
                    'message' => 'You need to verify your email address before logging in.'
                ], 403);
            }

            // If verified, create a personal access token
            $token = $user->createToken('Personal Access Token')->accessToken;

            return response()->json(['token' => $token]);
        } catch (\Illuminate\Validation\ValidationException $e) {

            // Handle validation errors
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {

            // Handle other exceptions
            return response()->json([
                'message' => 'Login failed. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
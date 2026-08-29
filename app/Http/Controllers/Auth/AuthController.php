<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Mail\OtpVerificationMail;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\PasswordResetOtp;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Mail\PasswordResetOtpMail;



class AuthController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    // Register
    // ─────────────────────────────────────────────────────────────────

    public function register(RegisterRequest $request): JsonResponse
    {
        // Check whether this email already exists
        $existingUser = User::where('email', $request->email)->first();

        // Verified account already exists
        if ($existingUser && $existingUser->email_verified_at !== null) {
            return response()->json([
                'message' => 'An account with this email already exists. Please login.',
            ], 409);
        }

        // Reuse existing unverified account
        if ($existingUser) {
            $user = $existingUser;

            $user->update([
                'name'     => $request->name,
                'password' => Hash::make($request->password),
                'phone'    => $request->phone,
            ]);
        } else {
            // Create new account
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
                'phone'    => $request->phone,
                'role'     => User::ROLE_USER,
            ]);
        }

        // Generate 6-digit OTP
        $otp = (string) random_int(100000, 999999);

        // Invalidate all previous OTPs
        OtpCode::where('user_id', $user->id)
            ->where('used', false)
            ->update([
                'used' => true,
            ]);

        // Store new OTP
        OtpCode::create([
            'user_id'    => $user->id,
            'email'      => $user->email,
            'otp'        => $otp,
            'expires_at' => now()->addMinutes(10),
            'used'       => false,
        ]);

        // Send verification email
        try {
            Mail::to($user->email)
                ->send(new OtpVerificationMail($otp));

            Log::info('OTP email sent', [
                'user_id' => $user->id,
                'email'   => $user->email,
            ]);
        } catch (\Throwable $e) {

            Log::error('OTP email failed', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'error'   => $e->getMessage(),
            ]);

            // Invalidate the OTP
            OtpCode::where('user_id', $user->id)
                ->where('otp', $otp)
                ->update([
                    'used' => true,
                ]);

            // Keep the user so registration can be retried
            return response()->json([
                'message' => 'Verification email could not be sent. Please try again.',
            ], 500);
        }

        Log::info('Registration OTP sent', [
            'user_id' => $user->id,
            'email'   => $user->email,
        ]);

        return response()->json([
            'message' => 'Registration successful. Verification code sent to your email.',
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────
    // Verify OTP
    // ─────────────────────────────────────────────────────────────────

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|digits:6',
        ]);

        // Find user
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        // Already verified
        if ($user->email_verified_at !== null) {
            return response()->json([
                'message' => 'Email is already verified.',
            ], 422);
        }

        // Find valid OTP
        $otpCode = OtpCode::where('user_id', $user->id)
            ->where('email', $user->email)
            ->where('otp', $request->otp)
            ->where('used', false)
            ->latest()
            ->first();

        if (! $otpCode) {
            return response()->json([
                'message' => 'Invalid verification code.',
            ], 422);
        }

        // Check expiration
        if ($otpCode->expires_at->isPast()) {
            return response()->json([
                'message' => 'Verification code has expired. Please request a new code.',
            ], 422);
        }

        // Mark OTP as used
        $otpCode->update([
            'used' => true,
        ]);

        // Verify user's email
        $user->update([
            'email_verified_at' => now(),
        ]);

        // Create JWT only after successful verification
        try {
            $token = JWTAuth::fromUser($user);
        } catch (JWTException $e) {
            Log::error('JWT creation failed after OTP verification', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Email verified, but authentication token could not be created.',
            ], 500);
        }

        Log::info('Email verified successfully', [
            'user_id' => $user->id,
            'email'   => $user->email,
        ]);

        return response()->json([
            'message'    => 'Email verified successfully.',
            'user'       => $this->userResource($user->fresh()),
            'token'      => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Resend OTP
    // ─────────────────────────────────────────────────────────────────

    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Find user
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        // Already verified
        if ($user->email_verified_at !== null) {
            return response()->json([
                'message' => 'Email is already verified.',
            ], 422);
        }

        // Generate new OTP
        $otp = (string) random_int(100000, 999999);

        // Invalidate previous OTPs
        OtpCode::where('user_id', $user->id)
            ->where('used', false)
            ->update([
                'used' => true,
            ]);

        // Save new OTP
        OtpCode::create([
            'user_id'    => $user->id,
            'email'      => $user->email,
            'otp'        => $otp,
            'expires_at' => now()->addMinutes(10),
            'used'       => false,
        ]);

        // Send email
        try {
            Mail::to($user->email)
                ->send(new OtpVerificationMail($otp));

            Log::info('OTP resent successfully', [
                'user_id' => $user->id,
                'email'   => $user->email,
            ]);

            return response()->json([
                'message' => 'A new verification code has been sent to your email.',
            ]);
        } catch (\Throwable $e) {

            Log::error('OTP resend failed', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'error'   => $e->getMessage(),
            ]);

            // Invalidate failed OTP
            OtpCode::where('user_id', $user->id)
                ->where('otp', $otp)
                ->update([
                    'used' => true,
                ]);

            return response()->json([
                'message' => 'Could not send verification email. Please try again.',
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Login
    // ─────────────────────────────────────────────────────────────────

    public function login(LoginRequest $request): JsonResponse
    {
        // Find user first
        $user = User::where('email', $request->email)->first();

        // Don't reveal whether an account exists
        if (! $user) {
            return response()->json([
                'message' => 'Invalid email or password.',
            ], 401);
        }

        // Verify password BEFORE creating JWT
        if (! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password.',
            ], 401);
        }

        // Email must be verified
        if ($user->email_verified_at === null) {
            return response()->json([
                'message' => 'Please verify your email before logging in.',
            ], 403);
        }

        // Account must be active
        if (! $user->is_active) {
            return response()->json([
                'message' => 'Your account has been suspended. Contact support.',
            ], 403);
        }

        // Create JWT only after all checks pass
        try {
            $token = JWTAuth::fromUser($user);
        } catch (JWTException $e) {

            Log::error('JWT login error', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not create authentication token.',
            ], 500);
        }

        // Update last login
        $user->update([
            'last_login_at' => now(),
        ]);

        Log::info('User logged in', [
            'user_id' => $user->id,
            'email'   => $user->email,
        ]);

        return response()->json([
            'message'    => 'Login successful.',
            'user'       => $this->userResource($user->fresh()),
            'token'      => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Logout
    // ─────────────────────────────────────────────────────────────────

    public function logout(): JsonResponse
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (JWTException $e) {
            // Token is already invalid or expired
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Current User
    // ─────────────────────────────────────────────────────────────────

    public function me(): JsonResponse
    {
        return response()->json([
            'user' => $this->userResource(JWTAuth::user()),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Refresh Token
    // ─────────────────────────────────────────────────────────────────

    public function refresh(): JsonResponse
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());
        } catch (TokenExpiredException $e) {

            return response()->json([
                'message' => 'Token has expired and cannot be refreshed.',
            ], 401);
        } catch (JWTException $e) {

            return response()->json([
                'message' => 'Token is invalid.',
            ], 401);
        }

        return response()->json([
            'token'      => $newToken,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Update Profile
    // ─────────────────────────────────────────────────────────────────

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = JWTAuth::user();

        $data = $request->only([
            'name',
            'phone',
            'address',
        ]);

        // Profile image
        if ($request->hasFile('profile_image')) {

            if ($user->profile_image) {
                \Storage::disk('public')->delete($user->profile_image);
            }

            $data['profile_image'] = $request
                ->file('profile_image')
                ->store('profile_images', 'public');
        }

        // Password change
        if ($request->filled('current_password')) {

            if (! Hash::check(
                $request->current_password,
                $user->password
            )) {
                return response()->json([
                    'message' => 'Current password is incorrect.',
                ], 422);
            }

            $data['password'] = Hash::make(
                $request->new_password
            );
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user'    => $this->userResource($user->fresh()),
        ]);
    }


    // ─────────────────────────────────────────────────────────────────
    // Forgot Password — Send OTP
    // ─────────────────────────────────────────────────────────────────

public function forgotPassword(Request $request): JsonResponse
{
    Log::info('Forgot password request received', [
        'email' => $request->email,
    ]);

    $request->validate([
        'email' => 'required|email',
    ]);

    $user = User::where('email', $request->email)->first();

    // Don't reveal whether the email exists
    if (! $user) {
        Log::info('Forgot password requested for unknown email', [
            'email' => $request->email,
        ]);

        return response()->json([
            'message' => 'If that email is registered, a verification code has been sent.',
        ]);
    }

    Log::info('User found for password reset', [
        'user_id' => $user->id,
        'email'   => $user->email,
    ]);

    // Invalidate previous reset OTPs
    PasswordResetOtp::where('user_id', $user->id)
        ->where('used', false)
        ->update([
            'used' => true,
        ]);

    // Generate 6-digit OTP
    $otp = (string) random_int(100000, 999999);

    Log::info('Password reset OTP generated', [
        'user_id' => $user->id,
        'email'   => $user->email,
        // TEMPORARY - remove this in production
        'otp'     => $otp,
    ]);

    // Store OTP
    PasswordResetOtp::create([
        'user_id'    => $user->id,
        'email'      => $user->email,
        'otp'        => $otp,
        'expires_at' => now()->addMinutes(10),
        'used'       => false,
    ]);

    Log::info('Password reset OTP stored', [
        'user_id' => $user->id,
    ]);

    try {
        Mail::to($user->email)
            ->send(new PasswordResetOtpMail($otp));

        Log::info('Password reset OTP email sent', [
            'user_id' => $user->id,
            'email'   => $user->email,
        ]);

    } catch (\Throwable $e) {

        Log::error('Password reset OTP email failed', [
            'user_id' => $user->id,
            'email'   => $user->email,
            'error'   => $e->getMessage(),
        ]);

        PasswordResetOtp::where('user_id', $user->id)
            ->where('otp', $otp)
            ->update([
                'used' => true,
            ]);
    }

    return response()->json([
        'message' => 'If that email is registered, a verification code has been sent.',
    ]);
}

// ─────────────────────────────────────────────────────────────────
// Verify Password Reset OTP
// ─────────────────────────────────────────────────────────────────

    public function verifyResetOtp(Request $request): JsonResponse
{
    $request->validate([
        'email' => 'required|email',
        'otp'   => 'required|digits:6',
    ]);

    $user = User::where('email', $request->email)->first();

    if (! $user) {
        return response()->json([
            'message' => 'Invalid verification code.',
        ], 422);
    }

    $otpCode = PasswordResetOtp::where('user_id', $user->id)
        ->where('email', $user->email)
        ->where('otp', $request->otp)
        ->where('used', false)
        ->latest()
        ->first();

    if (! $otpCode) {
        return response()->json([
            'message' => 'Invalid verification code.',
        ], 422);
    }

    if ($otpCode->expires_at->isPast()) {
        return response()->json([
            'message' => 'Verification code has expired. Please request a new code.',
        ], 422);
    }

    // Generate a secure temporary reset token
    $resetToken = bin2hex(random_bytes(32));

    // Mark OTP as used and store reset authorization
    $otpCode->update([
        'used'        => true,
        'reset_token' => hash('sha256', $resetToken),
        'verified_at' => now(),
    ]);

    Log::info('Password reset OTP verified', [
        'user_id' => $user->id,
        'email'   => $user->email,
    ]);

    return response()->json([
        'message'    => 'OTP verified successfully.',
        'email'      => $user->email,
        'reset_token' => $resetToken,
    ]);
}

    // ─────────────────────────────────────────────────────────────────
    // Reset Password
    // ─────────────────────────────────────────────────────────────────

    public function resetPassword(Request $request): JsonResponse
{
    $request->validate([
        'email'                 => 'required|email',
        'reset_token'           => 'required|string',
        'password'              => [
            'required',
            'string',
            'min:8',
            'confirmed',
            'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
        ],
    ], [
        'password.regex' =>
            'Password must contain at least one uppercase letter, one lowercase letter, and one number.',
    ]);

    $user = User::where('email', $request->email)->first();

    if (! $user) {
        return response()->json([
            'message' => 'Unable to reset password.',
        ], 422);
    }

    // Find the latest verified reset request
    $resetOtp = PasswordResetOtp::where('user_id', $user->id)
        ->where('email', $user->email)
        ->where('verified_at', '!=', null)
        ->where('used', true)
        ->latest()
        ->first();

    if (! $resetOtp || ! $resetOtp->reset_token) {
        return response()->json([
            'message' => 'Invalid or expired password reset request.',
        ], 422);
    }

    // Compare supplied token with hashed database token
    if (! hash_equals(
        $resetOtp->reset_token,
        hash('sha256', $request->reset_token)
    )) {
        return response()->json([
            'message' => 'Invalid or expired password reset token.',
        ], 422);
    }

    // Optional: limit reset authorization lifetime
    if (
        $resetOtp->verified_at &&
        $resetOtp->verified_at->addMinutes(15)->isPast()
    ) {
        return response()->json([
            'message' => 'Password reset session has expired. Please request a new OTP.',
        ], 422);
    }

    // Update password
    $user->update([
        'password' => Hash::make($request->password),
    ]);

    // Invalidate reset token permanently
    $resetOtp->update([
        'reset_token' => null,
    ]);

    Log::info('Password reset successfully', [
        'user_id' => $user->id,
        'email'   => $user->email,
    ]);

    return response()->json([
        'message' => 'Password reset successfully.',
    ]);
}

    // ─────────────────────────────────────────────────────────────────
    // Update FCM Token
    // ─────────────────────────────────────────────────────────────────

    public function updateFcmToken(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        JWTAuth::user()->update([
            'fcm_token' => $request->fcm_token,
        ]);

        return response()->json([
            'message' => 'FCM token updated.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // User Resource
    // ─────────────────────────────────────────────────────────────────

    private function userResource(User $user): array
    {
        return [
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'phone'         => $user->phone,
            'address'       => $user->address,
            'role'          => $user->role,
            'is_active'     => $user->is_active,
            'profile_image' => $user->profile_image_url,
            'last_login_at' => $user->last_login_at?->toISOString(),
            'created_at'    => $user->created_at->toISOString(),
        ];
    }
}


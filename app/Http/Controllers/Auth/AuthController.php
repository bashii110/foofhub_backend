<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class AuthController extends Controller
{
    // ── Register ───────────────────────────────────────────────────────
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'phone'    => $request->phone,
            'role'     => User::ROLE_USER,
        ]);

        $token = JWTAuth::fromUser($user);

        Log::info('New user registered', ['user_id' => $user->id, 'email' => $user->email]);

        return response()->json([
            'message' => 'Registration successful',
            'user'    => $this->userResource($user),
            'token'   => $token,
        ], 201);
    }

    // ── Login ──────────────────────────────────────────────────────────
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        try {
            $token = JWTAuth::attempt($credentials);
        } catch (JWTException $e) {
            Log::error('JWT login error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Could not create token'], 500);
        }

        if (! $token) {
            return response()->json(['message' => 'Invalid email or password'], 401);
        }

        $user = JWTAuth::user();

        if (! $user->is_active) {
            JWTAuth::invalidate($token);
            return response()->json(['message' => 'Your account has been suspended. Contact support.'], 403);
        }

        $user->update(['last_login_at' => now()]);

        Log::info('User logged in', ['user_id' => $user->id]);

        return response()->json([
            'message'    => 'Login successful',
            'user'       => $this->userResource($user),
            'token'      => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ]);
    }

    // ── Logout ─────────────────────────────────────────────────────────
    public function logout(): JsonResponse
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (JWTException $e) {
            // Token already invalid — still OK
        }

        return response()->json(['message' => 'Logged out successfully']);
    }

    // ── Me ─────────────────────────────────────────────────────────────
    public function me(): JsonResponse
    {
        return response()->json(['user' => $this->userResource(JWTAuth::user())]);
    }

    // ── Refresh Token ──────────────────────────────────────────────────
    public function refresh(): JsonResponse
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());
        } catch (TokenExpiredException $e) {
            return response()->json(['message' => 'Token has expired and cannot be refreshed'], 401);
        } catch (JWTException $e) {
            return response()->json(['message' => 'Token is invalid'], 401);
        }

        return response()->json([
            'token'      => $newToken,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ]);
    }

    // ── Update Profile ─────────────────────────────────────────────────
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = JWTAuth::user();

        $data = $request->only(['name', 'phone', 'address']);

        // Handle profile image upload
        if ($request->hasFile('profile_image')) {
            // Delete old image if exists
            if ($user->profile_image) {
                \Storage::disk('public')->delete($user->profile_image);
            }
            $data['profile_image'] = $request->file('profile_image')
                ->store('profile_images', 'public');
        }

        // Handle password change
        if ($request->filled('current_password')) {
            if (! Hash::check($request->current_password, $user->password)) {
                return response()->json(['message' => 'Current password is incorrect'], 422);
            }
            $data['password'] = Hash::make($request->new_password);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user'    => $this->userResource($user->fresh()),
        ]);
    }

    // ── Forgot Password ────────────────────────────────────────────────
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        // Always return success to prevent email enumeration
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'If that email is registered, a reset link has been sent.',
        ]);
    }

    // ── Reset Password ─────────────────────────────────────────────────
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            fn ($user, $password) => $user->update(['password' => Hash::make($password)])
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Invalid or expired reset token'], 422);
        }

        return response()->json(['message' => 'Password reset successfully']);
    }

    // ── Update FCM Token (push notifications) ─────────────────────────
    public function updateFcmToken(Request $request): JsonResponse
    {
        $request->validate(['fcm_token' => 'required|string']);
        JWTAuth::user()->update(['fcm_token' => $request->fcm_token]);
        return response()->json(['message' => 'FCM token updated']);
    }

    // ── Private: Format user response ─────────────────────────────────
    private function userResource(User $user): array
    {
        return [
            'id'              => $user->id,
            'name'            => $user->name,
            'email'           => $user->email,
            'phone'           => $user->phone,
            'address'         => $user->address,
            'role'            => $user->role,
            'is_active'       => $user->is_active,
            'profile_image'   => $user->profile_image_url,
            'last_login_at'   => $user->last_login_at?->toISOString(),
            'created_at'      => $user->created_at->toISOString(),
        ];
    }
}
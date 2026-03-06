<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $v = $request->validate([
            'name'     => 'required|string|min:2|max:100',
            'email'    => 'required|email|unique:users|max:255',
            'password' => 'required|string|min:6|confirmed',
            'phone'    => 'nullable|string|min:10|max:15',
        ]);

        $user = User::create([
            'name'     => $v['name'],
            'email'    => $v['email'],
            'password' => Hash::make($v['password']),
            'phone'    => $v['phone'] ?? null,
            'role'     => 'user',
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'Registration successful',
            'user'    => $this->formatUser($user),
            'token'   => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $token = JWTAuth::attempt($credentials);
        if (!$token) {
            return response()->json(['message' => 'Invalid email or password'], 401);
        }

        $user = JWTAuth::user();
        if (!$user->is_active) {
            JWTAuth::invalidate($token);
            return response()->json(['message' => 'Account has been blocked'], 403);
        }

        return response()->json([
            'message' => 'Login successful',
            'user'    => $this->formatUser($user),
            'token'   => $token,
        ]);
    }

    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me()
    {
        return response()->json(['user' => $this->formatUser(JWTAuth::user())]);
    }

    public function refresh()
    {
        return response()->json(['token' => JWTAuth::refresh(JWTAuth::getToken())]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $status = Password::sendResetLink($request->only('email'));
        
        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password reset link sent to your email'])
            : response()->json(['message' => 'Unable to send reset link. Please try again.'], 500);
    }

    

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:6|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email','password','token'),
            function ($user, $password) {
                $user->update(['password' => Hash::make($password)]);
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password reset successfully'])
            : response()->json(['message' => 'Invalid or expired reset token'], 400);
    }

    public function updateProfile(Request $request)
    {
        $user = JWTAuth::user();
        $v    = $request->validate([
            'name'    => 'sometimes|string|min:2|max:100',
            'phone'   => 'sometimes|string|min:10|max:15',
            'address' => 'sometimes|string|max:500',
        ]);
        $user->update($v);
        return response()->json(['message' => 'Profile updated', 'user' => $this->formatUser($user)]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id'     => $user->id,
            'name'   => $user->name,
            'email'  => $user->email,
            'role'   => $user->role,
            'phone'  => $user->phone,
            'address'=> $user->address,
        ];
    }
}
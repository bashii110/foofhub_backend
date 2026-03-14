<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::withCount('orders')
            ->when($request->role,   fn ($q) => $q->where('role', $request->role))
            ->when($request->status, fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->when($request->search, function ($q) use ($request) {
                $term = $request->search;
                $q->where(fn ($q) => $q
                    ->where('name',  'LIKE', "%$term%")
                    ->orWhere('email', 'LIKE', "%$term%")
                    ->orWhere('phone', 'LIKE', "%$term%")
                );
            })
            ->orderByDesc('created_at');

        return response()->json($query->paginate(20));
    }

    public function show(User $targetUser): JsonResponse
    {
        $targetUser->load(['orders' => fn ($q) => $q->with('payment')->latest()->take(10)]);

        return response()->json([
            'user'           => $targetUser,
            'total_spent'    => (float) $targetUser->orders()->revenue()->sum('total_amount'),
            'total_orders'   => $targetUser->orders()->count(),
        ]);
    }

    public function toggleBlock(User $targetUser): JsonResponse
    {
        $admin = JWTAuth::user();

        // Prevent self-block
        if ($admin->id === $targetUser->id) {
            return response()->json(['message' => 'Cannot block yourself'], 422);
        }

        // Prevent blocking another admin
        if ($targetUser->isAdmin()) {
            return response()->json(['message' => 'Cannot block an admin account'], 403);
        }

        $targetUser->update(['is_active' => ! $targetUser->is_active]);

        $action = $targetUser->is_active ? 'unblocked' : 'blocked';

        return response()->json([
            'message'   => "User {$action} successfully",
            'is_active' => $targetUser->is_active,
        ]);
    }

    public function updateRole(Request $request, User $targetUser): JsonResponse
    {
        $request->validate([
            'role' => 'required|in:user,staff,rider',
        ]);

        // Only allow admin to elevate to staff/rider
        $targetUser->update(['role' => $request->role]);

        return response()->json([
            'message' => 'Role updated successfully',
            'user'    => $targetUser->fresh(),
        ]);
    }

    public function destroy(User $targetUser): JsonResponse
    {
        $admin = JWTAuth::user();

        if ($admin->id === $targetUser->id) {
            return response()->json(['message' => 'Cannot delete your own account'], 422);
        }

        // Soft-block instead of hard delete if user has orders
        if ($targetUser->orders()->exists()) {
            $targetUser->update(['is_active' => false]);
            return response()->json(['message' => 'User deactivated (has order history)']);
        }

        $targetUser->delete();
        return response()->json(['message' => 'User deleted']);
    }
}
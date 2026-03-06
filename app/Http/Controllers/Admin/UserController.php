<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if (!JWTAuth::user()->isStaff()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $q = User::where('role', 'user')->withCount('orders');

        if ($request->filled('search')) {
            $term = $request->search;
            $q->where(function ($q) use ($term) {
                $q->where('name','LIKE',"%$term%")
                  ->orWhere('email','LIKE',"%$term%");
            });
        }

        return response()->json($q->orderByDesc('created_at')->paginate(15));
    }

    public function show(User $targetUser)
    {
        if (!JWTAuth::user()->isStaff()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $targetUser->load('orders.payment');
        return response()->json(['user' => $targetUser]);
    }

    public function toggleBlock(User $targetUser)
    {
        if (!JWTAuth::user()->isAdmin()) {
            return response()->json(['message' => 'Only admins can block users'], 403);
        }

        $targetUser->update(['is_active' => !$targetUser->is_active]);
        $label = $targetUser->is_active ? 'unblocked' : 'blocked';

        return response()->json([
            'message' => "User $label successfully",
            'user'    => $targetUser,
        ]);
    }
}
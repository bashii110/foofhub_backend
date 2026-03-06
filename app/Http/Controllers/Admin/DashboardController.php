<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Order, User, Product};
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class DashboardController extends Controller
{
    public function stats()
    {
        if (!JWTAuth::user()->isStaff()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $today = now()->startOfDay();
        $revenueStatuses = ['verified','preparing','out_for_delivery','delivered'];

        return response()->json([
            'total_orders'         => Order::count(),
            'today_orders'         => Order::where('created_at', '>=', $today)->count(),
            'pending_verification' => Order::where('status', 'pending_verification')->count(),
            'total_revenue'        => Order::whereIn('status', $revenueStatuses)->sum('total_amount'),
            'today_revenue'        => Order::where('created_at', '>=', $today)
                                            ->whereIn('status', $revenueStatuses)
                                            ->sum('total_amount'),
            'total_users'          => User::where('role', 'user')->count(),
            'total_products'       => Product::count(),

            'orders_by_status' => Order::select('status', DB::raw('count(*) as count'))
                                       ->groupBy('status')
                                       ->get(),

            'weekly_revenue' => Order::select(
                    DB::raw("DATE(created_at) as date"),
                    DB::raw("SUM(total_amount) as revenue")
                )
                ->where('created_at', '>=', now()->subDays(6)->startOfDay())
                ->whereIn('status', $revenueStatuses)
                ->groupBy(DB::raw("DATE(created_at)"))
                ->orderBy('date')
                ->get(),

            'recent_orders' => Order::with('user', 'payment')
                                    ->orderByDesc('created_at')
                                    ->take(5)
                                    ->get(),
        ]);
    }
}
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Order, User, Product, Category};
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $stats = Cache::remember('admin_dashboard_stats', 60, function () {
            $today   = now()->startOfDay();
            $week    = now()->subDays(6)->startOfDay();

            return [
                // Counts
                'total_orders'          => Order::count(),
                'today_orders'          => Order::where('created_at', '>=', $today)->count(),
                'pending_verification'  => Order::where('status', Order::STATUS_PENDING_VERIFICATION)->count(),
                'active_orders'         => Order::whereIn('status', [
                                              Order::STATUS_VERIFIED,
                                              Order::STATUS_PREPARING,
                                              Order::STATUS_OUT_FOR_DELIVERY,
                                          ])->count(),

                // Revenue
                'total_revenue'         => (float) Order::revenue()->sum('total_amount'),
                'today_revenue'         => (float) Order::revenue()
                                              ->where('created_at', '>=', $today)
                                              ->sum('total_amount'),
                'weekly_revenue'        => (float) Order::revenue()
                                              ->where('created_at', '>=', $week)
                                              ->sum('total_amount'),

                // Users
                'total_users'           => User::where('role', User::ROLE_USER)->count(),
                'new_users_today'       => User::where('role', User::ROLE_USER)
                                              ->where('created_at', '>=', $today)
                                              ->count(),

                // Catalogue
                'total_products'        => Product::count(),
                'unavailable_products'  => Product::where('is_available', false)->count(),
                'total_categories'      => Category::count(),

                // Charts
                'orders_by_status'      => Order::select('status', DB::raw('count(*) as count'))
                                              ->groupBy('status')
                                              ->get(),

                'daily_revenue_chart'   => Order::select(
                                                  DB::raw("DATE(created_at) as date"),
                                                  DB::raw("SUM(total_amount) as revenue"),
                                                  DB::raw("COUNT(*) as orders")
                                              )
                                              ->revenue()
                                              ->where('created_at', '>=', $week)
                                              ->groupBy(DB::raw("DATE(created_at)"))
                                              ->orderBy('date')
                                              ->get(),

                'top_products'          => DB::table('order_items')
                                              ->select('product_name', DB::raw('SUM(quantity) as total_sold'))
                                              ->groupBy('product_name')
                                              ->orderByDesc('total_sold')
                                              ->limit(5)
                                              ->get(),

                // Recent activity
                'recent_orders'         => Order::with(['user:id,name,email', 'payment'])
                                              ->orderByDesc('created_at')
                                              ->take(10)
                                              ->get(),
            ];
        });

        return response()->json($stats);
    }

    // ── Revenue report ─────────────────────────────────────────────────
    public function revenueReport(): JsonResponse
    {
        $data = Order::select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw("DATE_FORMAT(created_at, '%b %Y') as label"),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('AVG(total_amount) as avg_order_value'),
            )
            ->revenue()
            ->where('created_at', '>=', now()->subYear())
            ->groupBy('year', 'month', 'label')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        return response()->json(['report' => $data]);
    }
}
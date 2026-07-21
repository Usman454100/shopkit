<?php

namespace App\Http\Controllers\Api\StoreAdmin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        // Cancelled orders don't count as revenue in either figure.
        $revenueQuery = fn () => Order::query()->where('status', '!=', 'cancelled');

        $ordersToday = Order::query()->whereDate('created_at', now()->toDateString());

        $horizon = now()->addDays(config('shopkit.expiring_soon_days'));

        return response()->json([
            'data' => [
                'orders_today' => (clone $ordersToday)->count(),
                'revenue_today' => (clone $revenueQuery)()->whereDate('created_at', now()->toDateString())->sum('total_amount'),
                'revenue_to_date' => $revenueQuery()->sum('total_amount'),
                'low_stock_count' => Inventory::query()->whereColumn('quantity_on_hand', '<=', 'reorder_level')->count(),
                'expiring_soon_count' => Product::query()
                    ->where('is_perishable', true)
                    ->whereNotNull('expiry_date')
                    ->whereDate('expiry_date', '<=', $horizon)
                    ->count(),
            ],
        ]);
    }
}

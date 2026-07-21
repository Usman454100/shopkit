<?php

namespace App\Http\Controllers\Api\StoreAdmin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->with('customer:id,name,phone,email')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(25);

        return response()->json($orders);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'data' => $order->load(['items.product', 'customer:id,name,phone,email', 'statusHistories.changedBy:id,name']),
        ]);
    }

    public function updateStatus(Request $request, Order $order, OrderStatusService $orderStatusService): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'])],
        ]);

        $order = $orderStatusService->transition($order, $data['status'], $request->user());

        return response()->json(['data' => $order->load('statusHistories')]);
    }
}

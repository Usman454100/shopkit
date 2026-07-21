<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * Checkout — the only write path for stock, alongside Milestone 2's manual
     * admin adjustment. Locks each inventory row to avoid two customers
     * overselling the last unit, snapshots pricing (never trusts client
     * totals), and re-checks expiry directly rather than trusting is_active
     * (Milestone 2's flag-expired job only runs daily — see docs/03-DATABASE-SCHEMA.md §3).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'delivery_address' => ['required', 'string', 'max:1000'],
            'payment_method' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid'],
            'items.*.variant_id' => ['nullable', 'uuid'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        if ($data['payment_method'] !== 'cod') {
            throw ValidationException::withMessages([
                'payment_method' => 'Only cash on delivery is available at this time.',
            ]);
        }

        $order = DB::transaction(function () use ($request, $data) {
            $total = 0;
            $lineItems = [];

            foreach ($data['items'] as $item) {
                $product = Product::query()->findOrFail($item['product_id']);

                if (! $product->is_active || $product->isExpired()) {
                    throw ValidationException::withMessages([
                        'items' => "\"{$product->name}\" is no longer available.",
                    ]);
                }

                $variant = null;
                $unitPrice = $product->base_price;

                if (! empty($item['variant_id'])) {
                    $variant = $product->variants()->findOrFail($item['variant_id']);
                    $unitPrice = $variant->price_override ?? $product->base_price;
                } elseif ($product->has_variants) {
                    throw ValidationException::withMessages([
                        'items' => "\"{$product->name}\" requires selecting a variant.",
                    ]);
                }

                $inventoryQuery = Inventory::query()
                    ->where('product_id', $product->id)
                    ->where('variant_id', $variant?->id)
                    ->lockForUpdate();

                $inventory = $inventoryQuery->first();

                if (! $inventory || $inventory->quantity_on_hand < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => "\"{$product->name}\" doesn't have enough stock.",
                    ]);
                }

                $inventory->decrement('quantity_on_hand', $item['quantity']);

                $subtotal = round($unitPrice * $item['quantity'], 2);
                $total += $subtotal;

                $lineItems[] = [
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ];
            }

            $order = Order::create([
                'customer_id' => $request->user()->id,
                'status' => 'pending',
                'payment_method' => 'cod',
                'payment_status' => 'pending',
                'delivery_address' => $data['delivery_address'],
                'total_amount' => $total,
            ]);

            foreach ($lineItems as $lineItem) {
                $order->items()->create($lineItem);
            }

            return $order;
        });

        return response()->json(['data' => $order->load('items')], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->where('customer_id', $request->user()->id)
            ->with('items')
            ->latest()
            ->paginate(25);

        return response()->json($orders);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->customer_id !== $request->user()->id) {
            abort(404);
        }

        return response()->json(['data' => $order->load('items')]);
    }
}

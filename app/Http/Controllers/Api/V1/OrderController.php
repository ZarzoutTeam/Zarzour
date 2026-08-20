<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Orders\StoreOrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\PriceCalculationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    use ApiResponse;

    private const MAX_PER_PAGE = 50;

    public function __construct(
        private readonly PriceCalculationService $priceCalculationService,
    ) {}

    /**
     * Re-runs the same PriceCalculationService used by the /cart/calculate preview
     * so pricing, coupon validity, and gift availability are all re-checked at the
     * actual moment of order creation (stock/coupon state may have changed since
     * the customer last previewed their cart).
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $data = $request->validated();

        $authenticatedUser = $request->user('sanctum');
        $userId = $authenticatedUser instanceof User && $authenticatedUser->isCustomer() ? $authenticatedUser->id : null;

        ['order' => $order, 'pricing' => $pricing] = DB::transaction(function () use ($data, $userId): array {
            // Product and coupon rows are locked by the pricing service. This keeps
            // stock reservations and coupon limits correct under concurrent orders.
            $pricing = $this->priceCalculationService->calculate(
                $data['lines'],
                $data['coupon_code'] ?? null,
                $data['phone_number'],
                $data['province_id'],
                lockForUpdate: true,
            );

            $productNames = Product::query()
                ->whereIn('id', array_column($pricing['lines'], 'product_id'))
                ->pluck('name', 'id');

            $primaryOfferId = collect($pricing['lines'])
                ->first(fn (array $line): bool => $line['offer_id'] !== null)['offer_id'] ?? null;

            $order = Order::create([
                'user_id' => $userId,
                'customer_name' => $data['customer_name'],
                'phone_number' => $data['phone_number'],
                'province_id' => $data['province_id'],
                'shipping_address' => $data['shipping_address'],
                'extra_notes' => $data['extra_notes'] ?? null,
                'subtotal' => $pricing['subtotal'],
                'subtotal_usd' => $pricing['amounts']['subtotal']['USD'],
                'discount_amount' => $pricing['discount_amount'],
                'discount_amount_usd' => $pricing['amounts']['discount_amount']['USD'],
                'coupon_discount_amount' => $pricing['coupon']['discount_amount'] ?? 0,
                'coupon_discount_amount_usd' => $pricing['coupon'] !== null
                    ? $pricing['coupon']['discount_amounts']['USD']
                    : ($pricing['exchange_rate'] !== null ? 0 : null),
                'shipping_fee' => $pricing['shipping_fee'],
                'shipping_fee_usd' => $pricing['amounts']['shipping_fee']['USD'],
                'total' => $pricing['grand_total'],
                'total_usd' => $pricing['amounts']['grand_total']['USD'],
                'coupon_id' => $pricing['coupon']['id'] ?? null,
                'applied_offer_id' => $primaryOfferId,
                'payment_method' => $data['payment_method'],
                'currency' => 'SYP',
                'exchange_rate_snapshot' => $pricing['exchange_rate']['rate'] ?? null,
                'status' => 'pending',
            ]);

            foreach ($pricing['lines'] as $line) {
                $order->items()->create([
                    'product_id' => $line['product_id'],
                    'product_name_snapshot' => $productNames->get($line['product_id']),
                    'unit_price_snapshot' => $line['unit_price'],
                    'unit_price_snapshot_usd' => $line['amounts']['unit_price']['USD'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['final_line_total'],
                    'line_total_usd' => $line['amounts']['final_line_total']['USD'],
                    'direct_discount_amount' => $line['direct_discount_amount'],
                    'direct_discount_amount_usd' => $line['amounts']['direct_discount_amount']['USD'],
                    'coupon_discount_amount' => $line['coupon_discount_amount'],
                    'coupon_discount_amount_usd' => $line['amounts']['coupon_discount_amount']['USD'],
                    'offer_discount_amount' => $line['offer_discount_amount'],
                    'offer_discount_amount_usd' => $line['amounts']['offer_discount_amount']['USD'],
                    'is_gift' => false,
                    'offer_id' => $line['offer_id'],
                ]);

                if ($line['gift'] !== null) {
                    $order->items()->create([
                        'product_id' => $line['gift']['product_id'],
                        'product_name_snapshot' => $line['gift']['name'],
                        'unit_price_snapshot' => 0,
                        'unit_price_snapshot_usd' => $pricing['exchange_rate'] !== null ? 0 : null,
                        'quantity' => 1,
                        'line_total' => 0,
                        'line_total_usd' => $pricing['exchange_rate'] !== null ? 0 : null,
                        'direct_discount_amount' => 0,
                        'direct_discount_amount_usd' => $pricing['exchange_rate'] !== null ? 0 : null,
                        'coupon_discount_amount' => 0,
                        'coupon_discount_amount_usd' => $pricing['exchange_rate'] !== null ? 0 : null,
                        'offer_discount_amount' => 0,
                        'offer_discount_amount_usd' => $pricing['exchange_rate'] !== null ? 0 : null,
                        'is_gift' => true,
                        'offer_id' => $line['gift']['offer_id'],
                    ]);
                }
            }

            if ($pricing['coupon'] !== null) {
                Coupon::whereKey($pricing['coupon']['id'])->increment('used_count');
                $pricing['coupon']['used_count']++;

                if ($pricing['coupon']['usage_limit'] !== null) {
                    $pricing['coupon']['remaining_uses'] = max(
                        0,
                        $pricing['coupon']['usage_limit'] - $pricing['coupon']['used_count'],
                    );
                }

                if ($pricing['coupon']['customer_usage_count'] !== null) {
                    $pricing['coupon']['customer_usage_count']++;

                    if ($pricing['coupon']['per_customer_usage_limit'] !== null) {
                        $pricing['coupon']['customer_remaining_uses'] = max(
                            0,
                            $pricing['coupon']['per_customer_usage_limit'] - $pricing['coupon']['customer_usage_count'],
                        );
                    }
                }
            }

            return ['order' => $order, 'pricing' => $pricing];
        }, 3);

        return $this->success([
            'order_id' => $order->id,
            'status' => $order->status,
            'currency' => $order->currency,
            'exchange_rate' => $order->exchange_rate_snapshot !== null ? [
                'base_currency' => 'USD',
                'quote_currency' => 'SYP',
                'rate' => (float) $order->exchange_rate_snapshot,
            ] : null,
            'amounts' => [
                'subtotal' => $this->dualAmount($order->subtotal, $order->subtotal_usd),
                'discount_amount' => $this->dualAmount($order->discount_amount, $order->discount_amount_usd),
                'coupon_discount_amount' => $this->dualAmount($order->coupon_discount_amount, $order->coupon_discount_amount_usd),
                'shipping_fee' => $this->dualAmount($order->shipping_fee, $order->shipping_fee_usd),
                'total' => $this->dualAmount($order->total, $order->total_usd),
            ],
            'subtotal' => (float) $order->subtotal,
            'discount_amount' => (float) $order->discount_amount,
            'coupon_discount_amount' => (float) $order->coupon_discount_amount,
            'shipping_fee' => (float) $order->shipping_fee,
            'total' => (float) $order->total,
            'coupon' => $pricing['coupon'],
            'items' => $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'name' => $item->product_name_snapshot,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price_snapshot,
                'line_total' => (float) $item->line_total,
                'direct_discount_amount' => (float) $item->direct_discount_amount,
                'coupon_discount_amount' => (float) $item->coupon_discount_amount,
                'offer_discount_amount' => (float) $item->offer_discount_amount,
                'amounts' => [
                    'unit_price' => $this->dualAmount($item->unit_price_snapshot, $item->unit_price_snapshot_usd),
                    'line_total' => $this->dualAmount($item->line_total, $item->line_total_usd),
                    'direct_discount_amount' => $this->dualAmount($item->direct_discount_amount, $item->direct_discount_amount_usd),
                    'coupon_discount_amount' => $this->dualAmount($item->coupon_discount_amount, $item->coupon_discount_amount_usd),
                    'offer_discount_amount' => $this->dualAmount($item->offer_discount_amount, $item->offer_discount_amount_usd),
                ],
                'offer_id' => $item->offer_id,
                'is_gift' => $item->is_gift,
            ]),
        ], 'تم إنشاء الطلب بنجاح', 201);
    }

    public function myOrders(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->with(['items', 'province', 'coupon'])
            ->latest()
            ->paginate($this->perPage($request));

        return $this->success([
            'items' => OrderResource::collection($orders->items()),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }

    /**
     * Ownership mismatches return 404 (not 403) to avoid leaking that an order id
     * belongs to someone else.
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            abort(404);
        }

        if ($order->status !== 'pending') {
            return $this->error(__('http.order_cancel_not_pending'), null, 422);
        }

        $order->update(['status' => 'cancelled']);

        return $this->success(
            new OrderResource($order->fresh(['items', 'province', 'coupon'])),
            'تم إلغاء الطلب بنجاح',
        );
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), self::MAX_PER_PAGE);
    }

    /**
     * @return array{SYP: float|null, USD: float|null}
     */
    private function dualAmount(mixed $syp, mixed $usd): array
    {
        return [
            'SYP' => $syp !== null ? (float) $syp : null,
            'USD' => $usd !== null ? (float) $usd : null,
        ];
    }
}

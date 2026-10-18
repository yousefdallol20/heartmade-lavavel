<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendOrderConfirmedEmail;
use App\Models\Order;
use App\Models\Product;
use App\Services\AdminNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    private function queueCustomerConfirmation(
        Order $order
    ): void {
        if ($order->status !== 'confirmed') {
            return;
        }

        /*
     * تعديل السجل بشرط أن البريد لم يدخل الطابور سابقًا.
     * هذا يمنع إرسال البريد مرتين حتى مع طلبين متزامنين.
     */
        $updated = Order::query()
            ->whereKey($order->id)
            ->whereNull('customer_confirmation_queued_at')
            ->update([
                'customer_confirmation_queued_at' => now(),
            ]);

        if ($updated !== 1) {
            return;
        }

        SendOrderConfirmedEmail::dispatch($order->id)
            ->onConnection('database')
            ->onQueue('default');

        AdminNotifier::create(
            'Order confirmed',
            "Order #{$order->id} was confirmed. "
                .'A confirmation email was queued for '
                ."{$order->customer_email}.",
            'order_confirmed',
            $order->id
        );
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(
            (bool) $request->user()?->is_admin,
            403,
            'Admin access is required.'
        );
    }

    private function validatedData(
        Request $request,
        bool $updating = false
    ): array {
        $required = $updating
            ? ['sometimes', 'required']
            : ['required'];

        return $request->validate([
            'customer_name' => [...$required, 'string', 'max:255'],
            'customer_phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:40',
            ],
            'customer_email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
            ],
            'shipping_address' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],
            'payment_method' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'payment_status' => [
                'sometimes',
                'required',
                Rule::in(['unpaid', 'paid']),
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::in(Order::STATUSES),
            ],
            'items' => [...$required, 'array', 'min:1', 'max:100'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                'exists:products,id',
            ],
            'items.*.qty' => [
                'required',
                'integer',
                'min:1',
                'max:100000',
            ],
            'items.*.price' => [
                'required',
                'numeric',
                'decimal:0,2',
                'min:0',
                'max:9999999999.99',
            ],
        ]);
    }

    private function cents($price): int
    {
        $parts = explode('.', (string) $price, 2);

        return ((int) $parts[0] * 100)
            + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100)
            .'.'
            .str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function quantities(array $items): array
    {
        $quantities = [];

        foreach ($items as $item) {
            if (! empty($item['product_id'])) {
                $id = (int) $item['product_id'];
                $quantities[$id] =
                    ($quantities[$id] ?? 0) + (int) $item['qty'];
            }
        }

        return $quantities;
    }

    private function syncStock(
        array $oldItems,
        string $oldStatus,
        array $newItems,
        string $newStatus
    ) {
        $oldQuantities = $oldStatus === 'cancelled'
            ? []
            : $this->quantities($oldItems);

        $newQuantities = $newStatus === 'cancelled'
            ? []
            : $this->quantities($newItems);

        $ids = array_unique([
            ...array_keys($oldQuantities),
            ...array_keys($newQuantities),
        ]);

        sort($ids);

        $products = Product::whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($ids as $id) {
            $product = $products->get($id);

            if (! $product) {
                if (($newQuantities[$id] ?? 0) > 0) {
                    throw ValidationException::withMessages([
                        'items' => 'One of the products is no longer available.',
                    ]);
                }

                continue;
            }

            $remaining = (int) $product->stock
                + ($oldQuantities[$id] ?? 0)
                - ($newQuantities[$id] ?? 0);

            if ($remaining < 0) {
                throw ValidationException::withMessages([
                    'items' => "Insufficient stock for {$product->name}.",
                ]);
            }

            $product->stock = $remaining;
            $product->save();
        }

        return $products;
    }

    private function saveOrder(
        array $data,
        ?Order $existing = null
    ): Order {
        return DB::transaction(function () use ($data, $existing) {
            $order = $existing
                ? Order::whereKey($existing->id)
                    ->lockForUpdate()
                    ->firstOrFail()
                : new Order;

            // السماح بتسجيل الدفع عند الاستلام فقط، مع قفل الحالة بعد الدفع.
            $paymentOnly = count($data) === 1 && array_key_exists('payment_status', $data);
            $cashOnDelivery = in_array(strtolower(trim((string) $order->payment_method)), ['cod', 'cash on delivery'], true);
            if ($order->exists && array_key_exists('payment_status', $data)) {
                if ($order->payment_status === 'paid' && $data['payment_status'] !== 'paid') {
                    throw ValidationException::withMessages(['payment_status' => 'A paid order cannot be marked as unpaid.']);
                }
                if ($data['payment_status'] !== $order->payment_status &&
                    (! $cashOnDelivery || $order->payment_status !== 'unpaid' || $data['payment_status'] !== 'paid' || $order->status === 'cancelled')) {
                    throw ValidationException::withMessages(['payment_status' => 'Only an unpaid cash-on-delivery order can be marked as paid.']);
                }
            }
            if ($order->exists && $order->status === 'delivered' &&
                ! ($paymentOnly && $cashOnDelivery && $data['payment_status'] === 'paid')) {
                throw ValidationException::withMessages([
                    'status' => 'A delivered order cannot be modified. Only receipt of cash-on-delivery payment can be recorded.',
                ]);
            }

            if (! $order->exists && strtolower((string) ($data['payment_method'] ?? '')) === 'paypal') {
                throw ValidationException::withMessages(['payment_method' => 'PayPal orders must be created through checkout.']);
            }
            if ($order->exists && $order->payment_method === 'PayPal') {
                $forbidden = array_diff(array_keys($data), ['status']);
                if ($forbidden !== []) {
                    throw ValidationException::withMessages(['order' => 'PayPal order details and payment status are managed by the payment gateway. Change only the fulfilment status.']);
                }
                if ($order->payment_status !== 'paid' || ($data['status'] ?? '') === 'cancelled') {
                    throw ValidationException::withMessages(['status' => 'An unpaid PayPal order cannot be fulfilled. Refund paid orders through PayPal before cancellation.']);
                }
            }

            // تغيير الدفع وحده لا يعيد حساب المبلغ أو المخزون.
            if ($order->exists && $paymentOnly) {
                $order->payment_status = $data['payment_status'];
                $order->save();
                return $order->load('items');
            }

            $oldItems = $order->exists
                ? $order->items()->get()->toArray()
                : [];

            $oldStatus = $order->exists
                ? $order->status
                : 'cancelled';

            $newStatus = $data['status']
                ?? ($order->exists ? $order->status : 'pending');

            $replaceItems = array_key_exists('items', $data);
            $newItems = $replaceItems ? $data['items'] : $oldItems;

            $products = $this->syncStock(
                $oldItems,
                $oldStatus,
                $newItems,
                $newStatus
            );

            $rows = [];
            $subtotalCents = 0;

            foreach ($newItems as $item) {
                $priceCents = $this->cents($item['price']);
                $quantity = (int) $item['qty'];

                $subtotalCents += $priceCents * $quantity;

                if ($subtotalCents > 999999999999) {
                    throw ValidationException::withMessages([
                        'items' => 'The order total exceeds the allowed limit.',
                    ]);
                }

                if ($replaceItems) {
                    $product = $products->get($item['product_id'])
                        ?? Product::find($item['product_id']);

                    if (! $product) {
                        throw ValidationException::withMessages([
                            'items' => 'One of the products is no longer available.',
                        ]);
                    }

                    if (
                        $newStatus !== 'cancelled'
                        && $product->category === 'Soon'
                    ) {
                        throw ValidationException::withMessages([
                            'items' => "The product is not available for order: {$product->name}.",
                        ]);
                    }

                    $rows[] = [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'price' => $this->money($priceCents),
                        'qty' => $quantity,
                    ];
                }
            }

            unset($data['items']);

            $order->fill($data);
            $order->status = $newStatus;

            if (! $order->exists && ! isset($data['payment_status'])) {
                $order->payment_status = 'unpaid';
            }

            $order->subtotal = $this->money($subtotalCents);
            $order->total = $this->money($subtotalCents);
            $order->save();

            if ($replaceItems) {
                $order->items()->delete();
                $order->items()->createMany($rows);
            }

            return $order->load('items');
        });
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(Order::STATUSES)],
        ]);

        $query = Order::with([
            'items',
            'checkoutPayment',
        ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($query) use ($search) {
                $query->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $query->orWhere('id', $search);
                }
            });
        }

        return response()->json(
            $query->orderByDesc('id')->paginate(20)
        );
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);

        $order = $this->saveOrder($this->validatedData($request));

        AdminNotifier::largeOrder($order);
        $this->queueCustomerConfirmation($order);

        return response()->json([
            'message' => 'The order has been created successfully.',
            'data' => $order,
        ], 201);
    }

    public function show(Request $request, Order $order)
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'data' => $order->load('items'),
        ]);
    }

    public function update(
        Request $request,
        Order $order
    ) {
        $this->authorizeAdmin($request);

        $oldStatus = $order->status;
        $oldPaymentStatus = $order->payment_status;

        $order = $this->saveOrder(
            $this->validatedData($request, true),
            $order
        );

        $changes = [];

        if ($oldStatus !== $order->status) {
            $changes[] =
                "status: {$oldStatus} → {$order->status}";
        }

        if (
            $oldPaymentStatus
            !== $order->payment_status
        ) {
            $changes[] =
                "payment: {$oldPaymentStatus} "
                ."→ {$order->payment_status}";
        }

        if ($request->has('items')) {
            $changes[] = 'order items';
        }

        AdminNotifier::create(
            'Order updated',
            "Order #{$order->id} was updated by "
                .AdminNotifier::actor($request->user())
                .(
                    $changes !== []
                    ? '. Changed: '
                    .implode(', ', $changes)
                    .'.'
                    : '.'
                ),
            'order_updated',
            $order->id
        );

        AdminNotifier::largeOrder($order);

        $this->queueCustomerConfirmation($order);

        return response()->json([
            'message' => 'The order has been updated successfully.',
            'data' => $order->fresh()->load('items'),
        ]);
    }

    public function destroy(Request $request, Order $order)
    {
        $this->authorizeAdmin($request);

        DB::transaction(function () use ($order) {
            $lockedOrder = Order::whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->payment_method === 'PayPal') {
                throw ValidationException::withMessages([
                    'order' => 'PayPal orders cannot be deleted. Their payment records must be retained.',
                ]);
            }

            if ($lockedOrder->status === 'delivered') {
                throw ValidationException::withMessages([
                    'order' => 'A delivered order cannot be deleted.',
                ]);
            }

            $this->syncStock(
                $lockedOrder->items()->get()->toArray(),
                $lockedOrder->status,
                [],
                'cancelled'
            );

            $lockedOrder->delete();
        });

        return response()->json([
            'message' => 'The order has been deleted successfully.',
        ]);
    }
}

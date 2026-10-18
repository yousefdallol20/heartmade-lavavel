<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    private const CITIES = [
        'amman' => ['name' => 'Amman'],
        'zarqa' => ['name' => 'Zarqa'],
        'irbid' => ['name' => 'Irbid'],
        'balqa' => ['name' => 'Balqa / Salt'],
        'aqaba' => ['name' => 'Aqaba'],
        'madaba' => ['name' => 'Madaba'],
        'jerash' => ['name' => 'Jerash'],
        'ajloun' => ['name' => 'Ajloun'],
        'karak' => ['name' => 'Karak'],
        'tafilah' => ['name' => 'Tafilah'],
        'maan' => ['name' => "Ma'an"],
        'mafraq' => ['name' => 'Mafraq'],
    ];

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedData($request);

        $order = $this->reserve($validated);

        return response()->json([
            'success' => true,
            'message' => 'Your order has been received successfully.',
            'data' => $this->receipt($order),
        ], 201);
    }

    public function validatedData(
        Request $request,
        bool $paypal = false
    ): array {
        // دعم الحقول الجديدة مع قبول جلسات الدفع القديمة المحفوظة.
        $hasAddressParts = $request->hasAny([
            'street_address',
            'apartment_number',
            'building_number',
        ]);

        $validated = $request->validate([
            'customer_name' => [
                'required',
                'string',
                'max:255',
            ],

            'customer_phone' => [
                'required',
                'string',
                'max:40',
            ],

            'customer_email' => [
                'required',
                'email',
                'max:255',
            ],

            'shipping_address' => $hasAddressParts
                ? ['sometimes', 'nullable', 'string', 'max:2000']
                : ['required', 'string', 'max:2000'],

            'street_address' => $hasAddressParts
                ? ['required', 'string', 'max:1000']
                : ['exclude'],

            'apartment_number' => $hasAddressParts
                ? ['required', 'string', 'max:50']
                : ['exclude'],

            'building_number' => $hasAddressParts
                ? ['required', 'string', 'max:50']
                : ['exclude'],

            'city' => [
                'required',
                Rule::in(array_keys(self::CITIES)),
            ],

            'payment_method' => [
                'required',
                Rule::in(
                    $paypal
                        ? ['PayPal']
                        : ['Cash on Delivery']
                ),
            ],

            'expected_total' => [
                'required',
                'numeric',
                'decimal:0,2',
                'min:0',
                'max:9999999999.99',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
                'max:100',
            ],

            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                ...($paypal ? [] : ['exists:products,id']),
            ],

            'items.*.qty' => [
                'required',
                'integer',
                'min:1',
                'max:100',
            ],
        ], [
            'street_address.required' =>
                'Please enter your street address.',

            'street_address.max' =>
                'The street address must not exceed 1000 characters.',

            'apartment_number.required' =>
                'Please enter your apartment number.',

            'apartment_number.max' =>
                'The apartment number must not exceed 50 characters.',

            'building_number.required' =>
                'Please enter your building number.',

            'building_number.max' =>
                'The building number must not exceed 50 characters.',
        ]);

        if ($hasAddressParts) {
            $validated['shipping_address'] = implode(', ', [
                trim($validated['street_address']),
                'Building '.trim($validated['building_number']),
                'Apartment '.trim($validated['apartment_number']),
            ]);
        }

        // الاحتفاظ بعنوان واحد لضمان توافق الطلبات والبريد واستعادة الدفع.
        unset(
            $validated['street_address'],
            $validated['apartment_number'],
            $validated['building_number']
        );

        // تثبيت ترتيب البيانات يحافظ على بصمة طلب PayPal عند إعادة المحاولة.
        return [
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'customer_email' => $validated['customer_email'],
            'shipping_address' => $validated['shipping_address'],
            'city' => $validated['city'],
            'payment_method' => $validated['payment_method'],
            'expected_total' => $validated['expected_total'],
            'items' => $validated['items'],
        ];
    }

    public function reserve(
        array $validated,
        bool $paypal = false
    ): Order {
        $city = self::CITIES[$validated['city']];

        return DB::transaction(function () use (
            $validated,
            $city,
            $paypal
        ) {
            $productIds = collect($validated['items'])
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values();

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $rows = [];
            $subtotalCents = 0;

            foreach ($validated['items'] as $index => $item) {
                $product = $products->get(
                    (int) $item['product_id']
                );

                $quantity = (int) $item['qty'];

                if (! $product || $product->category === 'Soon') {
                    throw ValidationException::withMessages([
                        "items.$index.product_id" =>
                            'This product is not available for purchase.',
                    ]);
                }

                if ((int) $product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        "items.$index.qty" =>
                            "Insufficient stock for {$product->name}.",
                    ]);
                }

                $price = (string) $product->price;

                if (! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $price)) {
                    throw ValidationException::withMessages([
                        "items.$index.product_id" =>
                            'This product has an invalid price.',
                    ]);
                }

                $priceCents = $this->cents($price);

                $subtotalCents += $priceCents * $quantity;

                if ($subtotalCents > 999999999999) {
                    throw ValidationException::withMessages([
                        'items' =>
                            'The order total exceeds the allowed limit.',
                    ]);
                }

                $rows[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'price' => $this->money($priceCents),
                    'qty' => $quantity,
                ];
            }

            $totalCents = $subtotalCents;

            if (
                $this->cents((string) $validated['expected_total'])
                !== $totalCents
            ) {
                throw ValidationException::withMessages([
                    'expected_total' =>
                        'Prices have changed. Review your updated order summary and try again.',
                ]);
            }

            foreach ($rows as $row) {
                $product = $products->get($row['product_id']);

                $product->stock =
                    (int) $product->stock - $row['qty'];

                $product->save();
            }

            $order = Order::create([
                'customer_name' =>
                    trim($validated['customer_name']),

                'customer_phone' =>
                    trim($validated['customer_phone']),

                'customer_email' =>
                    trim($validated['customer_email']),

                'shipping_address' =>
                    trim($validated['shipping_address'])
                    .', '.$city['name'],

                'payment_method' =>
                    $paypal ? 'PayPal' : 'Cash on Delivery',

                'payment_status' => 'unpaid',
                'status' => 'pending',
                'subtotal' => $this->money($subtotalCents),
                'total' => $this->money($totalCents),
            ]);

            $order->items()->createMany($rows);

            return $order->load('items');
        });
    }

    public function receipt(Order $order): array
    {
        $order->loadMissing('items');

        return [
            'id' => $order->id,

            'reference' => 'ORDER-'.str_pad(
                (string) $order->id,
                6,
                '0',
                STR_PAD_LEFT
            ),

            'customer_name' => $order->customer_name,
            'shipping_address' => $order->shipping_address,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'status' => $order->status,
            'subtotal' => $order->subtotal,
            'total' => $order->total,
            'currency' => 'JD',
            'created_at' => $order->created_at->toIso8601String(),

            'items' => $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'name' => $item->name,
                'price' => $item->price,
                'qty' => $item->qty,
            ])->values()->all(),
        ];
    }

    private function cents(string $price): int
    {
        $parts = explode('.', $price, 2);

        return ((int) $parts[0] * 100)
            + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100)
            .'.'
            .str_pad(
                (string) ($cents % 100),
                2,
                '0',
                STR_PAD_LEFT
            );
    }
}

<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class CheckoutQuote
{
    public function cents(string $value): int
    {
        if (! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages([
                'amount' => 'Invalid monetary amount.',
            ]);
        }

        [$whole, $fraction] = array_pad(
            explode('.', $value, 2),
            2,
            ''
        );

        return (int) $whole * 100
            + (int) str_pad($fraction, 2, '0');
    }

    public function money(int $cents): string
    {
        return intdiv($cents, 100)
            .'.'
            .str_pad((string) ($cents % 100), 2, '0');
    }

    public function rate(): string
    {
        $rate = trim((string) config('checkout.usd_to_jod'));

        if (
            ! preg_match('/^\d{1,2}(?:\.\d{1,8})?$/', $rate)
            || (float) $rate < 0.01
            || (float) $rate > 10
        ) {
            throw ValidationException::withMessages([
                'payment_method' => 'The store exchange rate is not configured.',
            ]);
        }

        [$whole, $fraction] = array_pad(
            explode('.', $rate, 2),
            2,
            ''
        );

        return $whole.'.'.str_pad($fraction, 8, '0');
    }

    public function normalize(array $items): array
    {
        $rows = array_map(
            fn ($item) => [
                'product_id' => (int) $item['product_id'],
                'qty' => (int) $item['qty'],
            ],
            $items
        );

        usort(
            $rows,
            fn ($a, $b) => $a['product_id'] <=> $b['product_id']
        );

        return $rows;
    }

    public function create(array $items): array
    {
        $items = $this->normalize($items);

        $products = Product::whereIn(
            'id',
            array_column($items, 'product_id')
        )->get()->keyBy('id');

        $total = 0;

        foreach ($items as $item) {
            $product = $products->get($item['product_id']);

            if (
                ! $product
                || $product->category === 'Soon'
                || $product->stock < $item['qty']
            ) {
                throw ValidationException::withMessages([
                    'items' => 'Some products are unavailable or have insufficient stock.',
                ]);
            }

            $total += $this->cents((string) $product->price)
                * $item['qty'];
        }

        if ($total < 1 || $total > 100000000) {
            throw ValidationException::withMessages([
                'items' => 'PayPal orders must be between JD 0.01 and JD 1,000,000.00.',
            ]);
        }

        $rate = $this->rate();

        $denominator = (int) str_replace('.', '', $rate);

        $usdCents = intdiv(
            $total * 100000000 + intdiv($denominator, 2),
            $denominator
        );

        $quote = [
            'items' => $items,
            'order_amount' => $this->money($total),
            'payment_amount' => $this->money($usdCents),
            'order_currency' => 'JOD',
            'payment_currency' => 'USD',
            'usd_to_jod' => $rate,
            'expires_at' => now()
                ->addMinutes((int) config('checkout.quote_minutes'))
                ->timestamp,
        ];

        return $quote + [
            'quote_token' => Crypt::encryptString(
                json_encode($quote, JSON_THROW_ON_ERROR)
            ),
        ];
    }

    public function verify(
        string $token,
        array $items,
        string $expected
    ): array {
        try {
            $quote = json_decode(
                Crypt::decryptString($token),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'quote_token' => 'Invalid PayPal price quote.',
            ]);
        }

        if (
            ($quote['expires_at'] ?? 0) < now()->timestamp
            || ($quote['items'] ?? []) !== $this->normalize($items)
            || $this->cents($quote['order_amount'])
                !== $this->cents($expected)
        ) {
            throw ValidationException::withMessages([
                'quote_token' => 'Refresh the PayPal quote before continuing.',
            ]);
        }

        return $quote;
    }
}

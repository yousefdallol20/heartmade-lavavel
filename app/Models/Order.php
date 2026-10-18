<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    public const STATUSES = [
        'pending',
        'confirmed',
        'shipped',
        'delivered',
        'cancelled',
    ];

    protected $guarded = [];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'delivered_at' => 'datetime',
    ];

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function checkoutPayment(): HasOne
    {
        return $this->hasOne(CheckoutPayment::class);
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('status', 'delivered');
    }

    protected static function booted(): void
    {
        static::saving(function (Order $order): void {
            $wasDelivered = $order->exists
                && $order->getOriginal('status') === 'delivered';

            if (
                $wasDelivered
                && $order->isDirty('status')
            ) {
                throw new \DomainException(
                    'A delivered order status cannot be changed.'
                );
            }

            if ($wasDelivered && $order->isDirty('delivered_at')) {
                throw new \DomainException(
                    'The delivery date cannot be changed.'
                );
            }

            if (
                $order->status === 'delivered'
                && ! $wasDelivered
            ) {
                $order->delivered_at = now();
            } elseif ($order->status !== 'delivered') {
                $order->delivered_at = null;
            }
        });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutPayment extends Model
{
    protected $guarded = [];

    protected $hidden = [
        'checkout_token_hash',
        'payload_hash',
        'create_request_id',
        'capture_request_id',
    ];

    protected $casts = [
        'order_amount' => 'decimal:2',
        'payment_amount' => 'decimal:2',
        'usd_to_jod' => 'decimal:8',
        'expires_at' => 'datetime',
        'capture_attempted_at' => 'datetime',
        'paid_at' => 'datetime',
        'stock_released_at' => 'datetime',
        'notification_queued_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

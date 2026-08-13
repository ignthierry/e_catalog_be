<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'order_number',
        'total_amount',
        'shipping_cost',
        'grand_total',
        'status',
        'payment_method',
        'payment_status',
        'payment_proof',
        'courier',
        'awb_number',
        'shipping_address',
        'notes',
        'admin_notes',
        'paid_at',
    ];

    protected $casts = [
        'total_amount' => 'float',
        'shipping_cost' => 'float',
        'grand_total' => 'float',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}

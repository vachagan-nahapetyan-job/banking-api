<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'fee_amount',
        'balance_after',
        'idempotency_key',
        'metadata'
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'fee_amount'    => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata'      => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeWithdrawals($query)
    {
        return $query->where('type', 'withdraw');
    }

    public function scopeDateBetween($query, $from, $to)
    {
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }
        return $query;
    }
}

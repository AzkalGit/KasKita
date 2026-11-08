<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    protected $fillable = [
        'member_id', 'period', 'amount', 'status', 'method',
        'proof_path', 'rejection_reason', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(OrganizationMember::class, 'member_id');
    }

    // Transaksi pemasukan otomatis (kosong bila belum lunas)
    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id', 'category_id', 'invoice_id', 'created_by',
        'type', 'amount', 'tanggal', 'description', 'attachment_path',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'tanggal' => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Transaksi otomatis dari iuran tidak boleh diubah/dihapus manual
    public function isLocked(): bool
    {
        return $this->invoice_id !== null;
    }
}
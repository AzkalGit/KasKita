<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    public const STATUS_BELUM = 'belum';

    public const STATUS_MENUNGGU = 'menunggu';

    public const STATUS_LUNAS = 'lunas';

    public const METHOD_TUNAI = 'tunai';

    private const MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

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

    // Tagihan tidak punya organization_id; organisasi dicapai lewat anggota
    public function scopeInOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->whereHas('member', fn ($m) => $m->where('organization_id', $organization->id));
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_LUNAS;
    }

    // '2026-10' -> 'Oktober 2026'
    public static function labelFor(string $period): string
    {
        [$year, $month] = array_pad(explode('-', $period), 2, null);

        $name = self::MONTHS[(int) $month] ?? null;

        return $name ? "{$name} {$year}" : $period;
    }

    public function periodLabel(): string
    {
        return self::labelFor($this->period);
    }
}

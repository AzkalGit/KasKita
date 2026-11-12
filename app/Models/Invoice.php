<?php

namespace App\Models;

use App\Exceptions\PeriodClosedException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Invoice extends Model
{
    public const STATUS_BELUM = 'belum';

    public const STATUS_MENUNGGU = 'menunggu';

    public const STATUS_LUNAS = 'lunas';

    public const METHOD_TUNAI = 'tunai';

    public const METHOD_TRANSFER = 'transfer';

    // Bukti bayar disimpan di disk privat (storage/app/private), tidak pernah lewat disk 'public'
    public const PROOF_DISK = 'local';

    public const PROOF_DIR = 'proofs';

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

    // Ada nama berkas bukti DAN berkasnya benar-benar ada di disk (seeder punya bukti fiktif)
    public function proofAvailable(): bool
    {
        return $this->proof_path !== null
            && Storage::disk(self::PROOF_DISK)->exists($this->proof_path);
    }

    /**
     * Melunasi tagihan + membuat transaksi pemasukan otomatis, dalam satu transaksi DB.
     * Dipakai bersama oleh "Lunas tunai" dan "Setujui pengajuan".
     *
     * @param  ?string  $method  null = pertahankan metode yang sudah ada di tagihan
     * @param  array  $from  status asal yang diizinkan
     * @return ?self tagihan yang dilunasi, atau null bila statusnya tidak cocok
     */
    public static function settle(int $id, Organization $organization, User $actor, ?string $method, array $from): ?self
    {
        return DB::transaction(function () use ($id, $organization, $actor, $method, $from) {
            $locked = self::whereKey($id)->lockForUpdate()->first();

            if (! $locked || ! in_array($locked->status, $from, true)) {
                return null;
            }

            // F2: bulan tempat transaksi iuran dicatat (hari ini) tidak boleh sudah ditutup
            $tanggal = now()->toDateString();
            if ($organization->isPeriodClosed($tanggal)) {
                throw new PeriodClosedException(substr($tanggal, 0, 7));
            }

            $locked->update([
                'status' => self::STATUS_LUNAS,
                'method' => $method ?? $locked->method,
                'paid_at' => now(),
                'rejection_reason' => null,
            ]);

            $category = $organization->categories()->firstOrCreate(['name' => 'Iuran', 'is_system' => true]);

            // Keterangan sengaja tanpa nama anggota (D2): semua anggota bisa melihat daftar transaksi
            Transaction::create([
                'organization_id' => $organization->id,
                'category_id' => $category->id,
                'invoice_id' => $locked->id,
                'created_by' => $actor->id,
                'type' => 'masuk',
                'amount' => $locked->amount,
                'tanggal' => $tanggal,
                'description' => 'Iuran '.self::labelFor($locked->period),
            ]);

            return $locked;
        });
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

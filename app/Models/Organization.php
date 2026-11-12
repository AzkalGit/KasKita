<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'status',
        'opening_balance',
        'default_dues',
        'payment_info',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'integer',
            'default_dues' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function periodClosures(): HasMany
    {
        return $this->hasMany(PeriodClosure::class);
    }

    // F2: apakah bulan dari tanggal ini ('Y-m-d' atau 'Y-m') sudah ditutup bukunya?
    public function isPeriodClosed(string $date): bool
    {
        return $this->periodClosures()->where('period', substr($date, 0, 7))->exists();
    }

    /**
     * Hapus organisasi beserta seluruh datanya.
     *
     * Urutannya penting karena foreign key sengaja memakai restrictOnDelete:
     * transaksi menunjuk ke tagihan, dan tagihan menunjuk ke anggota. Tanpa urutan ini,
     * $organization->delete() gagal bila organisasi sudah punya tagihan.
     */
    public function purge(): void
    {
        DB::transaction(function () {
            $this->transactions()->withTrashed()->forceDelete();
            Invoice::whereIn('member_id', $this->members()->select('id'))->delete();
            $this->delete(); // anggota, kategori, undangan, penutupan buku ikut terhapus (cascade)
        });
    }

    public function isActive(): bool
    {
        return $this->status === 'aktif';
    }
}

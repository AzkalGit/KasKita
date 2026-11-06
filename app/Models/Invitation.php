<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invitation extends Model
{
    const UPDATED_AT = null; // tabel hanya punya created_at

    // Tanpa 0/O/1/I agar kode mudah dibaca dan diketik ulang
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const CODE_LENGTH = 8;

    // Masa berlaku yang boleh dipilih Admin (hari)
    public const DURATIONS = [1, 7, 30];

    // Masa berlaku kode yang dibuat otomatis saat organisasi dibuat (hari)
    public const DEFAULT_DAYS = 7;

    protected $fillable = ['organization_id', 'code', 'expires_at', 'closed_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function uses(): HasMany
    {
        return $this->hasMany(InvitationUse::class);
    }

    /** Kode acak yang belum dipakai kode lain di organisasi mana pun. */
    public static function generateCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /** Buat kode baru untuk sebuah organisasi. $days = null berarti tanpa batas waktu. */
    public static function issue(Organization $organization, ?int $days, ?User $by = null): self
    {
        return static::create([
            'organization_id' => $organization->id,
            'code' => static::generateCode(),
            'expires_at' => $days ? now()->addDays($days) : null,
            'created_by' => $by?->id,
        ]);
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return ! $this->isClosed() && ! $this->isExpired();
    }

    /** 'ditutup' menang atas 'kedaluwarsa': kode yang ditutup Admin tetap tampil sebagai ditutup. */
    public function status(): string
    {
        if ($this->isClosed()) {
            return 'ditutup';
        }

        return $this->isExpired() ? 'kedaluwarsa' : 'aktif';
    }
}

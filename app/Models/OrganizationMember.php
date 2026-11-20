<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OrganizationMember extends Model
{
    protected $fillable = [
        'organization_id',
        'user_id',
        'name',
        'email',
        'phone',
        'role',
        'is_billable',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_billable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // Email disimpan huruf kecil dan kosong menjadi NULL, agar penautan ke akun tidak
    // bergantung pada kolasi database (SQLite membedakan huruf besar/kecil, MySQL tidak).
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null || trim($value) === '' ? null : Str::lower(trim($value)),
        );
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Nama kolomnya member_id, bukan organization_member_id, jadi ditulis eksplisit
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'member_id');
    }

    public function hasAccount(): bool
    {
        return $this->user_id !== null;
    }

    public const ROLE_ADMIN = 'admin';

    public const ROLE_EDITOR = 'editor';

    public const ROLE_ANGGOTA = 'anggota';

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isCreator(): bool
    {
        return $this->user_id !== null
            && (int) $this->user_id === (int) $this->organization?->created_by;
    }

    // Aturan "keluar seperti grup WA": Admin boleh keluar bila ada Admin lain yang aktif,
    // atau bila tidak ada anggota berakun lain yang aktif. Selain itu harus mengangkat
    // Admin pengganti dulu. Kontak tanpa akun tidak dihitung (tidak bisa jadi Admin).
    // Mengembalikan alasan penolakan, atau null bila boleh keluar.
    public function leaveBlockedReason(): ?string
    {
        if (! $this->hasRole(self::ROLE_ADMIN)) {
            return null;
        }

        $others = self::where('organization_id', $this->organization_id)
            ->where('is_active', true)
            ->whereNotNull('user_id')
            ->where('id', '!=', $this->id);

        if ((clone $others)->where('role', self::ROLE_ADMIN)->exists()) {
            return null;
        }

        if ($others->exists()) {
            return 'Kamu satu-satunya Admin. Angkat Editor atau Anggota lain menjadi Admin dulu sebelum keluar.';
        }

        return null;
    }

    // Dinonaktifkan, bukan dihapus: riwayat tagihan tetap utuh
    public function deactivate(): void
    {
        $this->update(['is_active' => false, 'role' => null]);
    }

    // Yang punya akun kembali jadi Anggota; kontak tanpa akun tetap tanpa peran
    public function reactivate(): void
    {
        $this->update([
            'is_active' => true,
            'role' => $this->hasAccount() ? self::ROLE_ANGGOTA : null,
        ]);
    }
}

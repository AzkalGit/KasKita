<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    // platform_role sengaja TIDAK mass-assignable: isi lewat forceFill/forceCreate (mis. di seeder)
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        // Akun dihapus: keanggotaannya tidak boleh "yatim" dengan peran yang masih menempel.
        // (user_id di-null-kan oleh foreign key; riwayat tagihan tetap utuh.)
        static::deleting(function (User $user) {
            $user->memberships()->update(['role' => null, 'is_active' => false]);
        });
    }

    // Satu akun bisa punya banyak keanggotaan (di banyak organisasi)
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    // Organisasi yang dibuat oleh akun ini
    public function createdOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'created_by');
    }

    public function isOwner(): bool
    {
        return $this->platform_role === 'owner';
    }

    /**
     * Hubungkan akun ini ke data anggota (kontak) yang emailnya sama dan belum punya akun.
     * Hanya dipanggil setelah email TERVERIFIKASI, supaya orang tidak bisa mengklaim
     * keanggotaan hanya dengan mengetik email milik orang lain.
     */
    public function linkPendingMemberships(): int
    {
        return OrganizationMember::whereNull('user_id')
            ->where('is_active', true) // baris nonaktif (mis. bekas akun yang dihapus) tidak dihidupkan lagi
            ->where('email', Str::lower($this->email))
            ->whereNotIn('organization_id', $this->memberships()->select('organization_id')) // hindari duplikat (organisasi, akun)
            ->update([
                'user_id' => $this->id,
                'role' => DB::raw("COALESCE(role, 'anggota')"),
            ]);
    }

    /**
     * Organisasi yang akan kehilangan semua Admin aktifnya bila akun ini dihapus.
     */
    public function organizationsWhereSoleAdmin(): Collection
    {
        return Organization::whereIn('id', $this->memberships()
            ->where('role', 'admin')
            ->where('is_active', true)
            ->select('organization_id'))
            ->whereDoesntHave('members', fn ($q) => $q
                ->where('role', 'admin')
                ->where('is_active', true)
                ->where('user_id', '!=', $this->id))
            ->get();
    }
}

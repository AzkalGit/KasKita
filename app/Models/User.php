<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'platform_role',
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
}
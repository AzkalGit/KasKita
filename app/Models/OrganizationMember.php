<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
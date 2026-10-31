<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function isActive(): bool
    {
        return $this->status === 'aktif';
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invitation extends Model
{
    const UPDATED_AT = null; // tabel hanya punya created_at

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

    public function isUsable(): bool
    {
        return $this->closed_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialActivity extends Model
{
    protected $fillable = [
        'commercial_user_id',
        'user_id',
        'client_id',
        'client_name',
        'prospect_name',
        'type',
        'date',
        'time',
        'objective',
        'result',
        'commentary',
    ];

    protected $casts = [
        'date' => 'date',
        'time' => 'string',
    ];

    protected $appends = [
        'commercial_name',
        'creator_name',
        'created_by_role',
    ];

    public function commercialUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commercial_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function getCommercialNameAttribute(): ?string
    {
        return $this->commercialUser?->employee?->name
            ?? $this->commercialUser?->name
            ?? null;
    }

    public function getCreatorNameAttribute(): ?string
    {
        return $this->creator?->employee?->name
            ?? $this->creator?->name
            ?? null;
    }

    public function getCreatedByRoleAttribute(): ?string
    {
        return $this->creator?->employee?->role?->name
            ?? null;
    }
}

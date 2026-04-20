<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Account extends Model
{
    protected $fillable = [
        'client_id',
        'email',
        'password',
        'campus_password',
        'parcoursup_password',
    ];

    protected $hidden = [
        'password',
        'campus_password',
        'parcoursup_password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'campus_password' => 'hashed',
            'parcoursup_password' => 'hashed',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}

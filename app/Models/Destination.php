<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Destination extends Model
{
    protected $fillable = [
        'name', 'region', 'type_compte', 'montant_total',
        'frais_accompagnement', 'frais_campus_france', 'frais_visa',
    ];

    protected function casts(): array
    {
        return [
            'montant_total' => 'decimal:2',
            'frais_accompagnement' => 'decimal:2',
            'frais_campus_france' => 'decimal:2',
            'frais_visa' => 'decimal:2',
        ];
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function isFrance(): bool
    {
        return strcasecmp(trim($this->name), 'France') === 0;
    }
}

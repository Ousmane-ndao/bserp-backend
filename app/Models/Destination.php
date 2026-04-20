<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Destination extends Model
{
    protected $fillable = ['name', 'region', 'type_compte'];

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function isFrance(): bool
    {
        return strcasecmp(trim($this->name), 'France') === 0;
    }
}

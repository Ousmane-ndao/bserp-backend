<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAccount extends Model
{
    protected $fillable = [
        'client_id',
        'email',
        'email_password',
        'campus_password',
        'parcoursup_password',
    ];

    /**
     * Chiffrement réversible (clé APP_KEY) pour permettre l’affichage contrôlé côté ERP.
     * Ne pas confondre avec un hash bcrypt (irréversible).
     */
    protected function casts(): array
    {
        return [
            'email_password' => 'encrypted',
            'campus_password' => 'encrypted',
            'parcoursup_password' => 'encrypted',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}

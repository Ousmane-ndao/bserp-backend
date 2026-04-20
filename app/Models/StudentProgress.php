<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProgress extends Model
{
    protected $table = 'student_progress';

    protected $fillable = [
        'client_id',
        'lettre_motivation',
        'bulletins_enregistres',
        'travail_effectue',
        'notes_saisies',
    ];

    protected function casts(): array
    {
        return [
            'lettre_motivation' => 'boolean',
            'bulletins_enregistres' => 'boolean',
            'travail_effectue' => 'boolean',
            'notes_saisies' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}

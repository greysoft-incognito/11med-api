<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A drug class for the allergy cross-check, and the names in it (app handover §1.1).
 */
class DrugClass extends Model
{
    protected $fillable = [
        'code',
        'name',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(DrugClassMember::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A drug name, lower case, within a class. Reference data, not a record: no public id.
 */
class DrugClassMember extends Eloquent
{
    protected $fillable = [
        'drug_class_id',
        'name',
    ];

    public function drugClass(): BelongsTo
    {
        return $this->belongsTo(DrugClass::class);
    }
}

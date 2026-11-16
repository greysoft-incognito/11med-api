<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Base for every 11med domain model: a public UUID and soft deletes.
 */
abstract class Model extends Eloquent
{
    use HasPublicId;
    use SoftDeletes;
}

<?php

namespace App\Services\Pharmacy;

use App\Models\User;
use Illuminate\Container\Attributes\Bind;

/**
 * The medicines a patient holds a valid, unexpired 11med prescription for (app spec §8.2), for
 * the cart's `blocked_items`. The Consultations module (M10) implements it.
 */
#[Bind(NoPrescribedDrugs::class)]
interface PrescribedDrugs
{
    /**
     * The prescribed drug names, trimmed and lower-cased, matched against a medicine's name.
     *
     * @return list<string>
     */
    public function names(User $patient): array;
}

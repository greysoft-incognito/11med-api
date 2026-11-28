<?php

namespace App\Services\Identity\Export;

use App\Models\User;

/**
 * One module's share of a person's data export. Each module writes its own tables, through
 * the writer, so no module reads another's models. Implementations are tagged `personal-data`.
 */
interface PersonalData
{
    public function export(User $user, PersonalDataWriter $writer): void;
}

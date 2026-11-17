<?php

namespace App\Enums;

/**
 * `GENOTYPES` (app `src/constants/enums.ts`), screened for sickle cell.
 */
enum Genotype: string
{
    case AA = 'AA';
    case AS = 'AS';
    case SS = 'SS';
    case AC = 'AC';
    case SC = 'SC';
}

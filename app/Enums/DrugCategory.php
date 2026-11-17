<?php

namespace App\Enums;

/**
 * A store medicine's category (app `zDrugCategory`, portal `DRUG_CATEGORIES`).
 */
enum DrugCategory: string
{
    case Antibiotics = 'antibiotics';
    case Analgesics = 'analgesics';
    case Antimalarials = 'antimalarials';
    case Antihypertensives = 'antihypertensives';
    case Antidiabetics = 'antidiabetics';
    case Vitamins = 'vitamins';
    case Antihistamines = 'antihistamines';
    case Other = 'other';
}

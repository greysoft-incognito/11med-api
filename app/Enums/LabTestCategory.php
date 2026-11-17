<?php

namespace App\Enums;

/**
 * A catalogue test's `category` (app and portal `LAB_TEST_CATEGORIES`).
 */
enum LabTestCategory: string
{
    case Haematology = 'haematology';
    case Chemistry = 'chemistry';
    case Microbiology = 'microbiology';
    case Serology = 'serology';
    case Hormones = 'hormones';
    case Urinalysis = 'urinalysis';
    case Other = 'other';
}

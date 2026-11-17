<?php

namespace App\Enums;

/**
 * What a test is run on (`SAMPLE_TYPES`).
 */
enum SampleType: string
{
    case Blood = 'blood';
    case Urine = 'urine';
    case Stool = 'stool';
    case Swab = 'swab';
    case Other = 'other';
}

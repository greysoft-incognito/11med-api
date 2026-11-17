<?php

namespace App\Enums;

/**
 * A doctor's specialty (app `zSpecialty`, concept §4.3).
 */
enum Specialty: string
{
    case GeneralPractice = 'general_practice';
    case Cardiology = 'cardiology';
    case Endocrinology = 'endocrinology';
    case Nephrology = 'nephrology';
    case Obstetrics = 'obstetrics';
    case Paediatrics = 'paediatrics';
    case Psychiatry = 'psychiatry';
    case Other = 'other';
}

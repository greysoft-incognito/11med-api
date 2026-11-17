<?php

namespace App\Enums;

/**
 * Where the sample is taken: at the laboratory, or at the patient's door (`LAB_COLLECTIONS`).
 */
enum LabCollection: string
{
    case Visit = 'visit';
    case Home = 'home';
}

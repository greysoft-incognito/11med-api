<?php

namespace App\Enums;

/**
 * A language a doctor consults in (app `zConsultLanguage`, concept §4.3).
 */
enum ConsultLanguage: string
{
    case English = 'english';
    case Yoruba = 'yoruba';
    case Hausa = 'hausa';
    case Igbo = 'igbo';
}

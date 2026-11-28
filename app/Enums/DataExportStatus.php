<?php

namespace App\Enums;

/**
 * A data export's `status` (app `zDataExport`).
 */
enum DataExportStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';
}

<?php

namespace App\Domains\Banking\Enums;

/** Bank statement import format. */
enum CamtFormat: string
{
    case Camt053 = 'camt053';
    case Camt054 = 'camt054';
    case Csv = 'csv';
    case Mt940 = 'mt940';
}

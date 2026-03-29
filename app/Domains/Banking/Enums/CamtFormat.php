<?php

namespace App\Domains\Banking\Enums;

/** CAMT XML format variant (camt.053 statement vs. camt.054 notification). */
enum CamtFormat: string
{
    case Camt053 = 'camt053';
    case Camt054 = 'camt054';
}

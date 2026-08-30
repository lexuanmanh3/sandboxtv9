<?php

namespace App\Enums;

enum KetQuaNhaCungCap: string
{
    case SUCCESS = 'SUCCESS';
    case DEFINITIVE_FAILURE = 'DEFINITIVE_FAILURE';
    case UNKNOWN_OR_PENDING = 'UNKNOWN_OR_PENDING';
}

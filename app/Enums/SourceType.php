<?php

namespace App\Enums;

enum SourceType: string
{
    case KUTIPAN   = 'KUTIPAN';
    case BAYARAN   = 'BAYARAN';
    case ASET      = 'ASET';
    case JURNAL    = 'JURNAL';
    case REKUPMEN  = 'REKUPMEN';
    case FD        = 'FD';
    case DIVIDEN   = 'DIVIDEN';
    case OPENING   = 'OPENING';
    case CEK_BATAL = 'CEK_BATAL';
    case PELUPUSAN = 'PELUPUSAN';
}

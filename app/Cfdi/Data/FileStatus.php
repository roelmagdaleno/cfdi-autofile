<?php

namespace App\Cfdi\Data;

enum FileStatus: string
{
    case Ok = 'OK';
    case Review = 'REVISAR';
    case Rejected = 'RECHAZADO';
}

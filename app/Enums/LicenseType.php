<?php

namespace App\Enums;

enum LicenseType: string
{
    case Trial = 'trial';
    case Standard = 'standard';
    case Professional = 'professional';
    case Enterprise = 'enterprise';
    case Development = 'development';
}

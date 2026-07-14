<?php

namespace App\Modules\Identity\Domain\Authorization;

enum RoleName: string
{
    case Administrator = 'administrator';
    case Operator = 'operator';
    case Customer = 'customer';
}

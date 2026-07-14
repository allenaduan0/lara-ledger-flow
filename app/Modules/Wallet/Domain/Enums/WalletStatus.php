<?php

namespace App\Modules\Wallet\Domain\Enums;

enum WalletStatus: string
{
    case Active = 'active';
    case Frozen = 'frozen';
}

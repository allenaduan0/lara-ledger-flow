<?php

namespace App\Modules\Wallet\Domain\Exceptions;

use DomainException;

final class FrozenWalletException extends DomainException
{
    public function __construct()
    {
        parent::__construct('The wallet is frozen and cannot perform operations.');
    }
}

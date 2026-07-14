<?php

namespace App\Modules\Transaction\Domain\Exceptions;

use DomainException;

final class InsufficientFundsException extends DomainException
{
    public function __construct()
    {
        parent::__construct('The wallet has insufficient available funds.');
    }
}

<?php

namespace App\Modules\Transaction\Domain\Exceptions;

use DomainException;

final class TransactionProcessingException extends DomainException
{
    public function __construct(public readonly string $transactionId, string $message)
    {
        parent::__construct($message);
    }
}

<?php

namespace App\Modules\Ledger\Domain\Exceptions;

use DomainException;

final class UnbalancedLedgerTransaction extends DomainException
{
    public function __construct()
    {
        parent::__construct('Ledger transaction debits and credits must balance per currency.');
    }
}

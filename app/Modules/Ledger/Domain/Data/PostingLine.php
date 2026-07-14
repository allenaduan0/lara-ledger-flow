<?php

namespace App\Modules\Ledger\Domain\Data;

use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Ledger\Domain\ValueObjects\Money;

final readonly class PostingLine
{
    public function __construct(
        public string $accountId,
        public EntryDirection $direction,
        public Money $money,
    ) {}
}

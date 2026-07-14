<?php

namespace App\Modules\Ledger\Domain\Data;

final readonly class AccountBalance
{
    public function __construct(
        public int $debitsMinor,
        public int $creditsMinor,
        public int $balanceMinor,
        public string $currency,
    ) {}
}

<?php

namespace App\Modules\Wallet\Domain\Data;

final readonly class WalletBalance
{
    public function __construct(
        public ?int $postedMinor,
        public ?int $availableMinor,
        public string $currency,
        public string $source = 'ledger',
    ) {}
}

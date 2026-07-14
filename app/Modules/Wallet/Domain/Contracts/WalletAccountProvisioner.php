<?php

namespace App\Modules\Wallet\Domain\Contracts;

interface WalletAccountProvisioner
{
    public function provision(string $walletId, string $currencyCode): void;
}

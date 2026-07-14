<?php

namespace App\Modules\Ledger\Application\Services;

use App\Modules\Ledger\Domain\Data\PostingLine;
use App\Modules\Ledger\Infrastructure\Persistence\Models\LedgerTransaction;

final class LedgerService
{
    public function __construct(private readonly LedgerPostingService $posting) {}

    /** @param list<PostingLine> $lines */
    public function post(string $reference, array $lines, ?string $description = null): LedgerTransaction
    {
        return $this->posting->post($reference, $lines, $description);
    }
}

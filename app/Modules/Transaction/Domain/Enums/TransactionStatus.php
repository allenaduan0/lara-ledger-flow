<?php

namespace App\Modules\Transaction\Domain\Enums;

enum TransactionStatus: string
{
    case Created = 'created';
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Reversed = 'reversed';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Created => [self::Pending, self::Failed],
            self::Pending => [self::Processing, self::Failed],
            self::Processing => [self::Completed, self::Failed],
            self::Completed => [self::Reversed],
            self::Failed => [self::Reversed],
            self::Reversed => [],
        }, true);
    }
}

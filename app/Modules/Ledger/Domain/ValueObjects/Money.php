<?php

namespace App\Modules\Ledger\Domain\ValueObjects;

use InvalidArgumentException;
use OverflowException;

final readonly class Money
{
    private function __construct(public int $minor, public string $currency) {}

    public static function fromDecimal(string $amount, string $currency, int $minorUnit): self
    {
        if ($minorUnit < 0 || $minorUnit > 6 || ! preg_match('/^(0|[1-9]\d*)(?:\.(\d+))?$/', $amount, $matches)) {
            throw new InvalidArgumentException('Amount must be a positive decimal string valid for the currency.');
        }

        $fraction = $matches[2] ?? '';
        if (strlen($fraction) > $minorUnit) {
            throw new InvalidArgumentException('Amount has more fractional digits than the currency permits.');
        }

        $digits = ltrim($matches[1].str_pad($fraction, $minorUnit, '0'), '0') ?: '0';
        $max = (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($max) || (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0)) {
            throw new OverflowException('Amount exceeds the supported ledger range.');
        }

        $minor = (int) $digits;
        if ($minor <= 0) {
            throw new InvalidArgumentException('Ledger amounts must be greater than zero.');
        }

        return new self($minor, strtoupper($currency));
    }

    public static function fromMinor(int $minor, string $currency): self
    {
        if ($minor <= 0) {
            throw new InvalidArgumentException('Ledger amounts must be greater than zero.');
        }

        return new self($minor, strtoupper($currency));
    }
}

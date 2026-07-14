<?php

use App\Modules\Ledger\Domain\ValueObjects\Money;

it('converts decimal strings to exact currency minor units', function () {
    expect(Money::fromDecimal('100.05', 'EUR', 2)->minor)->toBe(10005)
        ->and(Money::fromDecimal('100', 'JPY', 0)->minor)->toBe(100)
        ->and(Money::fromDecimal('1.234', 'BHD', 3)->minor)->toBe(1234);
});

it('rejects zero, excessive precision, and overflowing values', function (string $amount, int $minorUnit, string $exception) {
    expect(fn () => Money::fromDecimal($amount, 'USD', $minorUnit))->toThrow($exception);
})->with([
    ['0', 2, InvalidArgumentException::class],
    ['1.001', 2, InvalidArgumentException::class],
    ['999999999999999999999999999999', 2, OverflowException::class],
]);

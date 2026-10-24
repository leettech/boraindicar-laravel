<?php

use BoraIndicar\Laravel\Discount;

it('turns basis points into a percentage', function () {
    expect((new Discount(1000, 12))->percent())->toBe(10.0)
        ->and((new Discount(1250, 3))->percent())->toBe(12.5);
});

it('names the coupon after the combination', function (int $bp, int $months, string $id) {
    expect((new Discount($bp, $months))->couponId())->toBe($id);
})->with([
    'whole' => [1000, 12, 'boraindicar-10pct-12m'],
    'fractional' => [1250, 3, 'boraindicar-12_5pct-3m'],
    'two decimals' => [3333, 6, 'boraindicar-33_33pct-6m'],
]);

it('labels the discount in pt-BR', function () {
    expect((new Discount(1000, 12))->label())->toBe('boraindicar: 10% por 12 meses')
        ->and((new Discount(1250, 1))->label())->toBe('boraindicar: 12,5% por 1 mês');
});

it('reads the hub discount object', function () {
    expect(Discount::fromHub(['percent_bp' => 1000, 'months' => 12]))->toEqual(new Discount(1000, 12))
        ->and(Discount::fromHub(null))->toBeNull()
        ->and(Discount::fromHub(['percent_bp' => 0, 'months' => 12]))->toBeNull()
        ->and(Discount::fromHub(['percent_bp' => 1000]))->toBeNull();
});

it('rejects an impossible discount', function (int $bp, int $months) {
    expect(fn () => new Discount($bp, $months))->toThrow(InvalidArgumentException::class);
})->with([[0, 12], [10001, 12], [1000, 0]]);

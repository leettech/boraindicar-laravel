<?php

use BoraIndicar\Laravel\Support\RelativeTime;
use Illuminate\Support\Carbon;

beforeEach(fn () => Carbon::setTestNow('2026-03-01 12:00:00'));
afterEach(fn () => Carbon::setTestNow());

it('says agora for anything under a minute', function () {
    expect(RelativeTime::of(Carbon::now()))->toBe('agora')
        ->and(RelativeTime::of(Carbon::now()->subSeconds(59)))->toBe('agora')
        ->and(RelativeTime::of('2026-03-01 12:00:30'))->toBe('agora');
});

it('falls back to diffForHumans from a minute on', function () {
    $at = Carbon::now()->subMinutes(5);

    expect(RelativeTime::of($at))->toBe($at->diffForHumans());
});

it('keeps null as null', function () {
    expect(RelativeTime::of(null))->toBeNull();
});

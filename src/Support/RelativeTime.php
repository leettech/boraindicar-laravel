<?php

namespace BoraIndicar\Laravel\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * How long ago something happened, for the panel: "agora" under a minute
 * (Carbon would say "há 0 segundo"), Carbon's diffForHumans() otherwise.
 */
final class RelativeTime
{
    public static function of(DateTimeInterface|string|null $at): ?string
    {
        if ($at === null) {
            return null;
        }

        $at = Carbon::parse($at);

        return abs($at->diffInSeconds()) < 60 ? 'agora' : $at->diffForHumans();
    }
}

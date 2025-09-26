<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class RateLimiter
{
    protected static int $count = 0;
    protected static int $timestamp = 0;

    public function hit(): void
    {
        $minute = intval(time() / 60);

        if ($minute !== static::$timestamp) {
            static::$timestamp = $minute;
            static::$count = 0;
        }

        static::$count++;

        if (static::$count >= 290) {
            $sleep = 60 - (time() % 60);
            \Log::warning("API limit reached, sleeping {$sleep}s");
            sleep($sleep);
        }
    }
}
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class RateLimiter
{
    protected string $key;

    public function __construct(string $name = 'default')
    {
        $this->key = "rate_limiter:{$name}";
    }

    public function hit(): void
    {
        $currentMinute = (int) floor(time() / 60);
        $cacheKey = "{$this->key}:{$currentMinute}";

        $count = Cache::get($cacheKey, 0);
        $count++;

        Cache::put($cacheKey, $count, 120);
        if ($count % 10 === 0) {
            \Log::info("Уже сделано $count запросов");
        }
        if ($count >= 290) {
            $sleepSeconds = 60 - (time() % 60);
            if ($sleepSeconds > 0) {
                \Log::warning("Обращение к API ограничено {$sleepSeconds}s");
                sleep($sleepSeconds);
            }
        } else {
            // Минимальная задержка между запросами
            usleep(0.2 * 100 * 1000);
        }
    }
}

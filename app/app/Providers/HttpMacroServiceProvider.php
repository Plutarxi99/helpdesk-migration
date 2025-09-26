<?php

namespace App\Providers;

use App\Services\RateLimiter;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class HttpMacroServiceProvider extends ServiceProvider
{
    /**
     * Загрузка макросов
     */
    public function boot(): void
    {
        $rate_limiter_main = new RateLimiter('main');
        $rate_limiter_egor = new RateLimiter('egor');

        Http::macro('HelpDesk', function () use ($rate_limiter_main): PendingRequest {
            return Http::baseUrl(config('services.help_desk.domain'))
                ->timeout(30)
                ->retry(3, 100)
                ->withHeaders([
                    'User-Agent' => config('app.name') . '/1.0',
                    'Accept' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode(config('services.help_desk.api_key')),
                ])
                ->beforeSending(function () use ($rate_limiter_main) {
                    $rate_limiter_main->hit();
                });
        });

        Http::macro('HelpDeskEgor', function () use ($rate_limiter_egor): PendingRequest {
            return Http::baseUrl(config('services.help_desk.domain_egor'))
                ->timeout(30)
                ->retry(3, 100)
                ->withHeaders([
                    'User-Agent' => config('app.name') . '/1.0',
                    'Accept' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode(config('services.help_desk.api_key_egor')),
                ])
                ->beforeSending(function () use ($rate_limiter_egor) {
                    $rate_limiter_egor->hit();
                });
        });
    }
}
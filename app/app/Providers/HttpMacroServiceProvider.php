<?php

namespace App\Providers;

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
        Http::macro('HelpDesk', function (): PendingRequest {
            return Http::baseUrl(config('services.help_desk.domain'))
                ->timeout(30)
                ->retry(3, 100)
                ->withHeaders(
                    [
                        'User-Agent' => config('app.name').'/1.0',
                        'Accept' => 'application/json',
                        'Authorization' => "Basic " . base64_encode(config('services.help_desk.api_key')),
                    ]
                );
            }
        );
    }
}

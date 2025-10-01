<?php

namespace App\Providers;

use App\Services\RateLimiter;
use GuzzleHttp\HandlerStack;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Psr\Http\Message\ResponseInterface;

class HttpMacroServiceProvider extends ServiceProvider
{
    /**
     * Загрузка макросов
     */
    public function boot(): void
    {
        Http::macro('HelpDesk', function (): PendingRequest {
            $stack = HandlerStack::create();

            // добавляем middleware
            $stack->push(function (callable $handler) {
                return function ($request, array $options) use ($handler) {
                    return $handler($request, $options)->then(function (ResponseInterface $response) {
                        $remaining = (int) $response->getHeaderLine('X-Rate-Limit-Remaining') ?: 300;

                        Log::warning('Сколько осталось запросов', [
                            'remaining' => $remaining,
                        ]);

                        if ($remaining < 10) {
                            $sleepSeconds = 60 - (time() % 60);
                            Log::warning("Скорость API почти исчерпана, спим {$sleepSeconds} сек");
                            sleep($sleepSeconds);
                        }

                        return $response;
                    });
                };
            });

            return Http::baseUrl(config('services.help_desk.domain'))
                ->timeout(30)
                ->retry(3, 100)
                ->withHeaders(
                    [
                        'User-Agent' => config('app.name') . '/1.0',
                        'Accept' => 'application/json',
                        'Authorization' => 'Basic ' . base64_encode(config('services.help_desk.api_key')),
                    ]
                )->withOptions(['handler' => $stack]);
            }
        );

        Http::macro('HelpDeskEgor', function (): PendingRequest {
            $stack = HandlerStack::create();

            $stack->push(function (callable $handler) {
                return function ($request, array $options) use ($handler) {
                    return $handler($request, $options)->then(function (ResponseInterface $response) {
                        $remaining = (int) $response->getHeaderLine('X-Rate-Limit-Remaining') ?: 300;

                        Log::warning('Сколько осталось запросов',
                            [
                                'remaining' => $remaining,
                            ]
                        );

                        if ($remaining < 10) {
                            $sleepSeconds = 60 - (time() % 60);
                            Log::warning("Скорость API почти исчерпана, спим {$sleepSeconds} сек");
                            sleep($sleepSeconds);
                        }

                            return $response;
                        }
                        );
                    };
                }
            );

            return Http::baseUrl(config('services.help_desk.domain_egor'))
                ->timeout(30)
                ->retry(3, 100)
                ->withHeaders(
                    [
                        'User-Agent' => config('app.name') . '/1.0',
                        'Accept' => 'application/json',
                        'Authorization' => 'Basic ' . base64_encode(config('services.help_desk.api_key_egor')),
                    ]
                )->withOptions(
                    [
                        'handler' => $stack,
                    ]
                );
            }
        );
    }
}

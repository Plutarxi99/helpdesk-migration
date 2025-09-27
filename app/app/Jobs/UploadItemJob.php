<?php

namespace App\Jobs;

use App\Enums\SendEnum;
use App\Enums\TableSourceEnum;
use App\Models\TableForMigration;
use App\Repository\ApiHelpDeskUploadResource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Загрузка Пользователя и заявки
 */
class UploadItemJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        private readonly TableForMigration $item,
        private readonly TableSourceEnum   $source,
        private string                     $endpoint
    ) {}

    /**
     * Запуск Job
     *
     * @param ApiHelpDeskUploadResource $repository
     * @return void
     */
    public function handle(ApiHelpDeskUploadResource $repository): void
    {
        try {
            $payload = $repository->mappingPayload($this->source, $this->item->json_data);

            $response = Http::HelpDeskEgor()->post($this->endpoint, $payload);

            if ($response->successful()) {
                $this->item->update(['is_send' => SendEnum::SEND, 'error_message' => null]);
                Log::info(
                    "Успешно загружен элемент",
                    [
                        'id' => $this->item->id_table_for_migrations,
                        'type' => $this->source->value
                    ]
                );
            } else {
                $this->item->update(['error_message' => json_encode($response->json())]);
                Log::error(
                    "Ошибка при загрузке",
                    [
                        'id' => $this->item->id_table_for_migrations,
                        'type' => $this->source->value,
                        'status' => $response->status()
                    ]
                );

                // Повторная попытка через 1 минуту
                $this->release(60);
            }
        } catch (Throwable $e) {
            $this->item->update(['error_message' => $e->getMessage()]);
            Log::error(
                "Ошибка в Job",
                [
                    'id' => $this->item->id_table_for_migrations,
                    'error' => $e->getMessage()
                ]
            );

            $this->release(60); // Повтор через 1 минуту
        }
    }

    /**
     * Записывания провала
     *
     * @param Throwable $exception Исключение
     *
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error(
            "Job провалился",
            [
                'id' => $this->item->id_table_for_migrations,
                'error' => $exception->getMessage()
            ]
        );
    }
}
<?php

namespace App\Jobs;

use App\Enums\SendEnum;
use App\Enums\TableSourceEnum;
use App\Models\TableForMigration;
use App\Repository\ApiHelpDeskUploadResource;
use App\Repository\IdMapperRepository;
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
     * @param IdMapperRepository        $mapper
     *
     * @return void
     */
    public function handle(
        ApiHelpDeskUploadResource $repository,
        IdMapperRepository        $mapper,
    ): void
    {
        try {
            $payload = $repository->mappingPayload($this->source, $this->item->json_data);

            $response = Http::HelpDeskEgor()->post($this->endpoint, $payload);

            if ($response->successful()) {
                $json = $response->json()['data'];
                $external_id = $this->item->json_data['id'];
                $local_id = $json['id'];
                $mapper->save($this->source, $external_id, $local_id);

                Log::info(
                    "Успешно сохранен маппер",
                    [
                        'external_id' => $external_id,
                        'local_id' => $local_id
                    ]
                );

                $this->item->update(
                    [
                        'is_send' => SendEnum::SEND,
                        'id_in_new_db' => $local_id,
                        'error_message' => null
                    ]
                );

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
                        'status' => $response->status(),
                    ]
                );
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
            // Если email уже существует
            if (
                $this->source === TableSourceEnum::CONTACTS &&
                str_contains($error, 'Email') &&
                str_contains($error, 'already exists')
            ) {
                $email = $this->item->json_data['email'];
                $existing_user = $repository->findUserByEmail($email);

                if ($existing_user && isset($existing_user['id'])) {
                    $local_id = $existing_user['id'];
                    $mapper->save($this->source, $this->item->json_data['id'], $local_id);

                    $this->item->update(
                        [
                            'is_send' => SendEnum::SEND,
                            'id_in_new_db' => $local_id,
                            'error_message' => null
                        ]
                    );

                    Log::info(
                        "Email уже существует, элемент сопоставлен",
                        ['email' => $email, 'local_id' => $local_id]
                    );

                    return;
                }
            }

            $this->item->update(['error_message' => $e->getMessage()]);

            Log::error(
                "Ошибка в Job",
                [
                    'id' => $this->item->id_table_for_migrations,
                    'json' => $this->item->json_data,
                    'error' => $e->getMessage()
                ]
            );
        }
    }

    /**
     * Записывания провала
     *
     * @param Throwable $exception Исключение
     *
     * @return void
     */
    public function failed(Throwable $exception): void
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

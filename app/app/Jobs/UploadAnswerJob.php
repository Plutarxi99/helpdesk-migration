<?php

namespace App\Jobs;

use App\Enums\SendEnum;
use App\Models\TableForMigration;
use App\Repository\ApiHelpDeskUploadResource;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Загрузка ответов
 */
class UploadAnswerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        private readonly TableForMigration $answer,
        private readonly int               $ticket_id,
        private readonly int               $user_id,
        private readonly array $text_parts
    ) {}

    /**
     * Запуск Job
     *
     * @param ApiHelpDeskUploadResource $repository Репозиторий
     *
     * @return void
     */
    public function handle(ApiHelpDeskUploadResource $repository): void
    {
        try {
            foreach ($this->text_parts as $index => $part) {
                $payload = ['text' => $part, 'user_id' => $this->user_id];
                $response = Http::HelpDeskEgor()->post("tickets/{$this->ticket_id}/posts/", $payload);

                if ($response->failed()) {
                    $this->answer->update(['error_message' => json_encode($response->json())]);
                    throw new Exception("Ошибка загрузки части ответа");
                }
            }

            $this->answer->update(['is_send' => SendEnum::SEND, 'error_message' => null]);
            Log::info('Ответ успешно загружен', ['id' => $this->answer->id_table_for_migrations]);

        } catch (Throwable $e) {
            $this->answer->update(['error_message' => $e->getMessage()]);
            Log::error('Ошибка при загрузке ответа',
                [
                    'id' => $this->answer->id_table_for_migrations,
                    'error' => $e->getMessage()
                ]
            );

            $this->release(60);
        }
    }
}

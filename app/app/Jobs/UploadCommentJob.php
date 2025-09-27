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

class UploadCommentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        private readonly TableForMigration $comment,
        private readonly int $ticket_id
    ) {}

    /**
     * @param ApiHelpDeskUploadResource $repository
     * @return void
     */
    public function handle(ApiHelpDeskUploadResource $repository): void
    {
        try {
            $payload = $repository->mappingPayload(TableSourceEnum::COMMENTS, $this->comment->json_data);

            $response = Http::HelpDeskEgor()->post("tickets/{$this->ticket_id}/comments/", $payload);

            if ($response->successful()) {
                $this->comment->update(['is_send' => SendEnum::SEND, 'error_message' => null]);
                Log::info(
                    'Комментарий успешно создан',
                    [
                        'id' => $this->comment->id_table_for_migrations,
                        'ticket_id' => $this->ticket_id
                    ]
                );
            } else {
                $this->comment->update(['error_message' => json_encode($response->json())]);
                Log::error(
                    'Ошибка создания комментария',
                    [
                        'id' => $this->comment->id_table_for_migrations,
                        'status' => $response->status()
                    ]
                );

                $this->release(60);
            }
        } catch (Throwable $e) {
            $this->comment->update(['error_message' => $e->getMessage()]);
            Log::error(
                'HTTP-ошибка при создании комментария',
                [
                    'id' => $this->comment->id_table_for_migrations,
                    'error' => $e->getMessage()
                ]
            );

            $this->release(60);
        }
    }
}

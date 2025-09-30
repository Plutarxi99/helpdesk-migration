<?php

namespace App\Services;

use App\Enums\SendEnum;
use App\Enums\TableSourceEnum;
use App\Models\TableForMigration;
use App\Repository\ApiHelpDeskUploadResource;
use App\Repository\IdMapperRepository;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Сервис по загрузке переписки
 */
class CorrespondenceService
{
    public function __construct(
        protected ApiHelpDeskUploadResource $repository,
        protected IdMapperRepository $mapper
    ) {}

    /**
     * Загружаем переписку для одного тикета в новой системе
     *
     * @param int $oldTicketId
     * @param bool $stopOnError
     *
     * @return void
     */
    public function uploadConversation(int $oldTicketId, bool $stopOnError = true): void
    {
        // Маппим тикет на новую систему
        $newTicketId = $this->mapper->map(TableSourceEnum::REQUEST, $oldTicketId);
        if (!$newTicketId) {
            Log::error("Не найден маппинг для тикета {$oldTicketId}");
            return;
        }

        // Получаем все записи для тикета по ticket_id внутри json_data
        $items = TableForMigration::query()
            ->whereIn('source', [TableSourceEnum::COMMENTS, TableSourceEnum::ANSWER])
            ->where('table_for_migrations.is_send', SendEnum::NOT_SEND)
            ->get()
            ->filter(fn($item) => $item->json_data['ticket_id'] === $oldTicketId)
            ->sortBy(fn($item) => \DateTime::createFromFormat('H:i:s d.m.Y', $item->json_data['date_created']))
            ->values();

        foreach ($items as $item) {
            try {
                if ($item->source === TableSourceEnum::COMMENTS) {
                    $this->uploadComment($newTicketId, $item);
                }

                if ($item->source === TableSourceEnum::ANSWER) {
                    $userId = $this->mapper->map(TableSourceEnum::CONTACTS, $item->json_data['user_id'], 1);
                    $this->uploadAnswer($newTicketId, $item, $userId);
                }

            } catch (Throwable $e) {
                $item->update(['error_message' => $e->getMessage()]);
                Log::error("Ошибка при загрузке элемента переписки", [
                    'old_ticket_id' => $oldTicketId,
                    'error' => $e->getMessage()
                ]);

                if ($stopOnError) {
                    break;
                }
            }
        }
    }

    /**
     * Загрузка комментария
     *
     * @param int               $ticketId Id заявки к которой надо загрузить
     * @param TableForMigration $comment  Комментарий который надо загрузить
     *
     * @return void
     * @throws Exception
     */
    private function uploadComment(int $ticketId, TableForMigration $comment): void
    {
        $payload = $this->repository->mappingPayload(TableSourceEnum::COMMENTS, $comment->json_data);
        $response = Http::HelpDeskEgor()->post("tickets/{$ticketId}/comments/", $payload);

        $remaining = (int) $response->header('X-Rate-Limit-Remaining', 300);
        Log::warning('Сколько осталось запросов', [
            'remaining' => $remaining,
        ]);
        if ($remaining < 10) {
            $sleepSeconds = 60 - (time() % 60);
            \Log::warning("Скорость API почти исчерпана, спим $sleepSeconds сек");
            sleep($sleepSeconds);
        }

        if ($response->successful()) {
            $comment->update(['is_send' => SendEnum::SEND, 'error_message' => null]);
            Log::info("Комментарий успешно создан", [
                'id' => $comment->id_table_for_migrations,
                'ticket_id' => $ticketId
            ]);
        } else {
            $comment->update(['error_message' => json_encode($response->json())]);
            throw new \Exception("Ошибка создания комментария: {$response->status()}");
        }
    }

    /**
     * Загрузить ответ
     *
     * @param int               $ticketId ID заявки
     * @param TableForMigration $answer   Ответ, который надо загрузить
     * @param int|null          $userId   Id пользователя
     *
     * @return void
     * @throws Exception
     */
    private function uploadAnswer(int $ticketId, TableForMigration $answer, ?int $userId): void
    {
        $data = $answer->json_data;
        $maxLength = 15000;
        $parts = mb_str_split($data['text'], $maxLength);

        foreach ($parts as $part) {
            $payload = [
                'text' => $part,
                'user_id' => $userId ?? $data['user_id']
            ];

            $response = Http::HelpDeskEgor()->post("tickets/{$ticketId}/posts/", $payload);

            $remaining = (int) $response->header('X-Rate-Limit-Remaining', 300);
            Log::warning('Сколько осталось запросов', [
                'remaining' => $remaining,
            ]);
            if ($remaining < 10) {
                $sleepSeconds = 60 - (time() % 60);
                \Log::warning("Скорость API почти исчерпана, спим $sleepSeconds сек");
                sleep($sleepSeconds);
            }

            if ($response->failed()) {
                $answer->update(['error_message' => json_encode($response->json())]);
                throw new Exception("Ошибка загрузки части ответа: {$response->status()}");
            }
        }

        $answer->update(['is_send' => SendEnum::SEND, 'error_message' => null]);
        Log::info("Ответ успешно загружен", [
            'id' => $answer->id_table_for_migrations,
            'ticket_id' => $ticketId
        ]);
    }

    /**
     * Загружаем переписки по диапазону ticket_id внутри json_data
     *
     * @param null|int $fromId От какой заявки
     * @param null|int $toId   До какой заявки
     *
     * @return array
     */
    public function uploadMessages(?int $fromId = null, ?int $toId = null): array
    {
        $query = TableForMigration::query()
            ->whereIn('source', [TableSourceEnum::COMMENTS, TableSourceEnum::ANSWER])
            ->where('table_for_migrations.is_send', SendEnum::NOT_SEND)
            ->get();

        // Группируем по ticket_id внутри json_data
        $ticketsGrouped = $query
            ->filter(fn($item) => ($fromId === null || $item->json_data['ticket_id'] >= $fromId) &&
                ($toId === null || $item->json_data['ticket_id'] <= $toId))
            ->groupBy(fn($item) => $item->json_data['ticket_id']);

        foreach ($ticketsGrouped as $oldTicketId => $items) {
            try {
                $this->uploadConversation((int) $oldTicketId);
            } catch (Throwable $e) {
                Log::error(
                    "Ошибка при загрузке переписки для тикета $oldTicketId",
                    [
                        'error' => $e->getMessage()
                    ]
                );
            }
        }

        return [
            'success' => true,
            'processed' => $ticketsGrouped->count()
        ];
    }
}

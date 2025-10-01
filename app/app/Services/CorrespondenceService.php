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
     * @param int $old_ticket_id
     * @param bool $stop_on_error
     *
     * @return void
     */
    public function uploadConversation(int $old_ticket_id, bool $stop_on_error = true): void
    {
        // Маппим тикет на новую систему
        $new_ticket_id = $this->mapper->map(TableSourceEnum::REQUEST, $old_ticket_id);
        if (!$new_ticket_id) {
            Log::error("Не найден маппинг для тикета {$old_ticket_id}");
            return;
        }

        // Получаем все записи для тикета по ticket_id внутри json_data
        $items = TableForMigration::query()
            ->whereIn('source', [TableSourceEnum::COMMENTS, TableSourceEnum::ANSWER])
            ->where('table_for_migrations.is_send', SendEnum::NOT_SEND)
            ->get()
            ->filter(fn($item) => $item->json_data['ticket_id'] === $old_ticket_id)
            ->sortBy(fn($item) => \DateTime::createFromFormat('H:i:s d.m.Y', $item->json_data['date_created']))
            ->values();

        foreach ($items as $item) {
            try {
                if ($item->source === TableSourceEnum::COMMENTS) {
                    $this->uploadComment($new_ticket_id, $item);
                }

                if ($item->source === TableSourceEnum::ANSWER) {
                    $user_id = $this->mapper->map(TableSourceEnum::CONTACTS, $item->json_data['user_id'], 1);
                    $this->uploadAnswer($new_ticket_id, $item, $user_id);
                }

            } catch (Throwable $e) {
                $item->update(['error_message' => $e->getMessage()]);
                Log::error(
                    "Ошибка при загрузке элемента переписки", 
                    [
                        'old_ticket_id' => $old_ticket_id,
                        'error' => $e->getMessage()
                    ]
                );

                if ($stop_on_error) {
                    break;
                }
            }
        }
    }

    /**
     * Загрузка комментария
     *
     * @param int               $ticket_id Id заявки к которой надо загрузить
     * @param TableForMigration $comment  Комментарий который надо загрузить
     *
     * @return void
     * @throws Exception
     */
    private function uploadComment(int $ticket_id, TableForMigration $comment): void
    {
        $payload = $this->repository->mappingPayload(TableSourceEnum::COMMENTS, $comment->json_data);
        $response = Http::HelpDeskEgor()->post("tickets/{$ticket_id}/comments/", $payload);

        if ($response->successful()) {
            $new_id = $response->json('data.id');
            $comment->update(
                [
                    'is_send' => SendEnum::SEND,
                    'error_message' => null,
                    'id_in_new_db' => $new_id,
                ]
            );
            Log::info(
                "Комментарий успешно создан",
                [
                    'id' => $comment->id_table_for_migrations,
                    'ticket_id' => $ticket_id
                ]
            );
        } else {
            $comment->update(['error_message' => json_encode($response->json())]);
        }
    }

    /**
     * Загрузить ответ
     *
     * @param int               $ticket_id ID заявки
     * @param TableForMigration $answer   Ответ, который надо загрузить
     * @param int|null          $user_id   Id пользователя
     *
     * @return void
     * @throws Exception
     */
    private function uploadAnswer(int $ticket_id, TableForMigration $answer, ?int $user_id): void
    {
        $data = $answer->json_data;
        $max_length = 15000;
        $parts = mb_str_split($data['text'], $max_length);

        $last_id = null;
        foreach ($parts as $part) {
            $payload = [
                'text' => $part,
                'user_id' => $user_id ?? $data['user_id']
            ];

            $response = Http::HelpDeskEgor()->post("tickets/{$ticket_id}/posts/", $payload);

            if ($response->failed()) {
                $answer->update(['error_message' => json_encode($response->json())]);
            }

            // сохраняем id последнего поста
            $last_id = $response->json('data.id');
        }

        if ($last_id !== null) {
            $answer->update([
                'is_send' => SendEnum::SEND,
                'error_message' => null,
                'id_in_new_db' => $last_id,
            ]);

            Log::info(
                "Ответ успешно загружен", 
                [
                    'old_id' => $answer->id_table_for_migrations,
                    'new_id' => $last_id,
                    'ticket_id' => $ticket_id,
                ]
            );
        }
    }

    /**
     * Загружаем переписки по диапазону ticket_id внутри json_data
     *
     * @param null|int $from_id От какой заявки
     * @param null|int $to_id   До какой заявки
     *
     * @return array
     */
    public function uploadMessages(?int $from_id = null, ?int $to_id = null): array
    {
        $query = TableForMigration::query()
            ->whereIn('source', [TableSourceEnum::COMMENTS, TableSourceEnum::ANSWER])
            ->where('table_for_migrations.is_send', SendEnum::NOT_SEND)
            ->get();

        // Группируем по ticket_id внутри json_data
        $ticketsGrouped = $query
            ->filter(fn($item) => ($from_id === null || $item->json_data['ticket_id'] >= $from_id) &&
                ($to_id === null || $item->json_data['ticket_id'] <= $to_id))
            ->groupBy(fn($item) => $item->json_data['ticket_id']);

        foreach ($ticketsGrouped as $old_ticket_id => $items) {
            try {
                $this->uploadConversation((int) $old_ticket_id);
            } catch (Throwable $e) {
                Log::error(
                    "Ошибка при загрузке переписки для тикета $old_ticket_id",
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

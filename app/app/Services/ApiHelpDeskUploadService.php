<?php

namespace App\Services;

use App\Enums\SendEnum;
use App\Enums\TableSourceEnum;
use App\Models\TableForMigration;
use App\Repository\ApiHelpDeskUploadResource;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiHelpDeskUploadService
{
    public function __construct(
        protected ApiHelpDeskUploadResource $repository,
    ) {}

    /**
     * Загружает заявки
     *
     * @param null|int $from_id ID от какого заполнять
     * @param null|int $to_id ID до какого вставлять
     *
     * @return array
     * @throws Exception
     */
    public function uploadRequests(?int $from_id = null, ?int $to_id = null): array
    {
        return $this->uploadItems(
            TableSourceEnum::REQUEST,
            'tickets/',
            'заявок',
            $from_id, $to_id
        );
    }

    /**
     * Загружает пользователей
     *
     * @param null|int $from_id ID от какого заполнять
     * @param null|int $to_id   ID до какого вставлять
     *
     * @return array
     * @throws Exception
     */
    public function uploadContacts(?int $from_id = null, ?int $to_id = null): array
    {
        return $this->uploadItems(
            TableSourceEnum::CONTACTS,
            'users/',
            'пользователей',
            $from_id,
            $to_id
        );
    }

    /**
     * Загружает комментарии
     *
     * @param null|int $from_id ID от какого заполнять
     * @param null|int $to_id ID до какого вставлять
     *
     * @return array
     * @throws Exception
     */
    public function uploadComments(?int $from_id = null, ?int $to_id = null): array
    {
        $saved_count = 0;

        foreach (TableForMigration::getNotSend(TableSourceEnum::COMMENTS, $from_id, $to_id) as $comment) {
            $data = $comment->json_data;
            $ticket_id = $this->repository->mapTicketId($data['ticket_id']);
            $payload = $this->repository->mappingPayload(TableSourceEnum::COMMENTS, $data);

            if ($this->sendRequest("tickets/{$ticket_id}/comments/", $payload, $comment)) {
                $saved_count++;
            }
        }

        return $this->result("Создано {$saved_count} комментариев", $saved_count);
    }

    /**
     * Загружает ответы
     *
     * @param null|int $from_id ID от какого заполнять
     * @param null|int $to_id   ID до какого вставлять
     *
     * @return array
     */
    public function uploadAnswers(?int $from_id = null, ?int $to_id = null): array
    {
        $saved_count = 0;
        $maxTextLength = 15000;

        foreach (TableForMigration::getNotSend(TableSourceEnum::ANSWER, $from_id, $to_id) as $answer) {
            $data = $answer->json_data;
            $ticket_id = $this->repository->mapTicketId($data['ticket_id']);
            $user_id = $this->repository->mapUserId($data['user_id'] ?? null);
            $text_parts = mb_str_split($data['text'], $maxTextLength);

            if ($this->sendAnswerParts($ticket_id, $user_id, $text_parts, $answer)) {
                $saved_count++;
            }
        }

        return $this->result("Загружено {$saved_count} ответов", $saved_count);
    }

    /**
     * Отправка запроса с повторными попытками
     *
     * @param string            $endpoint URL
     * @param array             $payload  Данные для загрузки
     * @param TableForMigration $item     Объекта класса
     *
     * @return bool
     */
    private function sendRequest(string $endpoint, array $payload, TableForMigration $item): bool
    {
        $attempts = 0;
        $max_attempts = 2;

        while ($attempts < $max_attempts) {
            $attempts++;

            try {
                $response = Http::HelpDeskEgor()->post($endpoint, $payload);

                if ($response->successful()) {
                    $item->update(['is_send' => SendEnum::SEND, 'error_message' => null]);
                    Log::info("Успешно загружен элемент", ['id' => $item->id_table_for_migrations]);
                    return true;
                } else {
                    $item->update(['error_message' => json_encode($response->json())]);
                    Log::error("Ошибка при загрузке", ['id' => $item->id_table_for_migrations, 'attempt' => $attempts]);
                }
            } catch (\Throwable $e) {
                $item->update(['error_message' => $e->getMessage()]);
                Log::error("HTTP-ошибка", ['id' => $item->id_table_for_migrations, 'attempt' => $attempts]);
            }

            sleep(1);
        }

        return false;
    }

    /**
     * Общий метод для загрузки простых элементов
     *
     * @param TableSourceEnum $source   Источник таблицы
     * @param string          $endpoint URL
     * @param string          $typeName Тип
     * @param null|int        $from_id  От какого ID заполнять
     * @param null|int        $to_id    До какого ID заполнять
     *
     * @return array
     * @throws Exception
     */
    private function uploadItems(
        TableSourceEnum $source,
        string $endpoint,
        string $typeName,
        ?int $from_id,
        ?int $to_id
    ): array {
        $saved_count = 0;

        foreach (TableForMigration::getNotSend($source, $from_id, $to_id) as $item) {
            $payload = $this->repository->mappingPayload($source, $item->json_data);

            if ($this->sendRequest($endpoint, $payload, $item)) {
                $saved_count++;
            }
        }

        return $this->result("Загружено {$saved_count} {$typeName}", $saved_count);
    }

    /**
     * Отправка частей ответа
     * 
     * @param int               $ticket_id  ID заявки
     * @param int               $user_id    ID пользователя
     * @param array             $text_parts Часть текста
     * @param TableForMigration $answer     Ответ
     * 
     * @return bool
     */
    private function sendAnswerParts(int $ticket_id, int $user_id, array $text_parts, TableForMigration $answer): bool
    {
        $attempts = 0;
        $max_attempts = 2;

        while ($attempts < $max_attempts) {
            $attempts++;

            try {
                foreach ($text_parts as $index => $part) {
                    $payload = ['text' => $part, 'user_id' => $user_id];
                    $response = Http::HelpDeskEgor()->post("tickets/{$ticket_id}/posts/", $payload);

                    if ($response->failed()) {
                        throw new \Exception("Ошибка загрузки части ответа");
                    }
                }

                $answer->update(['is_send' => SendEnum::SEND]);
                Log::info('Ответ успешно загружен', ['id' => $answer->id_table_for_migrations]);
                return true;

            } catch (\Throwable $e) {
                Log::error('Ошибка при загрузке ответа', ['id' => $answer->id_table_for_migrations, 'attempt' => $attempts]);
                sleep(1);
            }
        }

        return false;
    }

    /**
     * Формирование результата
     * 
     * @param string $message Сообщение
     * @param int    $count   Количество
     * 
     * @return array
     */
    private function result(string $message, int $count): array
    {
        return [
            'success' => true,
            'message' => $message,
            'saved_count' => $count
        ];
    }
}

<?php

namespace App\Services;

use App\Enums\TableSourceEnum;
use App\Jobs\UploadAnswerJob;
use App\Jobs\UploadCommentJob;
use App\Jobs\UploadItemJob;
use App\Models\TableForMigration;
use App\Repository\ApiHelpDeskUploadResource;
use Exception;

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
     * @param null|int $to_id   ID до какого вставлять
     *
     * @return array
     * @throws Exception
     */
    public function uploadComments(?int $from_id = null, ?int $to_id = null): array
    {
        $count = 0;

        foreach (TableForMigration::getNotSend(TableSourceEnum::COMMENTS, $from_id, $to_id) as $comment) {
            $data = $comment->json_data;
            $ticket_id = $this->repository->mapTicketId($data['ticket_id']);

            UploadCommentJob::dispatch($comment, $ticket_id);
            $count++;
        }

        return $this->result("Поставлено в очередь {$count} комментариев", $count);
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
        $count = 0;
        $maxTextLength = 15000;

        foreach (TableForMigration::getNotSend(TableSourceEnum::ANSWER, $from_id, $to_id) as $answer) {
            $data = $answer->json_data;
            $ticket_id = $this->repository->mapTicketId($data['ticket_id']);
            $user_id = $this->repository->mapUserId($data['user_id'] ?? null);
            $text_parts = mb_str_split($data['text'], $maxTextLength);

            UploadAnswerJob::dispatch($answer, $ticket_id, $user_id, $text_parts);
            $count++;
        }

        return $this->result("Поставлено в очередь {$count} ответов", $count);
    }

    /**
     * Запуск одного элемента на отправку
     *
     * @param TableSourceEnum $source   Источник
     * @param string          $endpoint URL
     * @param string          $typeName Тип
     * @param int|null        $from_id  ID от чего
     * @param int|null        $to_id    ID до куда
     *
     * @return array
     */
    private function uploadItems(
        TableSourceEnum $source,
        string $endpoint,
        string $typeName,
        ?int $from_id,
        ?int $to_id
    ): array {
        $items = TableForMigration::getNotSend($source, $from_id, $to_id);
        $count = $items->count();

        foreach ($items as $item) {
            UploadItemJob::dispatch($item, $source, $endpoint);
        }

        return $this->result("Поставлено в очередь {$count} {$typeName}", $count);
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
            'queued_count' => $count
        ];
    }
}

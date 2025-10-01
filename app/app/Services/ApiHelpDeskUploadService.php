<?php

namespace App\Services;

use App\Enums\TableSourceEnum;
use App\Jobs\UploadItemJob;
use App\Models\TableForMigration;
use App\Repository\ApiHelpDeskUploadResource;
use App\Repository\IdMapperRepository;
use Exception;

class ApiHelpDeskUploadService
{
    public function __construct(
        protected ApiHelpDeskUploadResource $repository,
        protected IdMapperRepository $mapper,
        protected CorrespondenceService $correspondence,
    ) {
    }

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
            $from_id,
            $to_id
        );
    }

    /**
     * Загружает пользователей
     *
     * @param null|int $from_id ID от какого заполнять
     * @param null|int $to_id ID до какого вставлять
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
     * Загружает переписку по диапазону id_table_for_migrations
     *
     * @param null|int $from_id ID от какого заполнять
     * @param null|int $to_id ID до какого вставлять
     *
     * @return array
     * @throws Exception
     */
    public function uploadMessages(?int $from_id = null, ?int $to_id = null): array
    {
        return $this->correspondence->uploadMessages($from_id, $to_id);
    }

    /**
     * Запуск одного элемента на отправку
     *
     * @param TableSourceEnum $source Источник
     * @param string $endpoint URL
     * @param string $typeName Тип
     * @param int|null $from_id ID от чего
     * @param int|null $to_id ID до куда
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
     * @param int $count Количество
     *
     * @return array
     */
    private function result(string $message, int $count): array
    {
        return [
            'success' => true,
            'message' => $message,
            'queued_count' => $count,
        ];
    }
}

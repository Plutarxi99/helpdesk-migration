<?php

namespace App\Repository;

use App\Enums\TableSourceEnum;
use Exception;

class ApiHelpDeskUploadResource
{
    public function __construct(
        protected IdMapperRepository $mapper
    ) {}

    /**
     * Получить полезные нагрузку в зависимости от источника
     *
     * @param TableSourceEnum $source Источник куда будет загружаться
     * @param array           $data   Данные для загрузки
     *
     * @return array
     * @throws Exception
     */
    public function mappingPayload(TableSourceEnum $source, array $data): array
    {
        return match ($source) {
            TableSourceEnum::REQUEST => $this->getPayloadRequest($data),
            TableSourceEnum::CONTACTS => $this->getPayloadContacts($data),
            TableSourceEnum::COMMENTS => $this->getPayloadComment($data),
            TableSourceEnum::DEPARTMENTS,
            TableSourceEnum::CUSTOM_FIELDS,
            TableSourceEnum::CUSTOM_FIELD_OPTIONS,
            TableSourceEnum::ANSWER =>
                throw new Exception('To be implemented'),
        };
    }

    /**
     * Получить полезные данные для загрузки заявки
     *
     * @param array $data Данные получение для загрузки
     *
     * @return array
     */
    public function getPayloadRequest(array $data): array
    {
        $payload = [
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'status_id' => $data['status_id'] ?? null,
            'priority_id' => $data['priority_id'] ?? null,
            'type_id' => $data['type_id'] ?? null,
            'department_id' => $data['department_id'] ?? 1,
            'owner_id' => $this->mapper->map(TableSourceEnum::CONTACTS, $data['user_id'] ?? null),
            'user_id' => $this->mapper->map(TableSourceEnum::CONTACTS, $data['user_id'] ?? null),
            'user_email' => $data['user_email'] ?? null,
            'tags' => $data['tags'] ?? [],
        ];

        return $this->cleanPayload($payload);
    }

    /**
     * Получить полезные данные для загрузки заявки
     *
     * @param array $data Данные получение для загрузки
     *
     * @return array
     */
    public function getPayloadContacts(array $data): array
    {
        $payload = [
            'name' => $data['name'] ?? null,
            'lastname' => $data['lastname'] ?? null,
            'alias' => $data['alias'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'website' => $data['website'] ?? null,
            'organization' => $data['organization']['name'] ?? null,
            'organiz_id' => $data['organization']['id'] ?? null,
            'status' => $data['status'] ?? 'active',
            'language' => $data['language'] ?? null,
            'notifications' => $data['notifications'] ?? 0,
            'user_status' => $data['user_status'] ?? null,
            'group_id' => $data['group']['id'] ?? 1,
            'department' => $data['department'] ?? [1],
            'custom_fields' => $data['custom_fields'] ?? [],
            'password' => 'password',
        ];

        return $this->cleanPayload($payload);
    }

    /**
     * Получить полезные данные для загрузки Комментария
     *
     * @param array $data Данные получение для загрузки
     *
     * @return array
     */
    public function getPayloadComment(array $data): array
    {
        $payload = [
            'text' => $data['text'],
            'user_id' => $this->mapper->map(TableSourceEnum::CONTACTS, $data['user_id']),
        ];

        if (! empty($data['files'])) {
            $payload['files'] = $data['files'];
        }

        return $payload;
    }

    /**
     * Фильтрует массив, удаляя null и пустые строки
     *
     * @param array $data Массив для очищения от пустых значений
     *
     * @return array
     */
    private function cleanPayload(array $data): array
    {
        return array_filter($data, function($v) {
                if (is_array($v)) {
                        return true;
                    }

                return ! is_null($v) && $v !== '';
            }
        );
    }
}

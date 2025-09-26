<?php

namespace App\Repository;

use App\Enums\TableSourceEnum;
use Exception;

class ApiHelpDeskUploadResource
{
    /**
     * Маппит несуществующие ID заявок
     *
     * @param int $ticketId ID заявок
     *
     * @return int
     */
    public function mapTicketId(int $ticketId): int
    {
        $mapping = [
            844 => 783,
            841 => 783,
            842 => 783,
            843 => 783,
            845 => 782,
            846 => 781,
            847 => 788,
            848 => 788,
            849 => 788,
            840 => 785,
            862 => 786,
            852 => 786,
            853 => 786,
            854 => 786,
            855 => 786,
            856 => 786,
            857 => 786,
            858 => 786,
            859 => 786,
            860 => 786,
            861 => 786,
            863 => 786,
            864 => 786,
            851 => 786,
            850 => 784,
        ];

        return $mapping[$ticketId] ?? $ticketId;
    }

    /**
     * Маппит несуществующие ID пользователей
     *
     * @param int $userId ID пользователя
     *
     * @return int
     */
    public function mapUserId(int $userId): int
    {
        $mapping = [
            2093 => 708,
            2092 => 777,
            1090 => 700,
            1092 => 701,
            1093 => 702,
            1094 => 703,
            1095 => 704,
            1096 => 704,
            1097 => 705,
            1098 => 706,
            1099 => 707,
            -1 => 1,
            -2 => 1
        ];

        return $mapping[$userId] ?? $userId;
    }

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
        return [
            'title' => $data['title'] ?? 'Без названия',
            'description' => !empty($data['description']) ? $data['description'] : 'Без описания',
            'status_id' => $data['status_id'] ?? 'open',
            'priority_id' => $data['priority_id'] ?? 1,
            'type_id' => $data['type_id'] ?? 0,
            'department_id' => $data['department_id'] ?? 1,
            'owner_id' => $data['owner_id'] ?? 0,
            'user_id' => $this->mapUserId($data['user_id']) ?? null,
            'user_email' => $data['user_email'] ?? null,
            'tags' => $data['tags'] ?? [],
        ];
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
        return [
            'name' => $data['name'] ?? 'No Name',
            'lastname' => $data['lastname'] ?? '',
            'alias' => $data['alias'] ?? '',
            'email' => $data['email'],
            'phone' => $data['phone'] ?? '',
            'website' => $data['website'] ?? '',
            'organization' => $data['organization']['name'] ?? null,
            'organiz_id' => $data['organization']['id'] ?? 1,
            'status' => $data['status'] ?? 'active',
            'language' => $data['language'] ?? 'ru',
            'notifications' => $data['notifications'] ?? 0,
            'user_status' => $data['user_status'] ?? 'offline',
            'group_id' => $data['group']['id'] ?? 1,
            'department' => $data['department'] ?? [1],
            'custom_fields' => $data['custom_fields'] ?? [],
            'password' => 'password',
        ];
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
            'user_id' => $this->mapUserId($data['user_id']) ?? 1,
        ];

        if (! empty($data['files'])) {
            $payload['files'] = $data['files'];
        }

        return $payload;
    }
}

<?php

namespace App\Repository;

use App\Enums\TableSourceEnum;
use App\Models\ExternalIdMap;

/**
 * Для работы с маппингом с внешней системы в новую
 */
class IdMapperRepository
{
    /**
     * Получение ID из нашей системы по внешнему ID
     *
     * @param TableSourceEnum $source Источник заявки
     * @param int $external_id Внешний ID
     * @param int|null $default Дефолтное значение
     *
     * @return int|null
     */
    public function map(TableSourceEnum $source, int $external_id, ?int $default = null): ?int
    {
        return ExternalIdMap::getLocalId($source, $external_id) ?? $default ?? $external_id;
    }

    /**
     * Сохранить значение для маппинга
     *
     * @param TableSourceEnum $source Источник переноса
     * @param int $external_id Внешний ID
     * @param int $local_id ID в новой системе
     *
     * @return void
     */
    public function save(TableSourceEnum $source, int $external_id, int $local_id): void
    {
        ExternalIdMap::saveMapping($source, $external_id, $local_id);
    }
}

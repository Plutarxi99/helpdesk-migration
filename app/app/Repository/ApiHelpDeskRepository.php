<?php

namespace App\Repository;

use App\Enums\TableSourceEnum;
use App\Models\TableForMigration;
use InvalidArgumentException;

class ApiHelpDeskRepository
{
    /**
     * Универсальное сохранение данных в TableForMigration
     *
     * @param TableSourceEnum $source Источник данных (REQUEST, CONTACTS, ANSWER и т.д.)
     * @param array           $data   Данные для сохранения
     *
     * @return void
     */
    public function updateOrCreateRow(TableSourceEnum $source, array $data): void
    {
        TableForMigration::query()->firstOrCreate(
            [
                'source' => $source,
                'id_table_for_migrations' => $data['id'],
            ],
            [
                'json_data' => $data,
            ]
        );
    }
}
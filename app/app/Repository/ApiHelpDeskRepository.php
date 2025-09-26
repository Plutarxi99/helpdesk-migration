<?php

namespace App\Repository;

use App\Enums\TableSourceEnum;
use App\Models\TableForMigration;

class ApiHelpDeskRepository
{
    public function saveRequest($request): void
    {
        TableForMigration::query()->create(
            [
                'source' => TableSourceEnum::REQUEST,
                'json_data' => $request,
                'id_table_for_migrations' => $request['id'],
            ]
        );
    }

    public function saveContacts($request): void
    {
        TableForMigration::query()->create(
            [
                'source' => TableSourceEnum::CONTACTS,
                'json_data' => $request,
                'id_table_for_migrations' => $request['id'],
            ]
        );
    }

    public function saveAnswer($request): void
    {
        TableForMigration::query()->firstOrCreate(
            [
                'source' => TableSourceEnum::ANSWER,
                'id_table_for_migrations' => $request['id'],
            ],
            [
                'json_data' => $request,
            ]
        );
    }

    public function saveComment($request): void
    {
        TableForMigration::query()->firstOrCreate(
            [
                'source' => TableSourceEnum::COMMENTS,
                'id_table_for_migrations' => $request['id'],
            ],
            [
                'json_data' => $request,
            ]
        );
    }

    public function saveDepartments($request): void
    {
        TableForMigration::query()->firstOrCreate(
            [
                'source' => TableSourceEnum::DEPARTMENTS,
                'id_table_for_migrations' => $request['id'],
            ],
            [
                'json_data' => $request,
            ]
        );
    }
}
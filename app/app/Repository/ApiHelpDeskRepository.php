<?php

namespace App\Repository;

use App\Enums\TableSourceEnum;
use App\Models\TableForMigration;

class ApiHelpDeskRepository
{
    public function saveRequest($request): void
    {
        TableForMigration::query()->firstOrCreate(
            [
                'source' => TableSourceEnum::REQUEST,
                'id_table_for_migrations' => $request['id'],
            ],
            [
                'json_data' => $request,
            ]
        );
    }

    public function saveContacts($request): void
    {
        TableForMigration::query()->firstOrCreate(
            [
                'source' => TableSourceEnum::CONTACTS,
                'id_table_for_migrations' => $request['id'],
            ],
            [
                'json_data' => $request,
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

    public function saveCustomFields($request): void
    {
        TableForMigration::query()->firstOrCreate(
            [
                'source' => TableSourceEnum::CUSTOM_FIELDS,
                'id_table_for_migrations' => $request['id'],
            ],
            [
                'json_data' => $request,
            ]
        );
    }

    public function saveCustomFieldOption($request): void
    {
        TableForMigration::query()->firstOrCreate(
            [
                'source' => TableSourceEnum::CUSTOM_FIELD_OPTIONS,
                'id_table_for_migrations' => $request['id'],
            ],
            [
                'json_data' => $request,
            ]
        );
    }
}
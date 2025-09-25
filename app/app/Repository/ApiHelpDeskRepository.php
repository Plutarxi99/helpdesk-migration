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
                'unique_id' => $request['unique_id'],
            ]
        );
    }

    public function saveContacts($request): void
    {
        TableForMigration::query()->create(
            [
                'source' => TableSourceEnum::CONTACTS,
                'json_data' => $request,
                'unique_id' => $request['id'],
            ]
        );
    }
}
<?php

namespace App\Http\Controllers;

use App\Enums\TableSourceEnum;
use App\Models\TableForMigration;
use App\Repository\IdMapperRepository;
use App\Services\ApiHelpDeskUploadService;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Класс для загрузки в новую систему данных
 */
class ApiHelpDeskUploadController extends Controller
{
    public function __construct(
        protected readonly ApiHelpDeskUploadService $service,
        protected readonly IdMapperRepository $mapper
    ) {
    }

    /**
     * Загрузка Заявок
     *
     * @return array
     * @throws Exception
     */
    public function uploadRequests(): array
    {
        return $this->service->uploadRequests(1, 900);
    }

    /**
     * Загрузка контактов
     *
     * @return array
     * @throws Exception
     */
    public function uploadContacts(): array
    {
        return $this->service->uploadContacts(1, 2094);
    }

    /**
     * Загрузка сообщений
     *
     * @return array
     * @throws Exception
     */
    public function uploadMessages(): array
    {
        return $this->service->uploadMessages(1, 950);
    }

    /**
     * Обновление статусов у всех заяввок
     *
     * @return array
     * @throws ConnectionException
     */
    public function updatedStatusesRequests(): array
    {
        $items = TableForMigration::query()
            ->where('table_for_migrations.source', TableSourceEnum::REQUEST)
            ->get();

        foreach ($items as $item) {
            $status = $item->json_data['status_id'];
            $id = $item->id_in_new_db;
            $response = Http::HelpDeskEgor()->put("tickets/$id", ['status_id' => $status]);
            if ($response->successful()) {
                Log::info('Была обновлена заявка', ['request_id' => $id, 'status_id' => $status]);
            }
        }

        return [];
    }

    /**
     * Обновление владельцев у всех заявок
     *
     * @return array
     * @throws ConnectionException
     */
    public function updatedOwnerRequests(): array
    {
        $items = TableForMigration::query()
            ->where('table_for_migrations.source', TableSourceEnum::REQUEST)
            ->get();

        foreach ($items as $item) {
            $owner = $item->json_data['owner_id'];
            $id = $item->id_in_new_db;
            if ($owner === 0) {
                $response = Http::HelpDeskEgor()->put("tickets/$id", ['owner_id' => $owner]);
                if ($response->successful()) {
                    Log::info('Была обновлена заявка', ['request_id' => $id, 'owner_id' => $owner]);
                }
            } elseif ($owner === 1) {
                $new_owner = $this->mapper->map(TableSourceEnum::CONTACTS, $owner);
                $response = Http::HelpDeskEgor()->put("tickets/$id", ['owner_id' => $new_owner]);
                if ($response->successful()) {
                    Log::info('Была обновлена заявка', ['request_id' => $id, 'owner_id' => $new_owner]);
                }
            }
        }

        return [];
    }

    /**
     * Обновить фололоверов
     *
     * @return array
     * @throws ConnectionException
     */
    public function updatedFollowersRequests(): array
    {
        $items = TableForMigration::query()
            ->where('table_for_migrations.source', TableSourceEnum::REQUEST)
            ->get();

        foreach ($items as $item) {
            $followers = $item->json_data['followers'];
            $id = $item->id_in_new_db;

            if (! empty($followers)) {
                foreach ($followers as $follower) {
                    $new_followers[] = $this->mapper->map(TableSourceEnum::CONTACTS, $follower);
                }
                if (! empty($new_followers)) {
                    $response = Http::HelpDeskEgor()->put("tickets/$id", ['followers' => $new_followers]);
                    if ($response->successful()) {
                        Log::info('Была обновлена заявка', ['request_id' => $id, 'followers' => $new_followers]);
                    }
                }
            }
        }

        return [];
    }
}

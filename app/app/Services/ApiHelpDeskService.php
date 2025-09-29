<?php

namespace App\Services;

use App\Enums\TableSourceEnum;
use App\Models\TableForMigration;
use App\Repository\ApiHelpDeskRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiHelpDeskService
{
    public function __construct(
        protected ApiHelpDeskRepository $repository
    ) {}

    /**
     * Получение и сохранение заявок
     * 
     * @return array
     */
    public function getRequests(): array
    {
        return $this->getAllPages('tickets/', TableSourceEnum::REQUEST, 'requests');
    }

    /**
     * Получение и сохранение контактов
     * 
     * @return array
     */
    public function getContacts(): array
    {
        return $this->getAllPages('users/', TableSourceEnum::CONTACTS, 'contacts');
    }

    /**
     * Получение и сохранение ответов
     * 
     * @return array
     */
    public function getAnswers(): array
    {
        return $this->getTicketRelatedData('posts/', TableSourceEnum::ANSWER, 'answers');
    }

    /**
     * Получение и сохранение комментариев
     * 
     * @return array
     */
    public function getComments(): array
    {
        return $this->getTicketRelatedData('comments/', TableSourceEnum::COMMENTS, 'comments');
    }

    /**
     * Получение и сохранение отделов
     * 
     * @return array
     */
    public function getDepartments(): array
    {
        $response = Http::HelpDesk()->get('departments/')->json();
        $saved_count = 0;

        foreach ($response['data'] as $item) {
            $this->repository->updateOrCreateRow(TableSourceEnum::DEPARTMENTS, $item);
            $saved_count++;
        }

        return [
            'success' => true,
            'saved_count' => $saved_count
        ];
    }

    /**
     * Получение и сохранение кастомных полей
     * 
     * @return array
     */
    public function getCustomFields(): array
    {
        return $this->getAllPages('custom_fields/', TableSourceEnum::CUSTOM_FIELDS, 'custom fields');
    }

    /**
     * Получение и сохранение опций кастомных полей
     * 
     * @return array
     */
    public function getCustomFieldOptions(): array
    {
        $saved_count = 0;
        $fields = TableForMigration::where('source', TableSourceEnum::CUSTOM_FIELDS)->get();

        foreach ($fields as $field) {
            $field_id = $field->json_data['id'];
            $saved_count += $this->processFieldOptions($field_id);
        }

        return [
            'success' => true,
            'message' => "Processed options for {$fields->count()} fields",
            'saved_count' => $saved_count
        ];
    }

    /**
     * Общий метод для получения данных с пагинацией
     *
     * @param string          $endpoint
     * @param TableSourceEnum $source
     * @param string          $type
     *
     * @return array
     */
    private function getAllPages(string $endpoint, TableSourceEnum $source, string $type): array
    {
        $response = Http::HelpDesk()->get($endpoint)->json();
        $total_pages = $response['pagination']['total_pages'];
        $saved_count = $this->processItems($response['data'], $source);

        Log::info("Будет загружено страниц {$total_pages} для {$type}");

        for ($page = 2; $page <= $total_pages; $page++) {
            $response = Http::HelpDesk()->get($endpoint, ['page' => $page]);

            if (!$response->successful()) {
                Log::error("Упала страница {$page} для {$type}");
                continue;
            }

            $saved_count += $this->processItems($response['data'], $source);
        }

        return [
            'success' => true,
            'message' => "Успешно загружено {$total_pages} страниц для {$type}",
            'total_pages' => $total_pages,
            'saved_count' => $saved_count
        ];
    }

    /**
     * Общий метод для получения данных связанных с тикетами
     *
     * @param string          $endpoint URL
     * @param TableSourceEnum $source   Источник
     * @param string          $type     Тип
     *
     * @return array
     */
    private function getTicketRelatedData(string $endpoint, TableSourceEnum $source, string $type): array
    {
        $saved_count = 0;
        $tickets = TableForMigration::where('source', TableSourceEnum::REQUEST)->get();

        foreach ($tickets as $ticket) {
            $ticket_id = $ticket->json_data['id'] ?? null;
            if (!$ticket_id) continue;

            Log::info("Загрузка $type для заявки $ticket_id");
            $saved_count += $this->processTicketPages($ticket_id, $endpoint, $source);
        }

        return [
            'success' => true,
            'message' => "Saved $saved_count $type",
            'saved_count' => $saved_count
        ];
    }

    /**
     * Обработка страниц для конкретного тикета
     *
     * @param int             $ticket_id Заявка
     * @param string          $endpoint  URL на который будет обращен
     * @param TableSourceEnum $source    Источник запроса
     *
     * @return int
     */
    private function processTicketPages(int $ticket_id, string $endpoint, TableSourceEnum $source): int
    {
        $saved_count = 0;
        $url = "tickets/{$ticket_id}/{$endpoint}";

        $response = Http::HelpDesk()->get($url);
        if (!$response->successful()) {
            Log::error("Не удалось получить данные для тикета {$ticket_id}");
            return 0;
        }

        $responseData = $response->json();
        $total_pages = $responseData['pagination']['total_pages'] ?? 1;

        $saved_count += $this->processItems($responseData['data'] ?? [], $source);

        for ($page = 2; $page <= $total_pages; $page++) {
            $response = Http::HelpDesk()->get($url, ['page' => $page]);

            if (!$response->successful()) {
                Log::error("Не удалось получить страницу $page для тикета $ticket_id");
                continue;
            }

            $saved_count += $this->processItems($response->json('data') ?? [], $source);
        }

        return $saved_count;
    }

    /**
     * Обработка опций для поля
     *
     * @param int $field_id Поле для получения
     * 
     * @return int
     */
    private function processFieldOptions(int $field_id): int
    {
        $saved_count = 0;
        $page = 1;

        do {
            $response = Http::HelpDesk()->get("custom_fields/$field_id/options/", ['page' => $page])->json();

            if (!isset($response['data'])) {
                Log::error("No data for field $field_id page $page");
                break;
            }

            $saved_count += $this->processItems($response['data'], TableSourceEnum::CUSTOM_FIELD_OPTIONS);
            $total_pages = $response['pagination']['total_pages'] ?? 1;
            $page++;
        } while ($page <= $total_pages);

        return $saved_count;
    }

    /**
     * Обработка массива элементов
     *
     * @param array           $items  Массив для сохранения
     * @param TableSourceEnum $source Источник к чему приписать
     * 
     * @return int
     */
    private function processItems(array $items, TableSourceEnum $source): int
    {
        $count = 0;
        foreach ($items as $item) {
            $this->repository->updateOrCreateRow($source, $item);
            $count++;
        }
        return $count;
    }
}

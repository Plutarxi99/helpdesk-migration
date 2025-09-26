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

    public function getRequests(): array
    {
        $saved_count = 0;

        $response = Http::HelpDesk()->get('tickets/')->json();

        $total_pages = $response['pagination']['total_pages'];

        foreach ($response['data'] as $request) {
            $this->repository->saveRequest($request);
            $saved_count++;
        }

        \Log::info("Будет загружено страниц {$total_pages}.");

        for ($page = 2; $page <= $total_pages; $page++) {
            $response = Http::HelpDesk()->get('tickets/',
                [
                    'page' => $page
                ]
            );

            if (!$response->successful()) {
                \Log::error("Failed to fetch page {$page}");
                continue;
            }

            foreach ($response['data'] as $request) {
                $this->repository->saveRequest($request);
                $saved_count++;
            }

            if ($page < $total_pages) {
                usleep(200000);
            }
        }

        return [
            'success' => true,
            'message' => "Successfully processed {$total_pages} pages",
            'total_pages' => $total_pages,
            'saved_requests' => $saved_count
        ];
    }

    public function getContacts()
    {
        $saved_count = 0;

        $response = Http::HelpDesk()->get('users/')->json();

        $total_pages = $response['pagination']['total_pages'];

        foreach ($response['data'] as $request) {
            $this->repository->saveContacts($request);
            $saved_count++;
        }

        \Log::info("Будет загружено страниц {$total_pages}.");

        for ($page = 2; $page <= $total_pages; $page++) {
            $response = Http::HelpDesk()->get('users/',
                [
                    'page' => $page
                ]
            );

            if (!$response->successful()) {
                \Log::error("Failed to fetch page {$page}");
                continue;
            }

            foreach ($response['data'] as $request) {
                $this->repository->saveContacts($request);
                $saved_count++;
            }
        }

        return [
            'success' => true,
            'message' => "Successfully processed {$total_pages} pages",
            'total_pages' => $total_pages,
            'saved_requests' => $saved_count
        ];
    }

    public function getAnswers(): array
    {
        $saved_count = 0;
        $requestCount = 0;
        $startTime = microtime(true);

        $tickets = TableForMigration::query()
            ->where('source', TableSourceEnum::REQUEST)
            ->get();

        foreach ($tickets as $ticket) {
            $data = $ticket->json_data;

            if (!isset($data['id'])) {
                continue;
            }

            $ticketId = $data['id'];

            Log::info("Загрузка по заявке ответов $ticketId");

            // первый запрос
            $response = Http::HelpDesk()->get("tickets/{$ticketId}/posts/");
            $requestCount++;

            if (!$response->successful()) {
                \Log::error("Не удалось получить ответы для тикета {$ticketId}");
                continue;
            }

            $responseData = $response->json();
            $total_pages = $responseData['pagination']['total_pages'] ?? 1;
            Log::info("Будет загружено записей $total_pages");

            foreach ($responseData['data'] ?? [] as $answer) {
                $this->repository->saveAnswer($answer);
                $saved_count++;
            }

            // остальные страницы
            for ($page = 2; $page <= $total_pages; $page++) {
                // задержка перед каждым новым запросом
                $this->applyRateLimit($requestCount, $startTime);

                $response = Http::HelpDesk()->get("tickets/{$ticketId}/posts/", [
                    'page' => $page,
                ]);
                $requestCount++;

                if (!$response->successful()) {
                    \Log::error("Не удалось получить страницу {$page} для тикета {$ticketId}");
                    continue;
                }

                foreach ($response->json('data') ?? [] as $answer) {
                    $this->repository->saveAnswer($answer);
                    $saved_count++;
                }
            }
        }

        return [
            'success' => true,
            'message' => "Saved {$saved_count} answers",
        ];
    }

    /**
     * Ограничение — не более 100 запросов в минуту
     */
    private function applyRateLimit(int $requestCount, float &$startTime): void
    {
        if ($requestCount >= 100) {
            $elapsed = microtime(true) - $startTime;

            if ($elapsed < 60) {
                $sleep = (60 - $elapsed) * 1000000; // в микросекундах
                \Log::info("Достигнут лимит 100 RPM. Спим {$sleep} мкс...");
                usleep((int) $sleep);
            }

            // сброс счётчика
            $startTime = microtime(true);
            $requestCount = 0;
        } else {
            // чтобы равномерно распределять — небольшая пауза 600ms
            usleep(600000);
        }
    }

    public function getComments(): array
    {
        $saved_count = 0;
        $requestCount = 0;
        $startTime = microtime(true);

        $tickets = TableForMigration::query()
            ->where('source', TableSourceEnum::REQUEST)
            ->get();

        foreach ($tickets as $ticket) {
            $data = $ticket->json_data;

            if (!isset($data['id'])) {
                continue;
            }

            $ticketId = $data['id'];

            Log::info("Загрузка по заявке ответов $ticketId");

            // первый запрос
            $response = Http::HelpDesk()->get("tickets/{$ticketId}/comments/");
            $requestCount++;

            if (!$response->successful()) {
                \Log::error("Не удалось получить ответы для тикета {$ticketId}");
                continue;
            }

            $responseData = $response->json();
            $total_pages = $responseData['pagination']['total_pages'] ?? 1;
            Log::info("Будет загружено записей $total_pages");

            foreach ($responseData['data'] ?? [] as $answer) {
                $this->repository->saveComment($answer);
                $saved_count++;
            }

            // остальные страницы
            for ($page = 2; $page <= $total_pages; $page++) {
                // задержка перед каждым новым запросом
                $this->applyRateLimit($requestCount, $startTime);

                $response = Http::HelpDesk()->get("tickets/{$ticketId}/comments/", [
                    'page' => $page,
                ]);
                $requestCount++;

                if (!$response->successful()) {
                    \Log::error("Не удалось получить страницу {$page} для тикета {$ticketId}");
                    continue;
                }

                foreach ($response->json('data') ?? [] as $answer) {
                    $this->repository->saveComment($answer);
                    $saved_count++;
                }
            }
        }

        return [
            'success' => true,
            'message' => "Saved {$saved_count} answers",
        ];
    }

    public function getDepartments(): array
    {
        $saved_count = 0;

        $response = Http::HelpDesk()->get('departments/')->json();

        foreach ($response['data'] as $request) {
            $this->repository->saveDepartments($request);
            $saved_count++;
        }

        return [
            'success' => true,
            'saved_requests' => $saved_count
        ];
    }

    public function getCustomFields(): array
    {
        $saved_count = 0;

        $response = Http::HelpDesk()->get('custom_fields/')->json();

        $total_pages = $response['pagination']['total_pages'];

        foreach ($response['data'] as $request) {
            $this->repository->saveCustomFields($request);
            $saved_count++;
        }

        \Log::info("Будет загружено страниц {$total_pages}.");

        for ($page = 2; $page <= $total_pages; $page++) {
            $response = Http::HelpDesk()->get('custom_fields/',
                [
                    'page' => $page
                ]
            );

            if (!$response->successful()) {
                \Log::error("Failed to fetch page {$page}");
                continue;
            }

            foreach ($response['data'] as $request) {
                $this->repository->saveCustomFields($request);
                $saved_count++;
            }
        }

        return [
            'success' => true,
            'message' => "Successfully processed {$total_pages} pages",
            'total_pages' => $total_pages,
            'saved_requests' => $saved_count
        ];
    }

    public function getCustomFieldOptions(): array
    {
        $saved_count = 0;

        // Берём все кастомные поля из таблицы миграции
        $fields = TableForMigration::query()
            ->where('source', TableSourceEnum::CUSTOM_FIELDS)
            ->get();


        foreach ($fields as $field) {
            $fieldId = $field->json_data['id'];

            $page = 1;
            $total_pages = 1;

            do {
                $response = Http::HelpDesk()->get("custom_fields/{$fieldId}/options/", [
                    'page' => $page
                ])->json();

                if (!isset($response['data'])) {
                    \Log::error("No data for field {$fieldId} page {$page}");
                    break;
                }

                foreach ($response['data'] as $option) {
                    $this->repository->saveCustomFieldOption($option);
                    $saved_count++;
                }

                $total_pages = $response['pagination']['total_pages'] ?? 1;
                $page++;
            } while ($page <= $total_pages);
        }

        return [
            'success' => true,
            'message' => "Processed options for {$fields->count()} fields",
            'saved_options' => $saved_count
        ];
    }
}
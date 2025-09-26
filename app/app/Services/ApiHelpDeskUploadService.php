<?php

namespace App\Services;

use App\Enums\TableSourceEnum;
use App\Models\TableForMigration;
use App\Repository\ApiHelpDeskUploadResource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiHelpDeskUploadService
{
    public function __construct(
        protected ApiHelpDeskUploadResource $repository
    ) {}

    /**
     * @param int|null $fromId - минимальный ID для загрузки
     * @param int|null $toId - максимальный ID для загрузки
     */
    public function uploadRequests(?int $fromId = null, ?int $toId = null): array
    {
        $savedCount = 0;
        $requestCount = 0;
        $startTime = microtime(true);

        $query = TableForMigration::query()
            ->where('source', TableSourceEnum::REQUEST);

        if ($fromId !== null) {
            $query->where('id_table_for_migrations', '>=', $fromId);
        }

        if ($toId !== null) {
            $query->where('id_table_for_migrations', '<=', $toId);
        }

        $requests = $query->get();

        foreach ($requests as $request) {
            $data = $request->json_data;

            $payload = [
                'title' => $data['title'] ?? 'Без названия',
                'description' => $data['description'] ?? '',
                'status_id' => $data['status_id'] ?? 'open',
                'priority_id' => $data['priority_id'] ?? 1,
                'type_id' => $data['type_id'] ?? 0,
                'department_id' => $data['department_id'] ?? 1,
                'owner_id' => $data['owner_id'] ?? 0,
                'user_id' => $data['user_id'] ?? null,
                'user_email' => $data['user_email'] ?? null,
                'ticket_lock' => $data['ticket_lock'] ?? false,
                'custom_fields' => $data['custom_fields'] ?? [],
                'tags' => $data['tags'] ?? [],
            ];

            $response = Http::HelpDesk()->post('tickets/', $payload);
            $requestCount++;

            if ($response->successful()) {
                $savedCount++;
                Log::info("Заявка ID {$request->id_table_for_migrations} загружена");
            } else {
                Log::error("Ошибка загрузки заявки ID {$request->id_table_for_migrations}: " . $response->body());
            }

            // Применяем лимит 300 запросов в минуту
            $this->applyRateLimit($requestCount, $startTime);
        }

        return [
            'success' => true,
            'message' => "Загружено {$savedCount} заявок",
        ];
    }

    private function applyRateLimit(int &$requestCount, float &$startTime): void
    {
        $maxRequestsPerMinute = 300;

        if ($requestCount >= $maxRequestsPerMinute) {
            $elapsed = microtime(true) - $startTime;
            if ($elapsed < 60) {
                $sleep = (60 - $elapsed) * 1000000; // мкс
                Log::info("Достигнут лимит {$maxRequestsPerMinute} RPM. Спим {$sleep} мкс...");
                usleep((int)$sleep);
            }
            $requestCount = 0;
            $startTime = microtime(true);
        } else {
            // равномерная задержка между запросами
            $pause = 60 / $maxRequestsPerMinute;
            usleep($pause * 1000000);
        }
    }

    /**
     * Загружает пользователей из базы в CRM
     *
     * @param int|null $fromId
     * @param int|null $toId
     */
    public function uploadContacts(?int $fromId = null, ?int $toId = null): array
    {
        $savedCount = 0;
        $requestCount = 0;
        $startTime = microtime(true);

        $users = TableForMigration::query()
            ->where('source', TableSourceEnum::CONTACTS);

        if ($fromId !== null) {
            $users->where('id_table_for_migrations', '>=', $fromId);
        }

        if ($toId !== null) {
            $users->where('id_table_for_migrations', '<=', $toId);
        }

        $users = $users->get();

        foreach ($users as $user) {
            $data = $user->json_data;

            $payload = [
                'name' => $data['name'] ?? 'No Name',
                'lastname' => $data['lastname'] ?? '',
                'alias' => $data['alias'] ?? '',
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? '',
                'website' => $data['website'] ?? '',
                'organization' => $data['organization']['name'] ?? null,
                'organiz_id' => $data['organization']['id'] ?? null,
                'status' => $data['status'] ?? 'active',
                'language' => $data['language'] ?? 'ru',
                'notifications' => $data['notifications'] ?? 0,
                'user_status' => $data['user_status'] ?? 'offline',
                'group_id' => $data['group']['id'] ?? 1,
                'department' => $data['department'] ?? [1],
                'custom_fields' => $data['custom_fields'] ?? [],
                'password' => 'temporaryPassword123', // нужно указать пароль
            ];

            if (empty($payload['email'])) {
                Log::warning("Пропущен пользователь с пустым email: ID {$user->id_table_for_migrations}");
                continue;
            }

            $response = Http::HelpDesk()->post('users/', $payload);
            $requestCount++;

            if ($response->successful()) {
                $savedCount++;
                Log::info("Пользователь {$payload['email']} создан");
            } else {
                Log::error("Ошибка создания пользователя {$payload['email']}: " . $response->body());
            }

            // Лимит 300 запросов в минуту
            $this->applyRateLimit($requestCount, $startTime);
        }

        return [
            'success' => true,
            'message' => "Создано {$savedCount} пользователей",
        ];
    }

    /**
     * Загружает комментарии из БД в CRM
     *
     * @param int|null $fromId
     * @param int|null $toId
     */
    public function uploadComments(?int $fromId = null, ?int $toId = null): array
    {
        $savedCount = 0;
        $requestCount = 0;
        $startTime = microtime(true);

        $comments = TableForMigration::query()
            ->where('source', TableSourceEnum::COMMENTS);

        if ($fromId !== null) {
            $comments->where('id_table_for_migrations', '>=', $fromId);
        }

        if ($toId !== null) {
            $comments->where('id_table_for_migrations', '<=', $toId);
        }

        $comments = $comments->get();

        foreach ($comments as $comment) {
            $data = $comment->json_data;

            if (!isset($data['ticket_id'], $data['text'])) {
                Log::warning("Пропущен комментарий с ID {$comment->id_table_for_migrations} — нет ticket_id или text");
                continue;
            }

            $payload = [
                'text' => $data['text'],
            ];

            if (isset($data['user_id']) && $data['user_id'] !== -1) {
                $payload['user_id'] = $data['user_id'];
            }

            if (!empty($data['files'])) {
                $payload['files'] = $data['files']; // Важно: нужно использовать multipart/form-data, если есть файлы
            }

            $ticketId = $data['ticket_id'];

            $response = Http::HelpDesk()->post("tickets/{$ticketId}/comments/", $payload);
            $requestCount++;

            if ($response->successful()) {
                $savedCount++;
                Log::info("Комментарий к тикету {$ticketId} создан");
            } else {
                Log::error("Ошибка создания комментария к тикету {$ticketId}: " . $response->body());
            }

            // Лимит 300 запросов в минуту
            $this->applyRateLimit($requestCount, $startTime);
        }

        return [
            'success' => true,
            'message' => "Создано {$savedCount} комментариев",
        ];
    }

    /**
     * Загружает ответы из БД в CRM
     *
     * @param int|null $fromId
     * @param int|null $toId
     */
    public function uploadAnswers(?int $fromId = null, ?int $toId = null): array
    {
        $savedCount = 0;
        $requestCount = 0;
        $startTime = microtime(true);

        $answers = TableForMigration::query()
            ->where('source', TableSourceEnum::ANSWER);

        if ($fromId !== null) {
            $answers->where('id_table_for_migrations', '>=', $fromId);
        }

        if ($toId !== null) {
            $answers->where('id_table_for_migrations', '<=', $toId);
        }

        $answers = $answers->get();

        $maxTextLength = 15000; // безопасная длина текста за один POST

        foreach ($answers as $answer) {
            $data = $answer->json_data;

            if (!isset($data['ticket_id'], $data['text'])) {
                Log::warning("Пропущен ответ ID {$answer->id_table_for_migrations} — нет ticket_id или текста");
                continue;
            }

            $ticketId = $data['ticket_id'];
            $userId = $data['user_id'] ?? null;
            $text = $data['text'];

            // Разбиваем текст на части
            $parts = mb_str_split($text, $maxTextLength);

            foreach ($parts as $part) {
                $payload = ['text' => $part];
                if ($userId !== null && $userId !== -1) {
                    $payload['user_id'] = $userId;
                }

                $response = Http::HelpDesk()->post("tickets/{$ticketId}/posts/", $payload);
                $requestCount++;

                if ($response->successful()) {
                    $savedCount++;
                    Log::info("Ответ к тикету {$ticketId} загружен");
                } else {
                    Log::error("Ошибка загрузки ответа к тикету {$ticketId}: " . $response->body());
                }

                // Лимит 300 запросов в минуту
                $this->applyRateLimit($requestCount, $startTime);
            }
        }

        return [
            'success' => true,
            'message' => "Загружено {$savedCount} ответов",
        ];
    }

}
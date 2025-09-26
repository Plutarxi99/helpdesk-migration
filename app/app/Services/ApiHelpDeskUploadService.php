<?php

namespace App\Services;

use App\Enums\SendEnum;
use App\Enums\TableSourceEnum;
use App\Models\TableForMigration;
use App\Repository\ApiHelpDeskUploadResource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiHelpDeskUploadService
{
    public function __construct(
        protected ApiHelpDeskUploadResource $repository
    )
    {
    }

    protected function mapUserId(int $userId): int
    {
        $mapping = [
            2093 => 708,
            2092 => 777,
            1090 => 700,
            1092 => 701,
            1093 => 702,
            1094 => 703,
            1095 => 704,
            1097 => 705,
            1098 => 706,
            1099 => 707,
        ];

        return $mapping[$userId] ?? $userId;
    }

    /**
     * Загружает заявки
     */
    public function uploadRequests(?int $fromId = null, ?int $toId = null): array
    {
        $savedCount = 0;

        foreach (TableForMigration::getNotSend(TableSourceEnum::REQUEST, $fromId, $toId) as $request) {
            $data = $request->json_data;

            $data['user_id'] = $this->mapUserId($data['user_id']);

            $payload = [
                'title' => $data['title'] ?? 'Без названия',
                'description' => !empty($data['description']) ? $data['description'] : 'Без описания',
                'status_id' => $data['status_id'] ?? 'open',
                'priority_id' => $data['priority_id'] ?? 1,
                'type_id' => $data['type_id'] ?? 0,
                'department_id' => $data['department_id'] ?? 1,
                'owner_id' => $data['owner_id'] ?? 0,
                'user_id' => $data['user_id'] ?? null,
                'user_email' => $data['user_email'] ?? null,
//                'custom_fields' => $data['custom_fields'] ?? [],
                'tags' => $data['tags'] ?? [],
            ];

            $attempts = 0;
            $maxAttempts = 2;
            $success = false;

            while (!$success && $attempts < $maxAttempts) {
                $attempts++;

                try {
                    $response = Http::HelpDeskEgor()->post('tickets/', $payload);

                    if ($response->successful()) {
                        $savedCount++;
                        $success = true;
                        $request->update(
                            [
                                'is_send' => SendEnum::SEND,
                                'error_message' => null
                            ]
                        );

                        Log::info('Заявка успешно загружена', ['request_id' => $request->id_table_for_migrations]);
                    } else {
                        $request->update(
                            [
                                'error_message' => json_encode($response->json())
                            ]
                        );
                        Log::error('Ошибка при загрузке заявки',
                            [
                                'request_id' => $request->id_table_for_migrations,
                                'status' => $response->status(),
                                'attempt' => $attempts
                            ]
                        );
                        sleep(1);
                    }
                } catch (\Throwable $e) {
                    $request->update(
                        [
                            'error_message' => $e->getMessage()
                        ]
                    );
                    Log::error('HTTP-ошибка при загрузке заявки',
                        [
                            'request_id' => $request->id_table_for_migrations,
                            'attempt' => $attempts
                        ]
                    );
                    sleep(1);
                }
            }
        }

        return [
            'success' => true,
            'message' => "Загружено {$savedCount} заявок",
        ];
    }

    /**
     * Загружает пользователей
     */
    public function uploadContacts(?int $fromId = null, ?int $toId = null): array
    {
        $savedCount = 0;

        foreach (TableForMigration::getNotSend(TableSourceEnum::CONTACTS, $fromId, $toId) as $user) {

            $data = $user->json_data;

            if (empty($data['email'])) {
                Log::warning('Пропущен пользователь без email', ['user_id' => $user->id_table_for_migrations]);
                continue;
            }

            $payload = [
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

            $attempts = 0;
            $maxAttempts = 2;
            $success = false;

            while (!$success && $attempts < $maxAttempts) {
                $attempts++;

                try {
                    $response = Http::HelpDeskEgor()->post('users/', $payload);

                    if ($response->successful()) {
                        $savedCount++;
                        $success = true;
                        $user->update([
                            'is_send' => SendEnum::SEND,
                            'error_message' => null
                        ]);

                        Log::info('Пользователь успешно создан', [
                            'email' => $payload['email'],
                            'user_id' => $user->id_table_for_migrations
                        ]);
                    } else {
                        $user->update([
                            'error_message' => json_encode($response->json())
                        ]);
                        Log::error('Ошибка при создании пользователя', [
                            'email' => $payload['email'],
                            'user_id' => $user->id_table_for_migrations,
                            'status' => $response->status(),
                            'attempt' => $attempts
                        ]);
                        sleep(1);
                    }
                } catch (\Throwable $e) {
                    $user->update([
                        'error_message' => $e->getMessage()
                    ]);
                    Log::error('HTTP-ошибка при создании пользователя', [
                        'email' => $payload['email'],
                        'user_id' => $user->id_table_for_migrations,
                        'attempt' => $attempts
                    ]);
                    sleep(1);
                }
            }
        }

        return [
            'success' => true,
            'message' => "Создано {$savedCount} пользователей",
        ];
    }

    /**
     * Загружает комментарии
     */
    public function uploadComments(?int $fromId = null, ?int $toId = null): array
    {
        $savedCount = 0;

        $comments = TableForMigration::query()
            ->where('source', TableSourceEnum::COMMENTS)
            ->where('is_send', SendEnum::NOT_SEND)
            ->when($fromId !== null, fn($q) => $q->where('id_table_for_migrations', '>=', $fromId))
            ->when($toId !== null, fn($q) => $q->where('id_table_for_migrations', '<=', $toId))
            ->get();

        foreach ($comments as $comment) {
            $data = $comment->json_data;

            if (!isset($data['ticket_id'], $data['text'])) {
                Log::warning('Пропущен комментарий без обязательных полей', [
                    'comment_id' => $comment->id_table_for_migrations,
                ]);
                continue;
            }

            $payload = ['text' => $data['text']];

            if (!empty($data['user_id']) && $data['user_id'] !== -1) {
                $payload['user_id'] = $data['user_id'];
            }

            if (!empty($data['files'])) {
                $payload['files'] = $data['files'];
            }

            $attempts = 0;
            $maxAttempts = 2;
            $success = false;

            while (!$success && $attempts < $maxAttempts) {
                $attempts++;

                try {
                    $response = Http::HelpDeskEgor()->post("tickets/{$data['ticket_id']}/comments/", $payload);

                    if ($response->successful()) {
                        $savedCount++;
                        $success = true;

                        Log::info('Комментарий успешно создан', [
                            'comment_id' => $comment->id_table_for_migrations,
                            'ticket_id' => $data['ticket_id'],
                        ]);

                        $comment->update(['is_send' => SendEnum::SEND]);
                    } else {
                        Log::error('Ошибка создания комментария', [
                            'comment_id' => $comment->id_table_for_migrations,
                            'ticket_id' => $data['ticket_id'],
                            'status' => $response->status(),
                            'body' => $response->json(),
                            'attempt' => $attempts,
                        ]);
                        sleep(1);
                    }
                } catch (\Throwable $e) {
                    Log::error('HTTP-ошибка при создании комментария', [
                        'comment_id' => $comment->id_table_for_migrations,
                        'ticket_id' => $data['ticket_id'],
                        'attempt' => $attempts,
                        'error' => $e->getMessage(),
                    ]);
                    sleep(1);
                }
            }

            if (!$success) {
                Log::warning('Не удалось создать комментарий после всех попыток', [
                    'comment_id' => $comment->id_table_for_migrations,
                    'ticket_id' => $data['ticket_id'],
                    'attempts' => $maxAttempts,
                ]);
            }
        }

        return [
            'success' => true,
            'message' => "Создано {$savedCount} комментариев",
        ];
    }


    /**
     * Загружает ответы
     */
    public function uploadAnswers(?int $fromId = null, ?int $toId = null): array
    {
        $savedCount = 0;
        $maxTextLength = 15000;

        $answers = TableForMigration::query()
            ->where('source', TableSourceEnum::ANSWER)
            ->where('is_send', SendEnum::NOT_SEND)
            ->when($fromId !== null, fn($q) => $q->where('id_table_for_migrations', '>=', $fromId))
            ->when($toId !== null, fn($q) => $q->where('id_table_for_migrations', '<=', $toId))
            ->get();

        foreach ($answers as $answer) {
            $data = $answer->json_data;

            if (!isset($data['ticket_id'], $data['text'])) {
                Log::warning('Пропущен ответ без обязательных полей', [
                    'answer_id' => $answer->id_table_for_migrations,
                ]);
                continue;
            }

            $ticketId = $data['ticket_id'];
            $userId = $data['user_id'] ?? null;
            $parts = mb_str_split($data['text'], $maxTextLength);

            $attempts = 0;
            $maxAttempts = 2;
            $success = false;

            while (!$success && $attempts < $maxAttempts) {
                $attempts++;

                try {
                    foreach ($parts as $index => $part) {
                        $payload = ['text' => $part];

                        if ($userId !== null && $userId !== -1) {
                            $payload['user_id'] = $userId;
                        }

                        $response = Http::HelpDeskEgor()->post("tickets/{$ticketId}/posts/", $payload);

                        if ($response->failed()) {
                            Log::error('Ошибка при загрузке ответа', [
                                'answer_id' => $answer->id_table_for_migrations,
                                'ticket_id' => $ticketId,
                                'part' => $index + 1,
                                'status' => $response->status(),
                                'body' => $response->json(),
                                'attempt' => $attempts,
                            ]);
                            throw new \Exception("Ошибка загрузки части ответа");
                        }
                    }

                    // если все части успешно загрузились
                    $savedCount++;
                    $success = true;

                    Log::info('Ответ успешно загружен', [
                        'answer_id' => $answer->id_table_for_migrations,
                        'ticket_id' => $ticketId,
                    ]);

                    $answer->update(['is_send' => SendEnum::SEND]);
                } catch (\Throwable $e) {
                    Log::error('HTTP-ошибка при загрузке ответа', [
                        'answer_id' => $answer->id_table_for_migrations,
                        'ticket_id' => $ticketId,
                        'attempt' => $attempts,
                        'error' => $e->getMessage(),
                    ]);
                    sleep(1);
                }
            }

            if (!$success) {
                Log::warning('Не удалось загрузить ответ после всех попыток', [
                    'answer_id' => $answer->id_table_for_migrations,
                    'ticket_id' => $ticketId,
                    'attempts' => $maxAttempts,
                ]);
            }
        }

        return [
            'success' => true,
            'message' => "Загружено {$savedCount} ответов",
        ];
    }
}

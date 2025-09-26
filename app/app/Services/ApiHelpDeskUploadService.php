<?php

namespace App\Services;

use App\Enums\SendEnum;
use App\Enums\TableSourceEnum;
use App\Models\TableForMigration;
use App\Repository\ApiHelpDeskUploadResource;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ApiHelpDeskUploadService
{
    public function __construct(
        protected ApiHelpDeskUploadResource $repository,
    )
    {
    }

    /**
     * Загружает заявки
     *
     * @param null|int $from_id От какой ID следует загружать
     * @param null|int $to_id   До какого ID следует загружать
     *
     * @return array
     * @throws Exception
     */
    public function uploadRequests(?int $from_id = null, ?int $to_id = null): array
    {
        $saved_count = 0;

        foreach (TableForMigration::getNotSend(TableSourceEnum::REQUEST, $from_id, $to_id) as $request) {
            $data = $request->json_data;

            $payload = $this->repository->mappingPayload(TableSourceEnum::REQUEST, $data);

            $attempts = 0;
            $max_attempts = 2;
            $success = false;

            while (! $success && $attempts < $max_attempts) {
                $attempts++;

                try {
                    $response = Http::HelpDeskEgor()->post('tickets/', $payload);

                    if ($response->successful()) {
                        $saved_count++;
                        $success = true;
                        $request->update(
                            [
                                'is_send' => SendEnum::SEND,
                                'error_message' => null
                            ]
                        );

                        Log::info('Заявка успешно загружена', ['request_id' => $request->id_table_for_migrations]);
                    } else {
                        $request->update(['error_message' => json_encode($response->json())]);
                        Log::error('Ошибка при загрузке заявки',
                            [
                                'request_id' => $request->id_table_for_migrations,
                                'status' => $response->status(),
                                'attempt' => $attempts
                            ]
                        );
                        sleep(1);
                    }
                } catch (Throwable $e) {
                    $request->update(['error_message' => $e->getMessage()]);

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
            'message' => "Загружено {$saved_count} заявок",
        ];
    }

    /**
     * Загружает пользователей
     *
     * @param null|int $from_id От какой ID следует загружать
     * @param null|int $to_id   До какого ID следует загружать
     *
     * @return array
     * @throws Exception
     */
    public function uploadContacts(?int $from_id = null, ?int $to_id = null): array
    {
        $saved_count = 0;

        foreach (TableForMigration::getNotSend(TableSourceEnum::CONTACTS, $from_id, $to_id) as $user) {

            $data = $user->json_data;

            $payload = $this->repository->mappingPayload(TableSourceEnum::CONTACTS, $data);

            $attempts = 0;
            $max_attempts = 2;
            $success = false;

            while (! $success && $attempts < $max_attempts) {
                $attempts++;

                try {
                    $response = Http::HelpDeskEgor()->post('users/', $payload);

                    if ($response->successful()) {
                        $saved_count++;
                        $success = true;
                        $user->update(['is_send' => SendEnum::SEND, 'error_message' => null]);

                        Log::info('Пользователь успешно создан',
                            [
                                'email' => $payload['email'],
                                'user_id' => $user->id_table_for_migrations
                            ]
                        );
                    } else {
                        $user->update(['error_message' => json_encode($response->json())]);

                        Log::error('Ошибка при создании пользователя',
                            [
                                'email' => $payload['email'],
                                'user_id' => $user->id_table_for_migrations,
                                'status' => $response->status(),
                                'attempt' => $attempts
                            ]
                        );

                        sleep(1);
                    }
                } catch (Throwable $e) {
                    $user->update(['error_message' => $e->getMessage()]);

                    Log::error('HTTP-ошибка при создании пользователя',
                        [
                            'email' => $payload['email'],
                            'user_id' => $user->id_table_for_migrations,
                            'attempt' => $attempts
                        ]
                    );

                    sleep(1);
                }
            }
        }

        return [
            'success' => true,
            'message' => "Создано {$saved_count} пользователей",
        ];
    }

    /**
     * Загружает комментарии
     *
     * @param null|int $from_id От какой ID следует загружать
     * @param null|int $to_id   До какого ID следует загружать
     *
     * @return array
     * @throws Exception
     */
    public function uploadComments(?int $from_id = null, ?int $to_id = null): array
    {
        $saved_count = 0;
        foreach (TableForMigration::getNotSend(TableSourceEnum::COMMENTS, $from_id, $to_id) as $comment) {
            $data = $comment->json_data;

            $payload = $this->repository->mappingPayload(TableSourceEnum::COMMENTS, $data);

            $attempts = 0;
            $max_attempts = 2;
            $success = false;

            while (! $success && $attempts < $max_attempts) {
                $attempts++;

                try {
                    $response = Http::HelpDeskEgor()
                        ->post(
                            "tickets/{$this->repository->mapTicketId($data['ticket_id'])}/comments/",
                            $payload
                        );

                    if ($response->successful()) {
                        $saved_count++;
                        $success = true;

                        // Проставляем is_send только если успешно
                        $comment->update(['is_send' => SendEnum::SEND]);

                        Log::info('Комментарий успешно создан',
                            [
                                'comment_id' => $comment->id_table_for_migrations,
                                'ticket_id' => $data['ticket_id'],
                                'user_id' => $payload['user_id'] ?? null
                            ]
                        );
                    } else {
                        Log::error('Ошибка создания комментария',
                            [
                                'comment_id' => $comment->id_table_for_migrations,
                                'ticket_id' => $data['ticket_id'],
                                'status' => $response->status(),
                                'body' => $response->json(),
                                'attempt' => $attempts,
                                'user_id' => $payload['user_id'] ?? null
                            ]
                        );

                        sleep(1);
                    }
                } catch (Throwable $e) {
                    Log::error('HTTP-ошибка при создании комментария',
                        [
                            'comment_id' => $comment->id_table_for_migrations,
                            'ticket_id' => $data['ticket_id'],
                            'attempt' => $attempts,
                            'error' => $e->getMessage(),
                            'user_id' => $payload['user_id'] ?? null
                        ]
                    );

                    sleep(1);
                }
            }

            if (! $success) {
                Log::warning('Не удалось создать комментарий после всех попыток',
                    [
                        'comment_id' => $comment->id_table_for_migrations,
                        'ticket_id' => $data['ticket_id'],
                        'attempts' => $max_attempts,
                        'user_id' => $payload['user_id'] ?? null
                    ]
                );
            }
        }

        return [
            'success' => true,
            'message' => "Создано {$saved_count} комментариев",
        ];
    }

    /**
     * Загружает ответы
     *
     * @param null|int $from_id От какой ID следует загружать
     * @param null|int $to_id   До какого ID следует загружать
     *
     * @return array
     */
    public function uploadAnswers(?int $from_id = null, ?int $to_id = null): array
    {
        $saved_count = 0;
        $maxTextLength = 15000;

        foreach (TableForMigration::getNotSend(TableSourceEnum::ANSWER, $from_id, $to_id) as $answer) {
            $data = $answer->json_data;

            $ticketId = $this->repository->mapTicketId($data['ticket_id']);
            $userId = $this->repository->mapUserId($data['user_id'] ?? null);
            $textParts = mb_str_split($data['text'], $maxTextLength);

            $attempts = 0;
            $max_attempts = 2;
            $success = false;

            while (!$success && $attempts < $max_attempts) {
                $attempts++;
                try {
                    foreach ($textParts as $index => $part) {
                        $payload = ['text' => $part];
                        $payload['user_id'] = $userId;

                        // Отправка POST для каждой части
                        $response = Http::HelpDeskEgor()->post("tickets/{$ticketId}/posts/", $payload);

                        if ($response->failed()) {
                            Log::error('Ошибка при загрузке части ответа',
                                [
                                    'answer_id' => $answer->id_table_for_migrations,
                                    'ticket_id' => $ticketId,
                                    'part' => $index + 1,
                                    'status' => $response->status(),
                                    'body' => $response->json(),
                                    'attempt' => $attempts,
                                    'user_id' => $userId
                                ]
                            );

                            throw new Exception("Ошибка загрузки части ответа");
                        }
                    }

                    // Все части успешно загружены
                    $saved_count++;
                    $success = true;
                    $answer->update(['is_send' => SendEnum::SEND]);

                    Log::info('Ответ успешно загружен',
                        [
                            'answer_id' => $answer->id_table_for_migrations,
                            'ticket_id' => $ticketId,
                            'user_id' => $userId
                        ]
                    );
                } catch (Throwable $e) {
                    Log::error('HTTP-ошибка при загрузке ответа',
                        [
                            'answer_id' => $answer->id_table_for_migrations,
                            'ticket_id' => $ticketId,
                            'attempt' => $attempts,
                            'error' => $e->getMessage(),
                            'user_id' => $userId
                        ]
                    );

                    sleep(1);
                }
            }

            if (! $success) {
                Log::warning('Не удалось загрузить ответ после всех попыток',
                    [
                        'answer_id' => $answer->id_table_for_migrations,
                        'ticket_id' => $ticketId,
                        'attempts' => $max_attempts,
                        'user_id' => $userId
                    ]
                );
            }
        }

        return [
            'success' => true,
            'message' => "Загружено {$saved_count} ответов",
        ];
    }
}

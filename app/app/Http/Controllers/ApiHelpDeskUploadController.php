<?php

namespace App\Http\Controllers;

use App\Services\ApiHelpDeskUploadService;
use Exception;

class ApiHelpDeskUploadController extends Controller
{
    public function __construct(
        protected readonly ApiHelpDeskUploadService $service
    ) {}

    /**
     * Загрузка Заявок
     *
     * @return array
     * @throws Exception
     */
    public function uploadRequests(): array
    {
        return $this->service->uploadRequests(1, 1000);
    }

    /**
     * Загрузка контактов
     *
     * @return array
     * @throws Exception
     */
    public function uploadContacts(): array
    {
        return $this->service->uploadContacts(1, 20100);
    }

    /**
     * Закгрузка комментариев
     *
     * @return array
     * @throws Exception
     */
    public function uploadComments(): array
    {
        return $this->service->uploadComments(1, 100);
    }

    /**
     * Загрузка Ответов
     *
     * @return array
     */
    public function uploadAnswers(): array
    {
        return $this->service->uploadAnswers(1, 100);
    }
}
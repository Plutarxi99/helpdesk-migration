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
        return $this->service->uploadMessages(1, 200);
    }
}
<?php

namespace App\Http\Controllers;

use App\Services\ApiHelpDeskService;

class ApiHelpDeskController extends Controller
{
    public function __construct(
        protected readonly ApiHelpDeskService $service
    ) {
    }

    /**
     * Получение заявок
     *
     * @return array
     */
    public function getRequests()
    {
        return $this->service->getRequests();
    }

    /**
     * Получение контактов
     *
     * @return array
     */
    public function getContacts()
    {
        return $this->service->getContacts();
    }

    /**
     * Получение ответов и сохранение
     *
     * @return array
     */
    public function getAnswers()
    {
        return $this->service->getAnswers();
    }

    /**
     * Получение комментариев
     *
     * @return array
     */
    public function getComments()
    {
        return $this->service->getComments();
    }

    /**
     * Получение департаментов
     *
     * @return array
     */
    public function getDepartments()
    {
        return $this->service->getDepartments();
    }

    /**
     * Получение кастомных полей
     *
     * @return array
     */
    public function getCustomFields()
    {
        return $this->service->getCustomFields();
    }

    /**
     * Получение кастомных полей
     *
     * @return array
     */
    public function getCustomFieldOptions()
    {
        return $this->service->getCustomFieldOptions();
    }
}

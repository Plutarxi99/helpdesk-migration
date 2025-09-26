<?php

namespace App\Http\Controllers;

use App\Services\ApiHelpDeskUploadService;

class ApiHelpDeskUploadController extends Controller
{
    public function __construct(
        protected readonly ApiHelpDeskUploadService $service
    ) {}

    public function uploadRequests()
    {
        $this->service->uploadRequests(1, 5);
    }

    public function uploadContacts()
    {
        $this->service->uploadContacts(1100, 2093);
    }

    public function uploadComments()
    {
        $this->service->uploadComments(1, 100);
    }

    public function uploadAnswers()
    {
        $this->service->uploadAnswers(1, 100);
    }
}
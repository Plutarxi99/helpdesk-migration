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
        $this->service->uploadRequests(1, 100);
    }

    public function uploadContacts()
    {
        $this->service->uploadContacts(1, 100);
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
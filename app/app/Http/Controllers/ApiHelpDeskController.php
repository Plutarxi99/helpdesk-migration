<?php

namespace App\Http\Controllers;

use App\Services\ApiHelpDeskService;
use Illuminate\Support\Facades\Http;

class ApiHelpDeskController extends Controller
{
    public function __construct(
        protected readonly ApiHelpDeskService $service
    ) {}

    public function getHelpDesks()
    {
        return Http::HelpDesk()->get('departments/')->json();
    }

    public function getRequests()
    {
        return $this->service->getRequests();
    }

    public function getContacts()
    {
        return $this->service->getContacts();
    }
}
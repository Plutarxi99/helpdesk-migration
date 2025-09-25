<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

class ApiHelpDeskController extends Controller
{
    public function getHelpDesks()
    {
        return Http::HelpDesk()->get('departments/')->json();
    }

    public function getRequest()
    {
        return Http::HelpDesk()->get('tickets/')->json();
    }
}
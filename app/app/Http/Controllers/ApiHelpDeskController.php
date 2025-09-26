<?php

namespace App\Http\Controllers;

use App\Services\ApiHelpDeskService;
use Illuminate\Support\Facades\DB;
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

    public function getAnswers()
    {
        return $this->service->getAnswers();
    }

    public function getComments()
    {
        return $this->service->getComments();
    }

    public function getDepartments()
    {
        return $this->service->getDepartments();
    }

    //TODO: удалить потом
    public function fillableIds()
    {
        // получаем все строки
        $rows = DB::table('table_for_migrations')->get();

        foreach ($rows as $row) {
            // json_decode превращает text в массив
            $data = json_decode($row->json_data, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($data['id'])) {
                DB::table('table_for_migrations')
                    ->where('id', $row->id)
                    ->update([
                        'id_table_for_migrations' => $data['id'],
                    ]);
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
<?php

namespace App\Services;

use App\Repository\ApiHelpDeskRepository;
use Illuminate\Support\Facades\Http;

class ApiHelpDeskService
{
    public function __construct(
        protected ApiHelpDeskRepository $repository
    ) {}

    public function getRequests(): array
    {
        $saved_count = 0;

        $response = Http::HelpDesk()->get('tickets/')->json();

        $total_pages = $response['pagination']['total_pages'];

        foreach ($response['data'] as $request) {
            $this->repository->saveRequest($request);
            $saved_count++;
        }

        \Log::info("Будет загружено страниц {$total_pages}.");

        for ($page = 2; $page <= $total_pages; $page++) {
            $response = Http::HelpDesk()->get('tickets/',
                [
                    'page' => $page
                ]
            );

            if (!$response->successful()) {
                \Log::error("Failed to fetch page {$page}");
                continue;
            }

            foreach ($response['data'] as $request) {
                $this->repository->saveRequest($request);
                $saved_count++;
            }

            if ($page < $total_pages) {
                usleep(200000); // 200ms задержка
            }
        }

        return [
            'success' => true,
            'message' => "Successfully processed {$total_pages} pages",
            'total_pages' => $total_pages,
            'saved_requests' => $saved_count
        ];
    }

    public function getContacts()
    {
        $saved_count = 0;

        $response = Http::HelpDesk()->get('users/')->json();

        $total_pages = $response['pagination']['total_pages'];

        foreach ($response['data'] as $request) {
            $this->repository->saveContacts($request);
            $saved_count++;
        }

        \Log::info("Будет загружено страниц {$total_pages}.");

        for ($page = 2; $page <= $total_pages; $page++) {
            $response = Http::HelpDesk()->get('users/',
                [
                    'page' => $page
                ]
            );

            if (!$response->successful()) {
                \Log::error("Failed to fetch page {$page}");
                continue;
            }

            foreach ($response['data'] as $request) {
                $this->repository->saveContacts($request);
                $saved_count++;
            }

            if ($page < $total_pages) {
                usleep(200000); // 200ms задержка
            }
        }

        return [
            'success' => true,
            'message' => "Successfully processed {$total_pages} pages",
            'total_pages' => $total_pages,
            'saved_requests' => $saved_count
        ];
    }
}
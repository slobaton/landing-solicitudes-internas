<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\HealthService;

final class HealthController
{
    public function show(Request $request): Response
    {
        return Response::ok((new HealthService())->estado());
    }
}

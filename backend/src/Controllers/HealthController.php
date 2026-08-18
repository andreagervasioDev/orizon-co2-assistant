<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\JsonResponse;

class HealthController
{
    /** GET /api/health */
    public function show(): never
    {
        JsonResponse::send(200, ['status' => 'ok']);
    }
}

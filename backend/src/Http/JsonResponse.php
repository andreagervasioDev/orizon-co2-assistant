<?php

declare(strict_types=1);

namespace App\Http;

final class JsonResponse
{
    public static function send(int $status, array $body): never
    {
        http_response_code($status);
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

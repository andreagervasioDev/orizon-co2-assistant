<?php

/**
 * Entry point del backend Orizon: bootstrap, CORS e routing.
 *
 * Endpoint:
 *   GET    /api/health                -> stato del servizio
 *   POST   /api/chat                  -> turno di conversazione con l'assistente
 *   GET    /api/conversations         -> storico chat (ultime 30, per la sidebar)
 *   GET    /api/conversations/{id}    -> messaggi di una conversazione (riapertura)
 *   PATCH  /api/conversations/{id}    -> rinomina una conversazione
 *   DELETE /api/conversations/{id}    -> elimina una conversazione
 */

declare(strict_types=1);

use App\Config;
use App\Controllers\ChatController;
use App\Controllers\ConversationsController;
use App\Controllers\HealthController;
use App\Core\Database;
use App\Http\JsonResponse;
use App\Repositories\ConversationRepository;

// ---------------------------------------------------------------------------
// Autoloader: App\Foo\Bar -> src/Foo/Bar.php
// ---------------------------------------------------------------------------
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

// ---------------------------------------------------------------------------
// CORS
// ---------------------------------------------------------------------------
header('Access-Control-Allow-Origin: ' . Config::get('ALLOWED_ORIGIN', '*'));
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Vary: Origin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// ---------------------------------------------------------------------------
// Routing
// ---------------------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';

try {
    if ($method === 'GET' && $path === '/api/health') {
        (new HealthController())->show();
    }

    $repository = fn (): ConversationRepository => new ConversationRepository(Database::connection());

    if ($method === 'POST' && $path === '/api/chat') {
        (new ChatController($repository()))->chat();
    }

    if ($method === 'GET' && $path === '/api/conversations') {
        (new ConversationsController($repository()))->index();
    }

    if (preg_match('#^/api/conversations/([a-zA-Z0-9\-]+)$#', $path, $matches)) {
        $controller = new ConversationsController($repository());
        $conversationId = $matches[1];
        match ($method) {
            'GET' => $controller->show($conversationId),
            'PATCH' => $controller->update($conversationId),
            'DELETE' => $controller->destroy($conversationId),
            default => JsonResponse::send(405, ['error' => 'Metodo non consentito.']),
        };
    }

    JsonResponse::send(404, ['error' => 'Endpoint non trovato.']);
} catch (\PDOException $e) {
    error_log('[orizon] DB error: ' . $e->getMessage());
    JsonResponse::send(503, ['error' => 'Database non disponibile.']);
} catch (\RuntimeException $e) {
    // Configurazione mancante o errore dei servizi esterni (OpenAI / EcoFreight)
    error_log('[orizon] ' . $e->getMessage());
    JsonResponse::send(502, ['error' => $e->getMessage()]);
} catch (\Throwable $e) {
    error_log('[orizon] Unexpected error: ' . $e->getMessage());
    JsonResponse::send(500, ['error' => 'Errore interno del server.']);
}

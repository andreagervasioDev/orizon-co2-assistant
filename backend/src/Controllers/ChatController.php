<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Clients\EcoFreightClient;
use App\Clients\OpenAIClient;
use App\Http\JsonResponse;
use App\Http\RateLimiter;
use App\Repositories\ConversationRepository;
use App\Services\ChatService;

/**
 * Endpoint unico dell'assistente: valida l'input, risolve (o crea) la
 * conversazione e delega il turno al ChatService.
 */
class ChatController
{
    public function __construct(
        private readonly ConversationRepository $repository,
        private readonly RateLimiter $rateLimiter,
    ) {
    }

    /** POST /api/chat — body: {message: string, conversation_id?: string} */
    public function chat(): never
    {
        if ($this->rateLimiter->tooManyRequests($this->clientIp())) {
            JsonResponse::send(429, ['error' => 'Troppe richieste. Riprova tra qualche secondo.']);
        }

        $input = json_decode((string) file_get_contents('php://input'), true);
        $message = is_array($input) ? trim((string) ($input['message'] ?? '')) : '';
        $conversationId = is_array($input) ? ($input['conversation_id'] ?? null) : null;

        if ($message === '') {
            JsonResponse::send(400, ['error' => 'Il campo "message" è obbligatorio.']);
        }

        if (is_string($conversationId) && $conversationId !== '') {
            if (!$this->repository->conversationExists($conversationId)) {
                JsonResponse::send(404, ['error' => 'Conversazione non trovata.']);
            }
        } else {
            $conversationId = $this->repository->createConversation(mb_substr($message, 0, 120));
        }

        $service = new ChatService(
            openAi: new OpenAIClient(),
            ecoFreight: new EcoFreightClient(),
            repository: $this->repository,
        );

        $result = $service->handleTurn($conversationId, $message);

        JsonResponse::send(200, [
            'conversation_id' => $conversationId,
            'reply' => $result['reply'],
            'trip_summary' => $result['trip_summary'],
        ]);
    }

    /**
     * Render (e la maggior parte degli host) sta dietro un proxy: REMOTE_ADDR
     * sarebbe l'IP del proxy, non del client reale, quindi va preferito
     * X-Forwarded-For quando presente.
     */
    private function clientIp(): string
    {
        $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
        if (is_string($forwardedFor) && $forwardedFor !== '') {
            return trim(explode(',', $forwardedFor)[0]);
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

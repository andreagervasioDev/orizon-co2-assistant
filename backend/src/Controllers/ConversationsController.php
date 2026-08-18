<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\JsonResponse;
use App\Repositories\ConversationRepository;

/**
 * Gestione dello storico chat per la sidebar:
 * elenco, riapertura, rinomina e cancellazione delle conversazioni.
 */
class ConversationsController
{
    public function __construct(private readonly ConversationRepository $repository)
    {
    }

    /** GET /api/conversations — le ultime 30 conversazioni */
    public function index(): never
    {
        JsonResponse::send(200, ['conversations' => $this->repository->listConversations()]);
    }

    /** GET /api/conversations/{id} — messaggi per riaprire una chat */
    public function show(string $conversationId): never
    {
        if (!$this->repository->conversationExists($conversationId)) {
            JsonResponse::send(404, ['error' => 'Conversazione non trovata.']);
        }

        JsonResponse::send(200, [
            'conversation_id' => $conversationId,
            'messages' => $this->repository->getHistoryForClient($conversationId),
        ]);
    }

    /** PATCH /api/conversations/{id} — body: {title: string} */
    public function update(string $conversationId): never
    {
        if (!$this->repository->conversationExists($conversationId)) {
            JsonResponse::send(404, ['error' => 'Conversazione non trovata.']);
        }

        $input = json_decode((string) file_get_contents('php://input'), true);
        $title = is_array($input) ? trim((string) ($input['title'] ?? '')) : '';

        if ($title === '') {
            JsonResponse::send(400, ['error' => 'Il campo "title" è obbligatorio.']);
        }

        $this->repository->renameConversation($conversationId, mb_substr($title, 0, 120));
        JsonResponse::send(200, ['ok' => true]);
    }

    /** DELETE /api/conversations/{id} */
    public function destroy(string $conversationId): never
    {
        if (!$this->repository->conversationExists($conversationId)) {
            JsonResponse::send(404, ['error' => 'Conversazione non trovata.']);
        }

        $this->repository->deleteConversation($conversationId);
        JsonResponse::send(200, ['ok' => true]);
    }
}

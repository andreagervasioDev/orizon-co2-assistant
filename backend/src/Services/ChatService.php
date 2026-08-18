<?php

declare(strict_types=1);

namespace App\Services;

use App\Clients\EcoFreightClient;
use App\Clients\OpenAIClient;
use App\Prompts\SystemPrompt;
use App\Repositories\ConversationRepository;
use RuntimeException;

/**
 * Orchestrazione del turno di conversazione: system prompt + history + messaggio
 * utente -> OpenAI (Chat Completions, function calling) -> eventuale chiamata
 * al tool EcoFreight -> risposta finale. Ogni passaggio viene persistito tramite
 * il repository, così la history ricaricata ricostruisce fedelmente il contesto.
 */
class ChatService
{
    private const MAX_TOOL_ITERATIONS = 3;

    public function __construct(
        private readonly OpenAIClient $openAi,
        private readonly EcoFreightClient $ecoFreight,
        private readonly ConversationRepository $repository,
    ) {
    }

    /**
     * @return array{reply: string, trip_summary: ?array}
     */
    public function handleTurn(string $conversationId, string $userMessage): array
    {
        $this->repository->addMessage($conversationId, 'user', $userMessage);

        $tripSummary = null;

        for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
            $messages = array_merge(
                [['role' => 'system', 'content' => SystemPrompt::text()]],
                $this->repository->getHistoryForLlm($conversationId)
            );

            $response = $this->openAi->chat($messages, SystemPrompt::tools());
            $choice = $response['choices'][0] ?? null;

            if ($choice === null) {
                throw new RuntimeException('Risposta inattesa da OpenAI: nessuna scelta restituita.');
            }

            $message = $choice['message'];
            $toolCalls = $message['tool_calls'] ?? [];

            if (empty($toolCalls)) {
                $reply = $message['content'] ?? '';
                $this->repository->addMessage($conversationId, 'assistant', $reply);
                return ['reply' => $reply, 'trip_summary' => $tripSummary];
            }

            $this->repository->addMessage($conversationId, 'assistant', $message['content'] ?? null, null, $toolCalls);

            foreach ($toolCalls as $toolCall) {
                $result = $this->executeToolCall($toolCall);
                if ($result['trip_summary'] !== null) {
                    $tripSummary = $result['trip_summary'];
                }
                $this->repository->addMessage(
                    $conversationId,
                    'tool',
                    json_encode($result['content']),
                    $toolCall['id']
                );
            }
        }

        throw new RuntimeException('Numero massimo di chiamate agli strumenti superato.');
    }

    /**
     * @return array{content: array, trip_summary: ?array}
     */
    private function executeToolCall(array $toolCall): array
    {
        $functionName = $toolCall['function']['name'] ?? '';

        if ($functionName !== 'calculate_co2_emissions') {
            return ['content' => ['ok' => false, 'error' => "Strumento sconosciuto: $functionName"], 'trip_summary' => null];
        }

        $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?? [];

        $transportMode = (string) ($arguments['transport_mode'] ?? '');
        $weightKg = (float) ($arguments['weight_kg'] ?? 0);
        $origin = (string) ($arguments['origin'] ?? '');
        $destination = (string) ($arguments['destination'] ?? '');

        $result = $this->ecoFreight->calculate($transportMode, $weightKg, $origin, $destination);

        $tripSummary = null;
        if ($result['ok']) {
            $co2Kg = $this->extractCo2Kg($result['data']);
            $tripSummary = [
                'transport_mode' => $transportMode,
                'weight_kg' => $result['request']['weight_kg'],
                'origin' => $origin,
                'destination' => $destination,
                'co2_kg' => $co2Kg,
            ];
        }

        return ['content' => $result, 'trip_summary' => $tripSummary];
    }

    private function extractCo2Kg(array $data): ?float
    {
        $emissions = $data['emissions'] ?? null;
        if (!is_array($emissions)) {
            return null;
        }

        foreach (['wtw', 'WTW', 'total', 'ttw', 'TTW'] as $key) {
            if (isset($emissions[$key]) && is_numeric($emissions[$key])) {
                return (float) $emissions[$key];
            }
            if (isset($emissions[$key]['value']) && is_numeric($emissions[$key]['value'])) {
                return (float) $emissions[$key]['value'];
            }
        }

        return null;
    }
}

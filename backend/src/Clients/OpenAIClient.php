<?php

declare(strict_types=1);

namespace App\Clients;

use App\Config;
use RuntimeException;

class OpenAIClient
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = (string) Config::get('OPENAI_API_KEY', '');
        $this->model = (string) Config::get('OPENAI_MODEL', 'gpt-4o-mini');

        if ($this->apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY non configurata sul server.');
        }
    }

    /**
     * @param array $messages Storico messaggi nel formato Chat Completions (system incluso)
     * @param array $tools Definizioni dei tool disponibili (formato OpenAI "tools")
     * @return array La risposta completa dell'API (decodificata)
     */
    public function chat(array $messages, array $tools = []): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.3,
        ];

        if (!empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 60,
        ]);

        $responseBody = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($responseBody === false) {
            throw new RuntimeException('Errore di rete chiamando OpenAI: ' . $curlError);
        }

        $decoded = json_decode($responseBody, true);

        if ($statusCode >= 400) {
            $message = $decoded['error']['message'] ?? $responseBody;
            throw new RuntimeException("Errore OpenAI (HTTP $statusCode): $message");
        }

        return $decoded;
    }
}

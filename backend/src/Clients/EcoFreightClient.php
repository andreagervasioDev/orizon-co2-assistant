<?php

declare(strict_types=1);

namespace App\Clients;

use App\Config;

class EcoFreightClient
{
    private const ENDPOINT = 'https://api.ecofreight.co/api/v1/calculate';
    private const MIN_WEIGHT_KG = 50;

    /**
     * Mappa il mezzo di trasporto (linguaggio naturale) sui valori richiesti da EcoFreight.
     * vessel_type è scelto con un default rappresentativo per ciascuna modalità, perché
     * l'utente/LLM non conosce (né deve conoscere) la tassonomia tecnica dei mezzi merci.
     */
    private const MODE_MAP = [
        'aereo' => ['transport_mode' => 'air', 'vessel_type' => 'belly_cargo'],
        'treno' => ['transport_mode' => 'rail', 'vessel_type' => 'freight_train_unknown_traction'],
        'auto' => ['transport_mode' => 'road', 'vessel_type' => 'heavy_truck_16t_plus'],
        'nave' => ['transport_mode' => 'sea', 'vessel_type' => 'container_ship_8000_teu_plus'],
    ];

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) Config::get('ECOFREIGHT_API_KEY', '');
    }

    /**
     * Logica pura, senza I/O: mappa il mezzo di trasporto (linguaggio naturale)
     * sui valori richiesti da EcoFreight, o null se non è supportato. Visibilità
     * public static per essere testabile in isolamento (vedi tests/EcoFreightClientTest.php).
     */
    public static function resolveMode(string $transportMode): ?array
    {
        return self::MODE_MAP[$transportMode] ?? null;
    }

    /**
     * Logica pura, senza I/O: applica il peso minimo richiesto da EcoFreight
     * (50kg), segnalando in una nota se è stato necessario alzare il peso
     * indicato dall'utente. Visibilità public static per essere testabile
     * in isolamento (vedi tests/EcoFreightClientTest.php).
     *
     * @return array{weight: float, note: ?string}
     */
    public static function normalizeWeight(float $weightKg): array
    {
        if ($weightKg >= self::MIN_WEIGHT_KG) {
            return ['weight' => $weightKg, 'note' => null];
        }

        return [
            'weight' => (float) self::MIN_WEIGHT_KG,
            'note' => sprintf(
                'Il peso indicato (%.1f kg) è sotto la soglia minima gestita dal servizio di calcolo (%d kg): '
                . 'il calcolo è stato effettuato usando %d kg come base.',
                $weightKg,
                self::MIN_WEIGHT_KG,
                self::MIN_WEIGHT_KG
            ),
        ];
    }

    /**
     * Esegue la chiamata di calcolo emissioni.
     *
     * @return array{ok: bool, data?: array, error?: string, weight_note?: string}
     */
    public function calculate(string $transportMode, float $weightKg, string $origin, string $destination): array
    {
        if ($this->apiKey === '') {
            return ['ok' => false, 'error' => 'ECOFREIGHT_API_KEY non configurata sul server.'];
        }

        $mapping = self::resolveMode($transportMode);
        if ($mapping === null) {
            return ['ok' => false, 'error' => "Mezzo di trasporto non supportato: $transportMode"];
        }

        $normalized = self::normalizeWeight($weightKg);
        $effectiveWeight = $normalized['weight'];
        $weightNote = $normalized['note'];

        $payload = [
            'origin' => ['query' => $origin],
            'destination' => ['query' => $destination],
            'cargo' => ['weight' => $effectiveWeight, 'type' => 'general'],
            'transport_mode' => $mapping['transport_mode'],
            'vessel_type' => $mapping['vessel_type'],
        ];

        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
        ]);

        $responseBody = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($responseBody === false) {
            return ['ok' => false, 'error' => "Errore di rete verso EcoFreight: $curlError"];
        }

        $decoded = json_decode($responseBody, true);

        if ($statusCode >= 400) {
            $message = $decoded['error']['message'] ?? $decoded['message'] ?? $responseBody;
            return ['ok' => false, 'error' => "Errore EcoFreight (HTTP $statusCode): $message"];
        }

        $result = [
            'ok' => true,
            'data' => $decoded,
            'request' => [
                'transport_mode' => $transportMode,
                'weight_kg' => $effectiveWeight,
                'origin' => $origin,
                'destination' => $destination,
            ],
        ];

        if ($weightNote !== null) {
            $result['weight_note'] = $weightNote;
        }

        return $result;
    }
}

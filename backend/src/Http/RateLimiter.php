<?php

declare(strict_types=1);

namespace App\Http;

use PDO;

/**
 * Rate limit minimo per IP, a finestra fissa, basato sulla stessa SQLite già
 * in uso (nessuna dipendenza esterna tipo Redis). Protegge dall'abuso banale
 * di endpoint che chiamano servizi esterni a pagamento/quota limitata (es.
 * OpenAI, EcoFreight): non è una difesa robusta contro un attacker
 * distribuito su molti IP, ma copre lo scenario più comune (uno script che
 * bombarda l'endpoint da un solo host).
 */
class RateLimiter
{
    public function __construct(
        private readonly PDO $db,
        private readonly int $maxRequests = 10,
        private readonly int $windowSeconds = 60,
    ) {
    }

    /**
     * Registra una richiesta per $ip e ritorna true se ha superato il limite
     * (in tal caso la richiesta NON viene conteggiata come valida).
     */
    public function tooManyRequests(string $ip): bool
    {
        // Pulizia occasionale delle righe vecchie, per non far crescere la tabella indefinitamente.
        if (random_int(1, 20) === 1) {
            $this->prune();
        }

        $windowStart = gmdate('Y-m-d\TH:i:s\Z', time() - $this->windowSeconds);
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM rate_limit_hits WHERE ip = ? AND created_at >= ?'
        );
        $stmt->execute([$ip, $windowStart]);

        if ((int) $stmt->fetchColumn() >= $this->maxRequests) {
            return true;
        }

        $insert = $this->db->prepare('INSERT INTO rate_limit_hits (ip, created_at) VALUES (?, ?)');
        $insert->execute([$ip, gmdate('Y-m-d\TH:i:s\Z')]);

        return false;
    }

    private function prune(): void
    {
        $cutoff = gmdate('Y-m-d\TH:i:s\Z', time() - $this->windowSeconds * 10);
        $this->db->prepare('DELETE FROM rate_limit_hits WHERE created_at < ?')->execute([$cutoff]);
    }
}

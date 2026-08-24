<?php

namespace App;

class Config
{
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        $envPath = dirname(__DIR__) . '/.env';
        if (!is_file($envPath)) {
            return;
        }

        foreach (self::parse((string) file_get_contents($envPath)) as $key => $value) {
            if (getenv($key) === false) {
                putenv("$key=$value");
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();
        $value = getenv($key);
        return $value === false ? $default : $value;
    }

    /**
     * Parser minimale in stile dotenv, carattere per carattere (non riga per
     * riga, a differenza della versione precedente): serve a gestire valori
     * tra apici singoli (letterali, nessun escape), apici doppi (con escape
     * \n \t \" \\ e possibilità di andare a capo dentro il valore) e valori
     * senza apici (troncati al primo # o a fine riga).
     *
     * @return array<string, string>
     */
    private static function parse(string $content): array
    {
        $values = [];
        $length = strlen($content);
        $pos = 0;

        while ($pos < $length) {
            while ($pos < $length && ctype_space($content[$pos])) {
                $pos++;
            }
            if ($pos >= $length) {
                break;
            }

            if ($content[$pos] === '#') {
                $pos = self::skipToEndOfLine($content, $pos);
                continue;
            }

            $keyStart = $pos;
            while ($pos < $length && $content[$pos] !== '=' && $content[$pos] !== "\n") {
                $pos++;
            }
            if ($pos >= $length || $content[$pos] !== '=') {
                // Riga senza "=": non è una variabile valida, la saltiamo.
                $pos = self::skipToEndOfLine($content, $pos);
                continue;
            }
            $key = trim(substr($content, $keyStart, $pos - $keyStart));
            $pos++; // salta '='

            while ($pos < $length && ($content[$pos] === ' ' || $content[$pos] === "\t")) {
                $pos++;
            }

            if ($pos < $length && ($content[$pos] === '"' || $content[$pos] === "'")) {
                // Dopo la virgoletta di chiusura siamo ancora sulla stessa riga
                // (potrebbe seguire un commento): va consumato il resto della riga.
                [$value, $pos] = self::parseQuotedValue($content, $pos, $content[$pos]);
                $pos = self::skipToEndOfLine($content, $pos);
            } else {
                // Qui skipToEndOfLine ci porta già all'inizio della riga successiva:
                // non va richiamato una seconda volta, altrimenti si salterebbe una riga intera.
                $lineEnd = self::skipToEndOfLine($content, $pos);
                $raw = substr($content, $pos, $lineEnd - $pos);
                $hashPos = strpos($raw, '#');
                $value = trim($hashPos === false ? $raw : substr($raw, 0, $hashPos));
                $pos = $lineEnd;
            }

            if ($key !== '') {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    /**
     * @return array{0: string, 1: int} valore decodificato e posizione subito dopo la virgoletta di chiusura
     */
    private static function parseQuotedValue(string $content, int $pos, string $quote): array
    {
        $length = strlen($content);
        $value = '';
        $pos++; // salta la virgoletta di apertura

        while ($pos < $length && $content[$pos] !== $quote) {
            if ($quote === '"' && $content[$pos] === '\\' && $pos + 1 < $length) {
                $value .= match ($content[$pos + 1]) {
                    'n' => "\n",
                    't' => "\t",
                    '"' => '"',
                    '\\' => '\\',
                    default => $content[$pos + 1],
                };
                $pos += 2;
                continue;
            }
            $value .= $content[$pos];
            $pos++;
        }

        return [$value, $pos + 1];
    }

    private static function skipToEndOfLine(string $content, int $pos): int
    {
        $eol = strpos($content, "\n", $pos);
        return $eol === false ? strlen($content) : $eol + 1;
    }
}

<?php

declare(strict_types=1);

/**
 * Runner di test minimale, senza dipendenze esterne (niente PHPUnit/Composer,
 * coerente con la scelta di "PHP puro" del progetto — vedi README).
 *
 * Ogni file tests/*Test.php definisce funzioni che iniziano per "test_"; questo
 * script le esegue tutte e riporta un riepilogo, con exit code 1 se qualcosa
 * fallisce (così è già pronto per essere invocato da una CI in futuro).
 *
 * Uso: php backend/tests/run.php
 */

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require __DIR__ . '/Assert.php';

$before = get_defined_functions()['user'];

foreach (glob(__DIR__ . '/*Test.php') as $testFile) {
    require $testFile;
}

$testFunctions = array_diff(get_defined_functions()['user'], $before);

$passed = 0;
$failed = 0;

foreach ($testFunctions as $function) {
    if (!str_starts_with($function, 'test_')) {
        continue;
    }
    try {
        $function();
        echo "  OK  $function\n";
        $passed++;
    } catch (Throwable $e) {
        echo "FAIL  $function\n       " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n$passed passati, $failed falliti su " . ($passed + $failed) . " test.\n";
exit($failed > 0 ? 1 : 0);

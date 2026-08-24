<?php

declare(strict_types=1);

function assertSame(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            '%sAtteso %s, ottenuto %s',
            $message === '' ? '' : "$message: ",
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function assertNull(mixed $actual, string $message = ''): void
{
    assertSame(null, $actual, $message);
}

function assertNotNull(mixed $actual, string $message = ''): void
{
    if ($actual === null) {
        throw new RuntimeException($message === '' ? 'Atteso un valore non null.' : $message);
    }
}

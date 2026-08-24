<?php

declare(strict_types=1);

use App\Services\ChatService;

function test_extractCo2Kg_legge_chiave_wtw_numerica(): void
{
    $co2 = ChatService::extractCo2Kg(['emissions' => ['wtw' => 123.45]]);
    assertSame(123.45, $co2);
}

function test_extractCo2Kg_legge_chiave_WTW_maiuscola(): void
{
    $co2 = ChatService::extractCo2Kg(['emissions' => ['WTW' => 50]]);
    assertSame(50.0, $co2);
}

function test_extractCo2Kg_legge_valore_annidato_in_value(): void
{
    $co2 = ChatService::extractCo2Kg(['emissions' => ['total' => ['value' => 77.7]]]);
    assertSame(77.7, $co2);
}

function test_extractCo2Kg_rispetta_ordine_di_priorita_tra_le_chiavi(): void
{
    // "wtw" precede "total" nell'elenco delle chiavi candidate: deve vincere lui.
    $co2 = ChatService::extractCo2Kg(['emissions' => ['total' => 999, 'wtw' => 111]]);
    assertSame(111.0, $co2);
}

function test_extractCo2Kg_ritorna_null_se_emissions_mancante(): void
{
    assertNull(ChatService::extractCo2Kg(['foo' => 'bar']));
}

function test_extractCo2Kg_ritorna_null_se_nessuna_chiave_nota(): void
{
    assertNull(ChatService::extractCo2Kg(['emissions' => ['unknown_key' => 10]]));
}

function test_extractCo2Kg_ignora_valori_non_numerici(): void
{
    assertNull(ChatService::extractCo2Kg(['emissions' => ['wtw' => 'n/d']]));
}

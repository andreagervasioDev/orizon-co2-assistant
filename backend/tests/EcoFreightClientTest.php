<?php

declare(strict_types=1);

use App\Clients\EcoFreightClient;

function test_resolveMode_riconosce_aereo(): void
{
    $mapping = EcoFreightClient::resolveMode('aereo');
    assertSame(['transport_mode' => 'air', 'vessel_type' => 'belly_cargo'], $mapping);
}

function test_resolveMode_riconosce_nave(): void
{
    $mapping = EcoFreightClient::resolveMode('nave');
    assertSame(['transport_mode' => 'sea', 'vessel_type' => 'container_ship_8000_teu_plus'], $mapping);
}

function test_resolveMode_ritorna_null_per_mezzo_sconosciuto(): void
{
    assertNull(EcoFreightClient::resolveMode('bicicletta'));
}

function test_normalizeWeight_lascia_invariato_peso_sopra_soglia(): void
{
    $result = EcoFreightClient::normalizeWeight(100.0);
    assertSame(100.0, $result['weight']);
    assertNull($result['note']);
}

function test_normalizeWeight_alza_peso_sotto_soglia_e_segnala_una_nota(): void
{
    $result = EcoFreightClient::normalizeWeight(10.0);
    assertSame(50.0, $result['weight']);
    assertNotNull($result['note'], 'Attesa una nota sul peso minimo applicato.');
}

function test_normalizeWeight_alla_soglia_esatta_non_genera_nota(): void
{
    $result = EcoFreightClient::normalizeWeight(50.0);
    assertSame(50.0, $result['weight']);
    assertNull($result['note']);
}

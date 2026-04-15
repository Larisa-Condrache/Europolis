<?php

declare(strict_types=1);

namespace Europolis\Services;

/**
 * BoardService — inițializarea tablei și rezolvarea căsuțelor.
 *
 * (Cap. 3 — Modelul problemei, Etapa 1)
 */
class BoardService
{
    /**
     * Inițializează tabla cu cele 40 de căsuțe standard.
     * TODO: implementare — lista completă a căsuțelor (proprietăți, taxe, șansă, etc.)
     */
    public function initializeBoard(int $gameId): array
    {
        // TODO: returnează array cu 40 de Tile-uri configurate
        // Exemplu structură:
        // index 0  → START
        // index 1  → Proprietate (maro)
        // index 2  → Caseta comunitate
        // index 4  → Impozit pe venit (taxă)
        // index 5  → Gară
        // index 10 → Închisoare (vizitator)
        // index 20 → Parcare liberă
        // index 30 → Du-te la închisoare
        // index 38 → Impozit de lux (taxă)
        // ...
        return [];
    }

    /**
     * Determină tipul căsuței și acțiunile disponibile pentru jucătorul curent.
     * TODO: implementare
     */
    public function resolveTile(int $position, int $playerId, int $gameId): array
    {
        // TODO: returnează ['tile' => Tile, 'availableActions' => [...]]
        return [];
    }

    /**
     * Aplică efectul unei cărți de șansă / comunitate.
     * TODO: implementare
     */
    public function applyCardEffect(array $card, int $playerId, int $gameId): array
    {
        // TODO: switch pe $card['effectType']:
        //   'move'               → mută jucătorul la $card['effectData']['position']
        //   'move_relative'      → mută jucătorul cu N pași
        //   'pay'                → scade $card['effectData']['amount'] din sold
        //   'collect'            → adaugă $card['effectData']['amount'] la sold
        //   'collect_from_players' → fiecare jucător activ plătește jucătorului curent
        //   'pay_to_players'     → jucătorul curent plătește fiecărui jucător activ
        //   'get_out_of_jail'    → jucătorul primește carta specială
        //   'go_to_jail'         → jucătorul este trimis la închisoare (index 10)
        return [];
    }
}

<?php

declare(strict_types=1);

namespace Europolis\Models;

/**
 * Board — tabla de joc.
 *
 * Conține exact 40 de căsuțe aranjate circular.
 * (Cap. 3 — Modelul problemei, Etapa 1)
 */
class Board
{
    /** @var Tile[] exact 40 de căsuțe (index 0–39) */
    public array $tiles = [];

    /**
     * Returnează căsuța de la o poziție dată.
     */
    public function getTile(int $position): Tile
    {
        // TODO: implementare
        return $this->tiles[$position % 40];
    }

    /**
     * Calculează noua poziție după aruncarea zarurilor.
     * Gestionează trecerea prin START (colectare 200).
     */
    public function advance(int $currentPosition, int $steps): array
    {
        // TODO: implementare
        // return ['position' => $newPosition, 'passedStart' => bool]
        return [];
    }
}

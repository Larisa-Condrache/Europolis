<?php

declare(strict_types=1);

namespace Europolis\Models;

/**
 * Board — tabla de joc.
 *
 * Conține căsuțele tablei aranjate circular.
 * (Cap. 3 — Modelul problemei, Etapa 1)
 */
class Board
{
    /** @var Tile[] căsuțele tablei, indexate circular */
    public array $tiles = [];

    /**
     * Returnează căsuța de la o poziție dată.
     */
    public function getTile(int $position): Tile
    {
        // TODO: implementare
        return $this->tiles[$position % count($this->tiles)];
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

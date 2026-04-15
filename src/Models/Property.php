<?php

declare(strict_types=1);

namespace Europolis\Models;

/**
 * Property — un tip de Tile cumpărabil.
 *
 * Aparține unui grup de culoare.
 * Poate fi ipotecată (mortgaged).
 * (Cap. 3 — Modelul problemei, Etapa 1)
 */
class Property
{
    public int     $id;
    public int     $tileIndex;       // poziția pe tablă (0–39)
    public string  $name;
    public string  $colorGroup;      // ex: 'brown', 'blue', 'green' etc.

    public int     $price;           // preț de cumpărare
    public int     $mortgageValue;   // valoare ipotecă (de regulă price / 2)
    public int     $houseCost;       // costul unei case
    public int     $hotelCost;       // costul unui hotel

    /** @var int[] chiria pentru 0–4 case și hotel [fără casă, 1 casă, ..., 4 case, hotel] */
    public array   $rentLevels = [];

    public ?int    $ownerId    = null;   // null = disponibilă (la bancă)
    public bool    $mortgaged  = false;

    /** @var Building[] max 4 case SAU 1 hotel */
    public array   $buildings  = [];

    // Reguli de business (Cap. 5):
    // - Nu poți construi fără a deține toate proprietățile din grup (422)
    // - Max 4 case sau 1 hotel per proprietate (422)
    // - Nu poți ipoteca o proprietate cu clădiri pe ea (422)
    // - O proprietate ipotecată nu generează chirie
}

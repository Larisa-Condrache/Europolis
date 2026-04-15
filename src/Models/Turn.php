<?php

declare(strict_types=1);

namespace Europolis\Models;

/**
 * Turn — tura unui jucător.
 *
 * Conține zarurile aruncate și acțiunile disponibile/executate.
 * (Cap. 3 — Modelul problemei, Etapa 1)
 */
class Turn
{
    public int   $id;
    public int   $gameId;
    public int   $playerId;

    public int   $die1;
    public int   $die2;

    public int   $fromPosition;
    public int   $toPosition;

    /** @var string[] ex: ['buy_property', 'end_turn'] */
    public array $availableActions = [];

    /** @var string[] acțiuni deja executate în această tură */
    public array $executedActions  = [];

    public bool  $isDouble;      // true dacă die1 === die2 → jucătorul aruncă din nou
    public int   $doubleCount = 0; // dacă ajunge la 3 → du-te la închisoare

    // Reguli de business (Cap. 5):
    // - Zarurile se aruncă o singură dată per tură, excepție: dublu (409)
    // - Un jucător poate acționa doar în tura sa (403)
}

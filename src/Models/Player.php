<?php

declare(strict_types=1);

namespace Europolis\Models;

/**
 * Player — un participant la joc.
 *
 * Status posibil: active | bankrupt
 * (Cap. 3 — Modelul problemei, Etapa 1)
 */
class Player
{
    public int    $id;
    public int    $gameId;
    public string $name;
    public int    $balance;     // sold curent (start: 1500)
    public int    $position;    // poziție pe tablă (0–39)
    public string $status;      // active | bankrupt

    /** @var Property[] proprietăți deținute */
    public array  $properties = [];

    public bool   $inJail      = false;
    public int    $jailTurns   = 0;     // număr de ture petrecute în închisoare
    public bool   $hasGetOutOfJailCard = false;

    // TODO: datorii active (Loan)
}

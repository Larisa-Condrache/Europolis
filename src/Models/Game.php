<?php

declare(strict_types=1);

namespace Europolis\Models;

/**
 * Game — o sesiune de joc.
 *
 * Stări posibile: waiting | active | finished
 * (Cap. 3 — Modelul problemei, Etapa 1)
 */
class Game
{
    public int    $id;
    public string $status;      // waiting | active | finished
    public int    $maxPlayers;
    public int    $currentPlayerId;

    /** @var Player[] */
    public array  $players = [];

    public Board  $board;

    // TODO: relație cu Bank (singleton per joc)
    // TODO: succesiune de Turn-uri
}

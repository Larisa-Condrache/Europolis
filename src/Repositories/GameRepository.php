<?php

declare(strict_types=1);

namespace Europolis\Repositories;

/**
 * GameRepository — acces la baza de date pentru sesiunile de joc.
 * TODO: implementare cu PDO + MySQL/PostgreSQL
 */
class GameRepository
{
    // public function __construct(private \PDO $pdo) {}

    /** Creează o sesiune nouă. Returnează înregistrarea creată. */
    public function create(string $hostName, int $maxPlayers): array
    {
        // TODO: INSERT INTO games (status, max_players) VALUES ('waiting', $maxPlayers)
        return [];
    }

    /** Returnează un joc după ID. Null dacă nu există. */
    public function findById(int $gameId): ?array
    {
        // TODO: SELECT * FROM games WHERE id = $gameId
        return null;
    }

    /** Actualizează statusul unui joc. */
    public function updateStatus(int $gameId, string $status): void
    {
        // TODO: UPDATE games SET status = $status WHERE id = $gameId
    }

    /** Actualizează jucătorul curent. */
    public function updateCurrentPlayer(int $gameId, int $playerId): void
    {
        // TODO: UPDATE games SET current_player_id = $playerId WHERE id = $gameId
    }
}

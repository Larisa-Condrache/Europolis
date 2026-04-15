<?php

declare(strict_types=1);

namespace Europolis\Repositories;

/**
 * PlayerRepository — acces la baza de date pentru jucători.
 * TODO: implementare cu PDO + MySQL/PostgreSQL
 */
class PlayerRepository
{
    // public function __construct(private \PDO $pdo) {}

    /** Adaugă un jucător la un joc. */
    public function create(int $gameId, string $name): array
    {
        // TODO: INSERT INTO players (game_id, name, balance, position, status)
        //        VALUES ($gameId, $name, 1500, 0, 'active')
        return [];
    }

    /** Returnează un jucător după ID. */
    public function findById(int $playerId): ?array
    {
        // TODO: SELECT * FROM players WHERE id = $playerId
        return null;
    }

    /** Returnează toți jucătorii unui joc. */
    public function findByGame(int $gameId): array
    {
        // TODO: SELECT * FROM players WHERE game_id = $gameId ORDER BY id
        return [];
    }

    /** Actualizează soldul unui jucător. */
    public function updateBalance(int $playerId, int $delta): void
    {
        // TODO: UPDATE players SET balance = balance + $delta WHERE id = $playerId
    }

    /** Actualizează poziția unui jucător. */
    public function updatePosition(int $playerId, int $position): void
    {
        // TODO: UPDATE players SET position = $position WHERE id = $playerId
    }

    /** Actualizează statusul unui jucător (active | bankrupt). */
    public function updateStatus(int $playerId, string $status): void
    {
        // TODO: UPDATE players SET status = $status WHERE id = $playerId
    }

    /** Actualizează statusul de închisoare al unui jucător. */
    public function updateJailStatus(int $playerId, bool $inJail): void
    {
        // TODO: UPDATE players SET in_jail = $inJail WHERE id = $playerId
    }
}

<?php

declare(strict_types=1);

namespace Europolis\Repositories;

/**
 * TurnRepository — acces la baza de date pentru ture.
 * TODO: implementare cu PDO + MySQL/PostgreSQL
 */
class TurnRepository
{
    // public function __construct(private \PDO $pdo) {}

    /** Creează o tură nouă. */
    public function create(int $gameId, int $playerId, int $die1, int $die2, int $fromPos, int $toPos): array
    {
        // TODO: INSERT INTO turns (game_id, player_id, die1, die2, from_position, to_position)
        return [];
    }

    /** Returnează o tură după ID. */
    public function findById(int $gameId, int $turnId): ?array
    {
        // TODO: SELECT * FROM turns WHERE id = $turnId AND game_id = $gameId
        return null;
    }

    /** Actualizează acțiunile disponibile ale unei ture. */
    public function updateAvailableActions(int $turnId, array $actions): void
    {
        // TODO: UPDATE turns SET available_actions = JSON($actions) WHERE id = $turnId
    }
}

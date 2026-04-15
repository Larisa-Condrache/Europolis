<?php

declare(strict_types=1);

namespace Europolis\Repositories;

/**
 * TradeRepository — acces la baza de date pentru schimburi.
 * TODO: implementare cu PDO + MySQL/PostgreSQL
 */
class TradeRepository
{
    // public function __construct(private \PDO $pdo) {}

    /** Creează o propunere de schimb. */
    public function create(
        int   $gameId,
        int   $fromPlayerId,
        int   $toPlayerId,
        int   $offeredMoney,
        array $offeredPropertyIds,
        int   $requestedMoney,
        array $requestedPropertyIds
    ): array {
        // TODO: INSERT INTO trades (game_id, from_player_id, to_player_id, status, ...)
        //        VALUES ($gameId, $fromPlayerId, $toPlayerId, 'pending', ...)
        return [];
    }

    /** Returnează un trade după ID. */
    public function findById(int $gameId, int $tradeId): ?array
    {
        // TODO: SELECT * FROM trades WHERE id = $tradeId AND game_id = $gameId
        return null;
    }

    /** Actualizează statusul unui trade (pending → accepted | rejected). */
    public function updateStatus(int $tradeId, string $status): void
    {
        // TODO: UPDATE trades SET status = $status WHERE id = $tradeId
    }
}

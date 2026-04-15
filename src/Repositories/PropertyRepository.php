<?php

declare(strict_types=1);

namespace Europolis\Repositories;

/**
 * PropertyRepository — acces la baza de date pentru proprietăți.
 * TODO: implementare cu PDO + MySQL/PostgreSQL
 */
class PropertyRepository
{
    // public function __construct(private \PDO $pdo) {}

    /** Returnează o proprietate după tile index și game. */
    public function findByTile(int $gameId, int $tileIndex): ?array
    {
        // TODO: SELECT * FROM properties WHERE game_id = $gameId AND tile_index = $tileIndex
        return null;
    }

    /** Returnează toate proprietățile unui jucător. */
    public function findByOwner(int $gameId, int $playerId): array
    {
        // TODO: SELECT * FROM properties WHERE game_id = $gameId AND owner_id = $playerId
        return [];
    }

    /** Returnează toate proprietățile dintr-un grup de culoare. */
    public function findByColorGroup(int $gameId, string $colorGroup): array
    {
        // TODO: SELECT * FROM properties WHERE game_id = $gameId AND color_group = $colorGroup
        return [];
    }

    /** Actualizează proprietarul unei proprietăți. */
    public function updateOwner(int $propertyId, ?int $playerId): void
    {
        // TODO: UPDATE properties SET owner_id = $playerId WHERE id = $propertyId
    }

    /** Actualizează statusul de ipotecare. */
    public function updateMortgaged(int $propertyId, bool $mortgaged): void
    {
        // TODO: UPDATE properties SET mortgaged = $mortgaged WHERE id = $propertyId
    }
}

<?php

declare(strict_types=1);

namespace Europolis\Repositories;

/**
 * BuildingRepository — acces la baza de date pentru clădiri.
 * TODO: implementare cu PDO + MySQL/PostgreSQL
 */
class BuildingRepository
{
    // public function __construct(private \PDO $pdo) {}

    /** Adaugă o clădire pe o proprietate. */
    public function create(int $propertyId, string $type): array
    {
        // TODO: INSERT INTO buildings (property_id, type) VALUES ($propertyId, $type)
        // type: 'house' | 'hotel'
        return [];
    }

    /** Returnează toate clădirile de pe o proprietate. */
    public function findByProperty(int $propertyId): array
    {
        // TODO: SELECT * FROM buildings WHERE property_id = $propertyId
        return [];
    }

    /** Șterge o clădire (ex: demolare la ipotecare sau finalizare joc). */
    public function delete(int $buildingId): void
    {
        // TODO: DELETE FROM buildings WHERE id = $buildingId
    }

    /** Șterge toate clădirile de pe o proprietate. */
    public function deleteByProperty(int $propertyId): void
    {
        // TODO: DELETE FROM buildings WHERE property_id = $propertyId
    }
}

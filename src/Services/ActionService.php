<?php

declare(strict_types=1);

namespace Europolis\Services;

/**
 * ActionService — execuția acțiunilor disponibile într-o tură.
 *
 * Acoperă UC5 (Executare acțiune).
 * Regulile de business sunt validate conform Cap. 5.
 * (Cap. 4 — Actorii sistemului și Use-Cases, Etapa 1)
 */
class ActionService
{
    /**
     * Cumpărare proprietate.
     * Regulă: nu poți cumpăra o proprietate aparținând altui jucător (409).
     * TODO: implementare
     */
    public function buyProperty(int $gameId, int $playerId, int $tileIndex): array
    {
        // TODO: verifică că proprietatea este disponibilă (ownerId === null) → 409
        // TODO: verifică că jucătorul are suficiente fonduri → 422 INSUFFICIENT_FUNDS
        // TODO: Bank::sellProperty(...)
        // TODO: PropertyRepository::updateOwner($propertyId, $playerId)
        // TODO: PlayerRepository::updateBalance($playerId, -$price)
        return [];
    }

    /**
     * Plată chirie.
     * TODO: implementare
     */
    public function payRent(int $gameId, int $playerId, int $tileIndex): array
    {
        // TODO: calculează chiria în funcție de numărul de clădiri
        // TODO: dacă proprietatea este ipotecată → nu se plătește chirie
        // TODO: Bank::collectRent($tenant, $owner, $rentAmount)
        // TODO: verifică dacă jucătorul a rămas fără bani → UC8 Declarare faliment
        return [];
    }

    /**
     * Plată taxă (impozit).
     * TODO: implementare
     */
    public function payTax(int $gameId, int $playerId, int $tileIndex): array
    {
        // TODO: determină suma taxei din configurația tablei
        // TODO: PlayerRepository::updateBalance($playerId, -$taxAmount)
        return [];
    }

    /**
     * Tragere carte de șansă sau comunitate.
     * TODO: implementare
     */
    public function drawCard(int $gameId, int $playerId, string $deck): array
    {
        // TODO: CardRepository::drawRandom($deck) → Card
        // TODO: aplică efectul cărții (move, pay, collect, get_out_of_jail etc.)
        // TODO: returnează carta trasă și efectul aplicat
        return [];
    }

    /**
     * Construire casă pe o proprietate.
     * Regulă: trebuie să deții toate proprietățile din grup (422).
     * Regulă: max 4 case sau 1 hotel per proprietate (422).
     * TODO: implementare
     */
    public function buildHouse(int $gameId, int $playerId, int $propertyId): array
    {
        // TODO: verifică că jucătorul deține proprietatea
        // TODO: verifică că deține întreg grupul de culoare → 422 INCOMPLETE_COLOR_GROUP
        // TODO: verifică că numărul de case < 4 → 422 MAX_HOUSES_REACHED
        // TODO: verifică stocul băncii (housesAvailable > 0)
        // TODO: BuildingRepository::create($propertyId, 'house')
        // TODO: Bank housesAvailable--
        return [];
    }

    /**
     * Construire hotel pe o proprietate (necesită 4 case existente).
     * TODO: implementare
     */
    public function buildHotel(int $gameId, int $playerId, int $propertyId): array
    {
        // TODO: verifică că proprietatea are exact 4 case → 422
        // TODO: verifică stocul băncii (hotelsAvailable > 0)
        // TODO: înlocuiește cele 4 case cu 1 hotel
        // TODO: Bank housesAvailable += 4, hotelsAvailable--
        return [];
    }

    /**
     * UC10 — Ipotecare proprietate.
     * Regulă: nu poți ipoteca o proprietate cu clădiri pe ea (422).
     * TODO: implementare
     */
    public function mortgageProperty(int $gameId, int $playerId, int $propertyId): array
    {
        // TODO: verifică că jucătorul deține proprietatea
        // TODO: verifică că nu are clădiri → 422 HAS_BUILDINGS
        // TODO: Bank::mortgage($property, $player)
        // TODO: PropertyRepository::updateMortgaged($propertyId, true)
        return [];
    }

    /**
     * Plată amendă pentru eliberare din închisoare.
     * Condiții de eliberare: plată | carte specială | dublu (Cap. 5).
     * TODO: implementare
     */
    public function payJailFine(int $gameId, int $playerId): array
    {
        // TODO: verifică că jucătorul este în închisoare → 422
        // TODO: scade amenda (50) din soldul jucătorului
        // TODO: PlayerRepository::updateJailStatus($playerId, false)
        return [];
    }

    /**
     * Încheiere tură.
     * TODO: implementare
     */
    public function endTurn(int $gameId, int $turnId): array
    {
        // TODO: TurnService::advanceTurn($gameId) → trece la următorul jucător
        return [];
    }
}

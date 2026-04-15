<?php

declare(strict_types=1);

namespace Europolis\Services;

/**
 * TurnService — logica de business pentru ture.
 *
 * Acoperă UC4 (Inițiere tură) și UC5 (Executare acțiune).
 * (Cap. 4 — Actorii sistemului și Use-Cases, Etapa 1)
 */
class TurnService
{
    /**
     * UC4 — Inițiere tură.
     * Aruncă zarurile, calculează noua poziție, determină acțiunile disponibile.
     * TODO: implementare
     */
    public function startTurn(int $gameId, int $playerId): array
    {
        // TODO: verifică că este tura jucătorului → 403 NOT_YOUR_TURN
        // TODO: verifică că zarurile nu au fost deja aruncate → 409 DICE_ALREADY_ROLLED
        // TODO: aruncă zarurile (rand 1–6 fiecare)
        // TODO: calculează noua poziție (% 40), gestionează trecerea prin START
        // TODO: BoardService::resolveTile($newPosition) → tipul căsuței
        // TODO: determină availableActions în funcție de tipul căsuței:
        //         property nedeținută → ['buy_property', 'end_turn']
        //         property deținută de altul → ['pay_rent']
        //         property deținută de tine → ['end_turn']
        //         tax → ['pay_tax']
        //         chance/community → ['draw_card']
        //         jail → ['pay_jail_fine', 'end_turn'] sau ['end_turn'] dacă e vizitator
        //         go_to_jail → ['end_turn'] (mutarea la închisoare e automată)
        // TODO: TurnRepository::create(...)
        return [];
    }

    /**
     * Aruncarea zarurilor (logică pură, fără efecte secundare).
     */
    public function rollDice(): array
    {
        return [rand(1, 6), rand(1, 6)];
    }

    /**
     * UC5 — Executare acțiune.
     * Validează și execută o acțiune disponibilă în tura curentă.
     * TODO: implementare
     */
    public function executeAction(int $gameId, int $turnId, string $actionType): array
    {
        // TODO: verifică că actionType este în availableActions ale turei → 403
        // TODO: delegă execuția la ActionService::execute($actionType, ...)
        // TODO: actualizează availableActions după execuție
        // TODO: returnează starea actualizată a jucătorului și a jocului
        return [];
    }

    /**
     * Verifică dacă tura s-a terminat (end_turn a fost executat).
     * TODO: implementare
     */
    public function advanceTurn(int $gameId): void
    {
        // TODO: determină următorul jucător activ (skip bankrupt)
        // TODO: GameRepository::updateCurrentPlayer($gameId, $nextPlayerId)
    }
}

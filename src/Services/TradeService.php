<?php

declare(strict_types=1);

namespace Europolis\Services;

/**
 * TradeService — logica de business pentru schimburi între jucători.
 *
 * Acoperă UC6 (Propunere trade) și UC7 (Răspuns trade).
 * (Cap. 4 — Actorii sistemului și Use-Cases, Etapa 1)
 */
class TradeService
{
    /**
     * UC6 — Creare propunere de schimb.
     * Regulă: schimburile sunt valide doar pentru bunuri proprii (403).
     * TODO: implementare
     */
    public function createTrade(
        int   $gameId,
        int   $fromPlayerId,
        int   $toPlayerId,
        int   $offeredMoney,
        array $offeredPropertyIds,
        int   $requestedMoney,
        array $requestedPropertyIds
    ): array {
        // TODO: verifică că $fromPlayerId deține toate $offeredPropertyIds → 403
        // TODO: verifică că $toPlayerId deține toate $requestedPropertyIds → 403
        // TODO: verifică că $toPlayerId există în joc → 404
        // TODO: TradeRepository::create(...)
        return [];
    }

    /**
     * UC7 — Acceptare sau respingere propunere de schimb.
     * TODO: implementare
     */
    public function respondToTrade(int $gameId, int $tradeId, string $status): array
    {
        // TODO: verifică că trade-ul există → 404
        // TODO: verifică că cel care răspunde este destinatarul → 403
        // TODO: verifică că trade-ul este în starea 'pending' → 409
        // TODO: dacă status === 'accepted' → transferă proprietăți și bani
        //         PropertyRepository::updateOwner pentru fiecare proprietate implicată
        //         PlayerRepository::updateBalance pentru ambii jucători
        // TODO: TradeRepository::updateStatus($tradeId, $status)
        return [];
    }
}

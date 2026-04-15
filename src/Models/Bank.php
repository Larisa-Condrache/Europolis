<?php

declare(strict_types=1);

namespace Europolis\Models;

/**
 * Bank — entitate singleton per joc.
 *
 * Gestionează stocul de clădiri și tranzacțiile financiare.
 * (Cap. 3 — Modelul problemei, Etapa 1)
 */
class Bank
{
    public int $gameId;
    public int $housesAvailable = 32;   // stoc standard de case
    public int $hotelsAvailable = 12;   // stoc standard de hoteluri

    /**
     * Procesează cumpărarea unei proprietăți.
     * (UC5 — Executare acțiune)
     * TODO: implementare
     */
    public function sellProperty(Property $property, Player $buyer): void
    {
        // TODO: scade price din soldul jucătorului
        // TODO: setează ownerId pe proprietate
    }

    /**
     * Colectează chiria și o transferă proprietarului.
     * (UC5 — Executare acțiune)
     * TODO: implementare
     */
    public function collectRent(Player $tenant, Player $owner, int $amount): void
    {
        // TODO: scade amount din soldul tenant
        // TODO: adaugă amount la soldul owner
    }

    /**
     * Acordă un împrumut pentru ipotecarea unei proprietăți.
     * (UC10 — Ipotecare)
     * TODO: implementare
     */
    public function mortgage(Property $property, Player $player): void
    {
        // TODO: verifică că proprietatea nu are clădiri (422)
        // TODO: adaugă mortgageValue la soldul jucătorului
        // TODO: setează property->mortgaged = true
    }

    /**
     * Declară un jucător bankrupt și redistribuie activele.
     * (UC8 — Declarare faliment)
     * TODO: implementare
     */
    public function declareBankruptcy(Player $player, Game $game): void
    {
        // TODO: redistribuie proprietăți conform regulilor
        // TODO: setează player->status = 'bankrupt'
        // TODO: verifică dacă mai există un singur jucător activ → finalizare joc
    }
}

<?php

declare(strict_types=1);

namespace Europolis\Models;

/**
 * Trade — o tranzacție de negociere între doi jucători.
 *
 * Poate include proprietăți și/sau bani în ambele direcții.
 * Status posibil: pending | accepted | rejected
 * (Cap. 3 — Modelul problemei, Etapa 1)
 */
class Trade
{
    public int    $id;
    public int    $gameId;
    public int    $fromPlayerId;
    public int    $toPlayerId;
    public string $status;          // pending | accepted | rejected

    public int    $offeredMoney    = 0;
    public int    $requestedMoney  = 0;

    /** @var int[] ID-uri de proprietăți oferite de fromPlayer */
    public array  $offeredPropertyIds   = [];

    /** @var int[] ID-uri de proprietăți cerute de la toPlayer */
    public array  $requestedPropertyIds = [];

    // Reguli de business (Cap. 5):
    // - Schimburile sunt valide doar pentru bunuri proprii (403)
    // - Un trade pending poate fi acceptat sau respins de destinatar (UC7)
}

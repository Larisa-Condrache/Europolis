<?php

declare(strict_types=1);

namespace Europolis\Services;

/**
 * GameService — logica de business pentru sesiunile de joc.
 *
 * Acoperă UC1 (Creare joc), UC3 (Vizualizare stare), UC9 (Finalizare joc).
 * (Cap. 4 — Actorii sistemului și Use-Cases, Etapa 1)
 */
class GameService
{
    /**
     * UC1 — Creare joc.
     * Inițializează sesiunea, tabla și înregistrează host-ul ca prim jucător.
     * TODO: implementare
     */
    public function createGame(string $hostName, int $maxPlayers): array
    {
        // TODO: validare maxPlayers (2–6)
        // TODO: GameRepository::create(...)
        // TODO: BoardService::initializeBoard() → 40 de căsuțe
        // TODO: PlayerRepository::create($gameId, $hostName, balance=1500, position=0)
        return [];
    }

    /**
     * UC3 — Vizualizare stare joc.
     * Returnează starea curentă: status, jucători, jucătorul curent, poziții, solduri.
     * TODO: implementare
     */
    public function getGameState(int $gameId): array
    {
        // TODO: GameRepository::findById($gameId) → 404 dacă nu există
        return [];
    }

    /**
     * Pornire joc (PATCH /games/{id} cu status: active).
     * Poate fi apelat doar de Host.
     * TODO: implementare
     */
    public function startGame(int $gameId, int $requestingPlayerId): void
    {
        // TODO: verifică că requestingPlayerId este host-ul (primul jucător)
        // TODO: verifică că sunt cel puțin 2 jucători
        // TODO: GameRepository::updateStatus($gameId, 'active')
        // TODO: stabilește primul jucător curent (aleator sau primul înscris)
    }

    /**
     * UC9 — Finalizare joc.
     * Poate fi declanșat manual (Host) sau automat (un singur jucător activ rămas).
     * TODO: implementare
     */
    public function endGame(int $gameId): void
    {
        // TODO: GameRepository::updateStatus($gameId, 'finished')
        // TODO: calculează clasamentul final (după sold + valoare proprietăți)
    }
}

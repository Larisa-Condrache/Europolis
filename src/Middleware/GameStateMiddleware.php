<?php

declare(strict_types=1);

namespace Europolis\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * GameStateMiddleware — verifică că jocul există și este în starea 'active'
 * înainte de a permite acțiuni de joc (ture, acțiuni, trades).
 *
 * Returnează:
 *   404 dacă jocul nu există
 *   409 dacă jocul nu este în starea 'active'
 *
 * (Cap. 5 — Reguli de business, Etapa 1)
 *
 * TODO: implementare completă după ce GameRepository este conectat la DB.
 */
class GameStateMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        // TODO: extrage gameId din atributele route-ului
        // TODO: GameRepository::findById($gameId)
        // TODO: dacă null → 404 NOT_FOUND
        // TODO: dacă status !== 'active' → 409 GAME_NOT_ACTIVE

        return $handler->handle($request);
    }
}

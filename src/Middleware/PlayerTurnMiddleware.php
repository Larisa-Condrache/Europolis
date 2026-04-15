<?php

declare(strict_types=1);

namespace Europolis\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * PlayerTurnMiddleware — verifică că jucătorul care acționează
 * este jucătorul curent al jocului.
 *
 * Returnează 403 NOT_YOUR_TURN dacă nu este tura lui.
 *
 * Regulă de business (Cap. 5):
 *   "Un jucător poate acționa doar în tura sa." → HTTP 403
 *
 * TODO: implementare completă după ce autentificarea este adăugată.
 */
class PlayerTurnMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        // TODO: extrage playerId din request (session token / header)
        // TODO: extrage gameId din atributele route-ului ($request->getAttribute('gameId'))
        // TODO: GameRepository::findById($gameId) → currentPlayerId
        // TODO: dacă playerId !== currentPlayerId → returnează 403
        //   $response->getBody()->write(json_encode([
        //       'error'   => 'NOT_YOUR_TURN',
        //       'message' => 'Jucătorul nu poate acționa în afara turei sale.',
        //   ]));
        //   return $response->withStatus(403);

        return $handler->handle($request);
    }
}

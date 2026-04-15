<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app): void {

    /**
     * POST /games/{gameId}/players
     * Adaugă un jucător la un joc existent (în starea waiting).
     * Body: { "name": "Mihai" }
     */
    $app->post('/games/{gameId}/players', function (Request $request, Response $response, array $args): Response {
        $gameId = (int) $args['gameId'];
        $body   = $request->getParsedBody();

        if (empty($body['name'])) {
            $response->getBody()->write(json_encode([
                'error'   => 'BAD_REQUEST',
                'message' => 'Câmpul name este obligatoriu.',
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // TODO: verifică că jocul există și este în starea "waiting"
        // TODO: verifică că nu s-a atins maxPlayers
        // Exemplu eroare dacă jocul e plin sau activ:
        // return $response->withStatus(409) cu error GAME_FULL sau GAME_ALREADY_STARTED

        // TODO: PlayerRepository::create($gameId, $body['name'])
        $player = [
            'id'       => 2,              // înlocuiește cu ID generat de DB
            'name'     => $body['name'],
            'balance'  => 1500,
            'position' => 0,
            'status'   => 'active',
        ];

        $response->getBody()->write(json_encode($player));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    });

    /**
     * GET /games/{gameId}/players
     * Returnează lista tuturor jucătorilor dintr-un joc.
     */
    $app->get('/games/{gameId}/players', function (Request $request, Response $response, array $args): Response {
        $gameId = (int) $args['gameId'];

        // TODO: PlayerRepository::findByGame($gameId)
        $players = [
            ['id' => 1, 'name' => 'Ana',   'balance' => 1500, 'position' => 0, 'status' => 'active'],
            ['id' => 2, 'name' => 'Mihai', 'balance' => 1500, 'position' => 0, 'status' => 'active'],
        ];

        $response->getBody()->write(json_encode($players));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    });

    /**
     * PATCH /players/{playerId}
     * Actualizează statusul unui jucător (ex: bankrupt).
     * Body: { "status": "bankrupt" }
     */
    $app->patch('/players/{playerId}', function (Request $request, Response $response, array $args): Response {
        $playerId = (int) $args['playerId'];
        $body     = $request->getParsedBody();

        $allowedStatuses = ['active', 'bankrupt'];

        if (empty($body['status']) || !in_array($body['status'], $allowedStatuses, true)) {
            $response->getBody()->write(json_encode([
                'error'   => 'BAD_REQUEST',
                'message' => 'Status invalid. Valorile permise: ' . implode(', ', $allowedStatuses),
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // TODO: verifică că jucătorul există → 404 dacă nu
        // TODO: PlayerRepository::updateStatus($playerId, $body['status'])
        // TODO: dacă bankrupt → redistribuire active conform regulilor

        $response->getBody()->write(json_encode([
            'message'  => 'Statusul jucătorului a fost actualizat.',
            'playerId' => $playerId,
            'status'   => $body['status'],
        ]));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    });
};

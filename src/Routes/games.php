<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app): void {

    /**
     * POST /games
     * Creează o sesiune nouă de joc.
     * Body: { "hostName": "Ana", "maxPlayers": 4 }
     */
    $app->post('/games', function (Request $request, Response $response): Response {
        $body = $request->getParsedBody();

        // Validare câmpuri obligatorii
        if (empty($body['hostName']) || empty($body['maxPlayers'])) {
            $response->getBody()->write(json_encode([
                'error'   => 'BAD_REQUEST',
                'message' => 'Câmpurile hostName și maxPlayers sunt obligatorii.',
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // TODO: salvare în baza de date prin GameService / GameRepository
        $game = [
            'id'         => 1,                  // înlocuiește cu ID generat de DB
            'status'     => 'waiting',
            'maxPlayers' => (int) $body['maxPlayers'],
            'players'    => [
                [
                    'id'       => 1,
                    'name'     => $body['hostName'],
                    'balance'  => 1500,
                    'position' => 0,
                    'status'   => 'active',
                ],
            ],
        ];

        $response->getBody()->write(json_encode($game));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    });

    /**
     * GET /games/{gameId}
     * Obține starea curentă a unui joc.
     */
    $app->get('/games/{gameId}', function (Request $request, Response $response, array $args): Response {
        $gameId = (int) $args['gameId'];

        // TODO: interogare GameRepository::findById($gameId)
        // Simulare răspuns — înlocuiește cu date reale din DB
        $game = [
            'id'             => $gameId,
            'status'         => 'active',
            'currentPlayerId'=> 2,
            'players'        => [
                ['id' => 1, 'name' => 'Ana',   'balance' => 1380, 'position' => 7,  'status' => 'active'],
                ['id' => 2, 'name' => 'Mihai', 'balance' => 1500, 'position' => 0,  'status' => 'active'],
            ],
        ];

        if ($game === null) {
            $response->getBody()->write(json_encode([
                'error'   => 'NOT_FOUND',
                'message' => 'Jocul cu id-ul ' . $gameId . ' nu a fost găsit.',
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($game));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    });

    /**
     * PATCH /games/{gameId}
     * Modifică starea jocului (ex: pornire joc cu status: active).
     * Body: { "status": "active" }
     */
    $app->patch('/games/{gameId}', function (Request $request, Response $response, array $args): Response {
        $gameId = (int) $args['gameId'];
        $body   = $request->getParsedBody();

        $allowedStatuses = ['active', 'finished'];

        if (empty($body['status']) || !in_array($body['status'], $allowedStatuses, true)) {
            $response->getBody()->write(json_encode([
                'error'   => 'BAD_REQUEST',
                'message' => 'Status invalid. Valorile permise: ' . implode(', ', $allowedStatuses),
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // TODO: GameRepository::updateStatus($gameId, $body['status'])

        $response->getBody()->write(json_encode([
            'message' => 'Starea jocului a fost actualizată.',
            'gameId'  => $gameId,
            'status'  => $body['status'],
        ]));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    });
};

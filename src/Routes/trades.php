<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app): void {

    /**
     * POST /games/{gameId}/trades
     * Creează o propunere de schimb între doi jucători.
     * Body: {
     *   "fromPlayerId": 1,
     *   "toPlayerId": 2,
     *   "offeredMoney": 200,
     *   "offeredPropertyIds": [],
     *   "requestedPropertyIds": [14]
     * }
     */
    $app->post('/games/{gameId}/trades', function (Request $request, Response $response, array $args): Response {
        $gameId = (int) $args['gameId'];
        $body   = $request->getParsedBody();

        if (empty($body['fromPlayerId']) || empty($body['toPlayerId'])) {
            $response->getBody()->write(json_encode([
                'error'   => 'BAD_REQUEST',
                'message' => 'Câmpurile fromPlayerId și toPlayerId sunt obligatorii.',
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // TODO: verifică că fromPlayerId deține proprietățile oferite → 403 NOT_YOUR_PROPERTY
        // TODO: verifică că toPlayerId există în joc → 404 PLAYER_NOT_FOUND
        // TODO: TradeRepository::create($gameId, $body)

        $trade = [
            'tradeId' => 3,         // înlocuiește cu ID generat de DB
            'status'  => 'pending',
        ];

        $response->getBody()->write(json_encode($trade));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    });

    /**
     * PATCH /games/{gameId}/trades/{tradeId}
     * Acceptă sau respinge o propunere de schimb.
     * Body: { "status": "accepted" } sau { "status": "rejected" }
     */
    $app->patch('/games/{gameId}/trades/{tradeId}', function (Request $request, Response $response, array $args): Response {
        $gameId  = (int) $args['gameId'];
        $tradeId = (int) $args['tradeId'];
        $body    = $request->getParsedBody();

        $allowedStatuses = ['accepted', 'rejected'];

        if (empty($body['status']) || !in_array($body['status'], $allowedStatuses, true)) {
            $response->getBody()->write(json_encode([
                'error'   => 'BAD_REQUEST',
                'message' => 'Status invalid. Valorile permise: accepted, rejected.',
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // TODO: verifică că trade-ul există → 404 TRADE_NOT_FOUND
        // TODO: verifică că cel care răspunde este destinatarul → 403 FORBIDDEN
        // TODO: dacă accepted → TradeService::execute($tradeId) transferă proprietăți și bani
        // TODO: TradeRepository::updateStatus($tradeId, $body['status'])

        $response->getBody()->write(json_encode([
            'message' => 'Răspunsul la propunere a fost înregistrat.',
            'tradeId' => $tradeId,
            'status'  => $body['status'],
        ]));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    });
};

<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app): void {

    /**
     * POST /games/{gameId}/turns
     * Inițiază o tură nouă și aruncă zarurile.
     * Body: { "playerId": 2 }
     */
    $app->post('/games/{gameId}/turns', function (Request $request, Response $response, array $args): Response {
        $gameId = (int) $args['gameId'];
        $body   = $request->getParsedBody();

        if (empty($body['playerId'])) {
            $response->getBody()->write(json_encode([
                'error'   => 'BAD_REQUEST',
                'message' => 'Câmpul playerId este obligatoriu.',
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $playerId = (int) $body['playerId'];

        // TODO: verifică că este tura acestui jucător → 403 NOT_YOUR_TURN
        // TODO: verifică că zarurile nu au fost deja aruncate → 409 DICE_ALREADY_ROLLED
        // TODO: TurnService::rollDice($gameId, $playerId)

        // Simulare aruncare zaruri
        $dice         = [rand(1, 6), rand(1, 6)];
        $fromPosition = 7;                              // TODO: din DB, poziția curentă a jucătorului
        $toPosition   = ($fromPosition + array_sum($dice)) % 40;

        // TODO: BoardService::resolveTile($toPosition) pentru tipul căsuței
        $turn = [
            'turnId'           => 10,
            'playerId'         => $playerId,
            'dice'             => $dice,
            'fromPosition'     => $fromPosition,
            'toPosition'       => $toPosition,
            'landedTile'       => [
                'index' => $toPosition,
                'name'  => 'Brașov',           // TODO: din configurația tablei
                'type'  => 'property',
            ],
            'availableActions' => ['buy_property', 'end_turn'],
        ];

        $response->getBody()->write(json_encode($turn));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    });

    /**
     * GET /games/{gameId}/turns/{turnId}
     * Returnează detaliile unei ture.
     */
    $app->get('/games/{gameId}/turns/{turnId}', function (Request $request, Response $response, array $args): Response {
        $gameId = (int) $args['gameId'];
        $turnId = (int) $args['turnId'];

        // TODO: TurnRepository::findById($gameId, $turnId) → 404 dacă nu există

        $turn = [
            'id'               => $turnId,
            'playerId'         => 2,
            'dice'             => [3, 5],
            'fromPosition'     => 7,
            'toPosition'       => 15,
            'availableActions' => ['buy_property', 'end_turn'],
        ];

        $response->getBody()->write(json_encode($turn));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    });

    /**
     * POST /games/{gameId}/turns/{turnId}/actions
     * Execută o acțiune disponibilă în tura curentă.
     *
     * Acțiuni posibile:
     *   buy_property   – cumpără proprietatea pe care s-a oprit jucătorul
     *   pay_rent       – plătește chiria proprietarului
     *   pay_tax        – plătește taxa (căsuță de impozit)
     *   mortgage       – ipotechează o proprietate
     *   build_house    – construiește o casă
     *   build_hotel    – construiește un hotel
     *   pay_jail_fine  – plătește amenda pentru eliberare din închisoare
     *   draw_card      – trage o carte de șansă / comunitate
     *   end_turn       – încheie tura
     *
     * Body: { "actionType": "buy_property" }
     */
    $app->post('/games/{gameId}/turns/{turnId}/actions', function (Request $request, Response $response, array $args): Response {
        $gameId = (int) $args['gameId'];
        $turnId = (int) $args['turnId'];
        $body   = $request->getParsedBody();

        $validActions = [
            'buy_property', 'pay_rent', 'pay_tax',
            'mortgage', 'build_house', 'build_hotel',
            'pay_jail_fine', 'draw_card', 'end_turn',
        ];

        if (empty($body['actionType']) || !in_array($body['actionType'], $validActions, true)) {
            $response->getBody()->write(json_encode([
                'error'   => 'BAD_REQUEST',
                'message' => 'actionType invalid sau lipsă.',
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // TODO: verifică că acțiunea este în lista availableActions a turei → 403
        // TODO: ActionService::execute($gameId, $turnId, $body['actionType'])
        //   → 422 dacă regulă de business este încălcată (ex: nu deții grupul complet)

        // Răspuns simulat — înlocuiește cu starea reală după execuție
        $result = [
            'message'    => 'Acțiunea a fost executată cu succes.',
            'gameStatus' => 'active',
            'player'     => [
                'id'      => 2,
                'balance' => 1320,          // TODO: sold real după tranzacție
            ],
        ];

        $response->getBody()->write(json_encode($result));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    });
};

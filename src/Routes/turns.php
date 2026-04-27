<?php

declare(strict_types=1);

use Europolis\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app): void {

    $tiles = [
        0  => ['name' => 'START',                   'type' => 'start'],
        1  => ['name' => 'Iasi',                     'type' => 'property'],
        2  => ['name' => 'Casa Comunitara',          'type' => 'community_chest'],
        3  => ['name' => 'Craiova',                  'type' => 'property'],
        4  => ['name' => 'Taxa Venit',               'type' => 'tax'],
        5  => ['name' => 'Gara de Nord',             'type' => 'station'],
        6  => ['name' => 'Timisoara',                'type' => 'property'],
        7  => ['name' => 'Sansa',                    'type' => 'chance'],
        8  => ['name' => 'Oradea',                   'type' => 'property'],
        9  => ['name' => 'Galati',                   'type' => 'property'],
        10 => ['name' => 'Inchisoare / Vizita',      'type' => 'jail'],
        11 => ['name' => 'Constanta',                'type' => 'property'],
        12 => ['name' => 'Compania Electrica',       'type' => 'utility'],
        13 => ['name' => 'Ploiesti',                 'type' => 'property'],
        14 => ['name' => 'Bacau',                    'type' => 'property'],
        15 => ['name' => 'Gara Basarab',             'type' => 'station'],
        16 => ['name' => 'Brasov',                   'type' => 'property'],
        17 => ['name' => 'Casa Comunitara',          'type' => 'community_chest'],
        18 => ['name' => 'Sibiu',                    'type' => 'property'],
        19 => ['name' => 'Pitesti',                  'type' => 'property'],
        20 => ['name' => 'Parcare Gratuita',         'type' => 'free_parking'],
        21 => ['name' => 'Cluj-Napoca',              'type' => 'property'],
        22 => ['name' => 'Sansa',                    'type' => 'chance'],
        23 => ['name' => 'Suceava',                  'type' => 'property'],
        24 => ['name' => 'Arad',                     'type' => 'property'],
        25 => ['name' => 'Gara Obor',                'type' => 'station'],
        26 => ['name' => 'Deva',                     'type' => 'property'],
        27 => ['name' => 'Drobeta-Turnu Severin',    'type' => 'property'],
        28 => ['name' => 'Compania de Apa',          'type' => 'utility'],
        29 => ['name' => 'Focsani',                  'type' => 'property'],
        30 => ['name' => 'Mergi la Inchisoare',      'type' => 'go_to_jail'],
        31 => ['name' => 'Satu Mare',                'type' => 'property'],
        32 => ['name' => 'Buzau',                    'type' => 'property'],
        33 => ['name' => 'Casa Comunitara',          'type' => 'community_chest'],
        34 => ['name' => 'Alba Iulia',               'type' => 'property'],
        35 => ['name' => 'Gara Progresu',            'type' => 'station'],
        36 => ['name' => 'Sansa',                    'type' => 'chance'],
        37 => ['name' => 'Bucuresti',                'type' => 'property'],
        38 => ['name' => 'Taxa de Lux',              'type' => 'tax'],
        39 => ['name' => 'Palatul Parlamentului',    'type' => 'property'],
    ];

    $tilePrices = [
        1  => 60,  3  => 60,
        6  => 100, 8  => 100, 9  => 120,
        11 => 140, 13 => 140, 14 => 160,
        16 => 180, 18 => 180, 19 => 200,
        21 => 220, 23 => 220, 24 => 240,
        26 => 260, 27 => 260, 29 => 280,
        31 => 300, 32 => 300, 34 => 320,
        37 => 350, 39 => 400,
        5  => 200, 15 => 200, 25 => 200, 35 => 200,
        12 => 150, 28 => 150,
    ];

    $availableActionsFor = fn(string $tileType): array => match ($tileType) {
        'property', 'station', 'utility' => ['buy_property', 'pay_rent', 'end_turn'],
        'tax'                             => ['pay_tax', 'end_turn'],
        'go_to_jail'                      => ['end_turn'],
        'chance', 'community_chest'       => ['draw_card', 'end_turn'],
        default                           => ['end_turn'],
    };

    $app->post('/games/{gameId}/turns', function (
        Request $request,
        Response $response,
        array $args
    ) use ($tiles, $availableActionsFor): Response {

        $gameId  = (int) $args['gameId'];
        $rawBody = (string) $request->getBody();
        $body    = json_decode($rawBody, true) ?? [];

        if (empty($body['playerId'])) {
            $response->getBody()->write(json_encode([
                'error'   => 'BAD_REQUEST',
                'message' => 'Campul playerId este obligatoriu.',
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $playerId = (int) $body['playerId'];
        $pdo      = Database::getConnection();

        $stmt = $pdo->prepare('SELECT id FROM games WHERE id = :id');
        $stmt->execute(['id' => $gameId]);
        if (!$stmt->fetch()) {
            $response->getBody()->write(json_encode([
                'error'   => 'NOT_FOUND',
                'message' => 'Jocul nu a fost gasit.',
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $stmt = $pdo->prepare(
            'SELECT id, position FROM players WHERE id = :id AND game_id = :game_id'
        );
        $stmt->execute(['id' => $playerId, 'game_id' => $gameId]);
        $player = $stmt->fetch();

        if (!$player) {
            $response->getBody()->write(json_encode([
                'error'   => 'NOT_FOUND',
                'message' => 'Jucatorul nu exista in acest joc.',
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $dice1        = rand(1, 6);
        $dice2        = rand(1, 6);
        $fromPosition = (int) $player['position'];
        $toPosition   = ($fromPosition + $dice1 + $dice2) % 40;

        $stmt = $pdo->prepare('UPDATE players SET position = :pos WHERE id = :id');
        $stmt->execute(['pos' => $toPosition, 'id' => $playerId]);

        $stmt = $pdo->prepare(
            'INSERT INTO turns (game_id, player_id, dice1, dice2, from_position, to_position)
             VALUES (:game_id, :player_id, :dice1, :dice2, :from_pos, :to_pos)'
        );
        $stmt->execute([
            'game_id'   => $gameId,
            'player_id' => $playerId,
            'dice1'     => $dice1,
            'dice2'     => $dice2,
            'from_pos'  => $fromPosition,
            'to_pos'    => $toPosition,
        ]);
        $turnId = (int) $pdo->lastInsertId();

        $landedTile = $tiles[$toPosition] ?? ['name' => 'Caseta necunoscuta', 'type' => 'unknown'];

        $response->getBody()->write(json_encode([
            'turnId'           => $turnId,
            'gameId'           => $gameId,
            'playerId'         => $playerId,
            'dice'             => [$dice1, $dice2],
            'fromPosition'     => $fromPosition,
            'toPosition'       => $toPosition,
            'landedTile'       => array_merge(['index' => $toPosition], $landedTile),
            'availableActions' => $availableActionsFor($landedTile['type']),
        ], JSON_UNESCAPED_UNICODE));

        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    });

    $app->get('/games/{gameId}/turns/{turnId}', function (
        Request $request,
        Response $response,
        array $args
    ) use ($tiles, $availableActionsFor): Response {

        $gameId = (int) $args['gameId'];
        $turnId = (int) $args['turnId'];
        $pdo    = Database::getConnection();

        $stmt = $pdo->prepare(
            'SELECT * FROM turns WHERE id = :id AND game_id = :game_id'
        );
        $stmt->execute(['id' => $turnId, 'game_id' => $gameId]);
        $turn = $stmt->fetch();

        if (!$turn) {
            $response->getBody()->write(json_encode([
                'error'   => 'NOT_FOUND',
                'message' => 'Tura nu a fost gasita.',
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $toPos      = (int) $turn['to_position'];
        $landedTile = $tiles[$toPos] ?? ['name' => 'Caseta necunoscuta', 'type' => 'unknown'];

        $response->getBody()->write(json_encode([
            'id'               => (int) $turn['id'],
            'gameId'           => (int) $turn['game_id'],
            'playerId'         => (int) $turn['player_id'],
            'dice'             => [(int) $turn['dice1'], (int) $turn['dice2']],
            'fromPosition'     => (int) $turn['from_position'],
            'toPosition'       => $toPos,
            'landedTile'       => array_merge(['index' => $toPos], $landedTile),
            'availableActions' => $availableActionsFor($landedTile['type']),
        ], JSON_UNESCAPED_UNICODE));

        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    });

    $app->post('/games/{gameId}/turns/{turnId}/actions', function (
        Request $request,
        Response $response,
        array $args
    ) use ($tilePrices): Response {

        $gameId  = (int) $args['gameId'];
        $turnId  = (int) $args['turnId'];
        $rawBody = (string) $request->getBody();
        $body    = json_decode($rawBody, true) ?? [];

        $validActions = [
            'buy_property', 'pay_rent',   'pay_tax',
            'mortgage',     'build_house', 'build_hotel',
            'pay_jail_fine', 'draw_card',  'end_turn',
        ];

        if (empty($body['actionType']) || !in_array($body['actionType'], $validActions, true)) {
            $response->getBody()->write(json_encode([
                'error'   => 'BAD_REQUEST',
                'message' => 'actionType invalid sau lipsa.',
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM turns WHERE id = :id AND game_id = :game_id');
        $stmt->execute(['id' => $turnId, 'game_id' => $gameId]);
        $turn = $stmt->fetch();

        if (!$turn) {
            $response->getBody()->write(json_encode([
                'error'   => 'NOT_FOUND',
                'message' => 'Tura nu a fost gasita.',
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $stmt = $pdo->prepare('SELECT id, balance FROM players WHERE id = :id');
        $stmt->execute(['id' => (int) $turn['player_id']]);
        $player = $stmt->fetch();

        $actionType = $body['actionType'];
        $tileIndex  = (int) $turn['to_position'];
        $newBalance = (int) $player['balance'];
        $details    = ['tileIndex' => $tileIndex];

        switch ($actionType) {
            case 'buy_property':
                $price      = $tilePrices[$tileIndex] ?? 100;
                $newBalance -= $price;
                $details     = ['tileIndex' => $tileIndex, 'price' => $price];
                break;

            case 'pay_rent':
                $rent       = (int) round(($tilePrices[$tileIndex] ?? 100) * 0.1);
                $newBalance -= $rent;
                $details     = ['tileIndex' => $tileIndex, 'rent' => $rent];
                break;

            case 'pay_tax':
                $tax        = ($tileIndex === 38) ? 100 : 200;
                $newBalance -= $tax;
                $details     = ['tileIndex' => $tileIndex, 'tax' => $tax];
                break;

            case 'pay_jail_fine':
                $newBalance -= 50;
                $details     = ['fine' => 50];
                break;

            case 'build_house':
                $newBalance -= 100;
                $details     = ['tileIndex' => $tileIndex, 'cost' => 100];
                break;

            case 'build_hotel':
                $newBalance -= 200;
                $details     = ['tileIndex' => $tileIndex, 'cost' => 200];
                break;
        }

        if ($newBalance !== (int) $player['balance']) {
            $stmt = $pdo->prepare('UPDATE players SET balance = :balance WHERE id = :id');
            $stmt->execute(['balance' => $newBalance, 'id' => (int) $player['id']]);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO actions (turn_id, game_id, action_type, details)
             VALUES (:turn_id, :game_id, :action_type, :details)'
        );
        $stmt->execute([
            'turn_id'     => $turnId,
            'game_id'     => $gameId,
            'action_type' => $actionType,
            'details'     => json_encode($details, JSON_UNESCAPED_UNICODE),
        ]);

        $response->getBody()->write(json_encode([
            'message'    => 'Actiunea a fost executata cu succes.',
            'gameId'     => $gameId,
            'turnId'     => $turnId,
            'actionType' => $actionType,
            'gameStatus' => 'active',
            'player'     => [
                'id'      => (int) $player['id'],
                'balance' => $newBalance,
            ],
        ], JSON_UNESCAPED_UNICODE));

        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    });
};

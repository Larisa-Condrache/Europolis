<?php

declare(strict_types=1);

use Europolis\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app): void {

<<<<<<< HEAD
    $json = function (Response $response, int $status, mixed $data): Response {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    };

    $formatTurn = function (array $turn): array {
        return [
            'turnId' => (int)$turn['id'],
            'gameId' => (int)$turn['game_id'],
            'playerId' => (int)$turn['player_id'],
            'playerName' => $turn['player_name'] ?? null,
            'dice' => [(int)$turn['dice1'], (int)$turn['dice2']],
            'fromPosition' => (int)$turn['from_position'],
            'toPosition' => (int)$turn['to_position'],
            'landedTile' => europolis_get_tile((int)$turn['to_position']),
            'createdAt' => $turn['created_at'],
        ];
    };

    $app->get('/games/{id}/turns', function (Request $request, Response $response, array $args) use ($json, $formatTurn) {
        $pdo = $this->get(PDO::class);
        $gameId = (int)$args['id'];
        $query = $request->getQueryParams();

        $sql = "
            SELECT t.*, p.name AS player_name
            FROM turns t
            JOIN players p ON p.id = t.player_id
            WHERE t.game_id = ?
        ";
        $params = [$gameId];

        if (!empty($query['playerId'])) {
            $sql .= " AND t.player_id = ?";
            $params[] = (int)$query['playerId'];
        }

        $sql .= " ORDER BY t.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $json($response, 200, array_map($formatTurn, $stmt->fetchAll()));
    });

    $app->get('/games/{id}/turns/{turnId}', function (Request $request, Response $response, array $args) use ($json, $formatTurn) {
        $pdo = $this->get(PDO::class);

        $stmt = $pdo->prepare("
            SELECT t.*, p.name AS player_name
            FROM turns t
            JOIN players p ON p.id = t.player_id
            WHERE t.game_id = ? AND t.id = ?
        ");
        $stmt->execute([(int)$args['id'], (int)$args['turnId']]);
        $turn = $stmt->fetch();

        if (!$turn) {
            return $json($response, 404, ['error' => 'turn not found']);
        }

        return $json($response, 200, $formatTurn($turn));
    });

    $app->get('/games/{id}/turns/{turnId}/actions', function (Request $request, Response $response, array $args) use ($json) {
        $pdo = $this->get(PDO::class);

        $stmt = $pdo->prepare("
            SELECT id, turn_id, game_id, action_type, details, created_at
            FROM actions
            WHERE game_id = ? AND turn_id = ?
            ORDER BY id
        ");
        $stmt->execute([(int)$args['id'], (int)$args['turnId']]);

        $actions = array_map(function (array $action): array {
            return [
                'id' => (int)$action['id'],
                'turnId' => (int)$action['turn_id'],
                'gameId' => (int)$action['game_id'],
                'actionType' => $action['action_type'],
                'details' => $action['details'] ? json_decode($action['details'], true) : null,
                'createdAt' => $action['created_at'],
            ];
        }, $stmt->fetchAll());

        return $json($response, 200, $actions);
    });

    $app->post('/games/{id}/turns', function (Request $request, Response $response, array $args) use ($json) {
        $pdo = $this->get(PDO::class);

        $gameId = (int)$args['id'];
        $body = $request->getParsedBody() ?: [];
        $playerId = (int)($body['playerId'] ?? 0);

        if (!$playerId) {
            return $json($response, 400, ['error' => 'Missing playerId']);
        }

        $stmt = $pdo->prepare("SELECT status, current_player_id FROM games WHERE id = ?");
        $stmt->execute([$gameId]);
        $game = $stmt->fetch();

        if (!$game) {
            return $json($response, 404, ['error' => 'game not found']);
        }

        if ($game['status'] !== 'active') {
            return $json($response, 409, ['error' => 'game not active']);
        }

        if ((int)$game['current_player_id'] !== $playerId) {
            return $json($response, 403, ['error' => 'not your turn']);
        }

        $stmt = $pdo->prepare("SELECT position FROM players WHERE id = ? AND game_id = ?");
        $stmt->execute([$playerId, $gameId]);
        $from = $stmt->fetchColumn();

        if ($from === false) {
            return $json($response, 404, ['error' => 'player not found']);
        }

        $dice1 = random_int(1, 6);
        $dice2 = random_int(1, 6);
        $to = ((int)$from + $dice1 + $dice2) % 40;

        $pdo->prepare("UPDATE players SET position = ? WHERE id = ?")
            ->execute([$to, $playerId]);

        $stmt = $pdo->prepare("
            INSERT INTO turns (game_id, player_id, dice1, dice2, from_position, to_position)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$gameId, $playerId, $dice1, $dice2, (int)$from, $to]);
        $turnId = (int)$pdo->lastInsertId();

        $tile = europolis_get_tile($to);
        $availableActions = [];

        if (in_array($tile['type'], ['property', 'station', 'utility'], true)) {
            $stmt = $pdo->prepare("
                SELECT player_id
                FROM owned_properties
                WHERE game_id = ? AND property_tile_index = ?
            ");
            $stmt->execute([$gameId, $to]);
            $owner = $stmt->fetchColumn();

            if (!$owner) {
                $availableActions[] = 'buy_property';
            } elseif ((int)$owner !== $playerId) {
                $availableActions[] = 'pay_rent';
            }
        } elseif ($tile['type'] === 'tax') {
            $availableActions[] = 'pay_tax';
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM players
            WHERE game_id = ? AND id != ? AND status = 'active'
        ");
        $stmt->execute([$gameId, $playerId]);
        if ((int)$stmt->fetchColumn() > 0) {
            $availableActions[] = 'trade';
        }

        $availableActions[] = 'end_turn';

        return $json($response, 201, [
            'turnId' => $turnId,
            'playerId' => $playerId,
            'dice' => [$dice1, $dice2],
            'fromPosition' => (int)$from,
            'toPosition' => $to,
            'landedTile' => $tile,
            'availableActions' => $availableActions,
        ]);
    });

    $app->post('/games/{id}/turns/{turnId}/actions', function (Request $request, Response $response, array $args) use ($json) {
        $pdo = $this->get(PDO::class);

        $gameId = (int)$args['id'];
        $turnId = (int)$args['turnId'];
        $body = $request->getParsedBody() ?: [];
        $action = (string)($body['actionType'] ?? '');

        if ($action === '') {
            return $json($response, 400, ['error' => 'Missing actionType']);
        }

        $stmt = $pdo->prepare("SELECT current_player_id FROM games WHERE id = ?");
        $stmt->execute([$gameId]);
        $playerId = (int)$stmt->fetchColumn();

        if (!$playerId) {
            return $json($response, 404, ['error' => 'game not found']);
        }

        $stmt = $pdo->prepare("SELECT position, balance FROM players WHERE id = ? AND game_id = ?");
        $stmt->execute([$playerId, $gameId]);
        $player = $stmt->fetch();

        if (!$player) {
            return $json($response, 404, ['error' => 'current player not found']);
        }

        $position = (int)$player['position'];
        $tile = europolis_get_tile($position);
        $details = ['playerId' => $playerId, 'position' => $position, 'tile' => $tile];

        if ($action === 'buy_property') {
            if (!in_array($tile['type'], ['property', 'station', 'utility'], true)) {
                return $json($response, 422, ['error' => 'tile is not buyable']);
            }

            $stmt = $pdo->prepare("
                SELECT id
                FROM owned_properties
                WHERE game_id = ? AND property_tile_index = ?
                LIMIT 1
            ");
            $stmt->execute([$gameId, $position]);

            if ($stmt->fetch()) {
                return $json($response, 409, ['error' => 'property already owned']);
            }

            $price = (int)($tile['price'] ?? 0);
            if ((int)$player['balance'] < $price) {
                return $json($response, 422, ['error' => 'insufficient funds']);
            }

            $pdo->prepare("UPDATE players SET balance = balance - ? WHERE id = ?")
                ->execute([$price, $playerId]);

            $pdo->prepare("
                INSERT INTO owned_properties (game_id, player_id, property_tile_index, purchase_price)
                VALUES (?, ?, ?, ?)
            ")->execute([$gameId, $playerId, $position, $price]);

            $details['price'] = $price;
        }

        if ($action === 'pay_rent') {
            $stmt = $pdo->prepare("
                SELECT player_id
                FROM owned_properties
                WHERE game_id = ? AND property_tile_index = ?
            ");
            $stmt->execute([$gameId, $position]);
            $ownerId = (int)$stmt->fetchColumn();

            if (!$ownerId || $ownerId === $playerId) {
                return $json($response, 422, ['error' => 'rent not required']);
            }

            $rent = max(10, (int)(($tile['price'] ?? 100) * 0.1));
            $pdo->prepare("UPDATE players SET balance = balance - ? WHERE id = ?")
                ->execute([$rent, $playerId]);
            $pdo->prepare("UPDATE players SET balance = balance + ? WHERE id = ?")
                ->execute([$rent, $ownerId]);

            $details['rent'] = $rent;
            $details['ownerId'] = $ownerId;
        }

        if ($action === 'pay_tax') {
            $tax = (int)($tile['price'] ?? 100);
            $pdo->prepare("UPDATE players SET balance = balance - ? WHERE id = ?")
                ->execute([$tax, $playerId]);
            $details['tax'] = $tax;
        }

        if ($action === 'end_turn') {
            $stmt = $pdo->prepare("
                SELECT id
                FROM players
                WHERE game_id = ? AND status = 'active'
                ORDER BY id ASC
            ");
            $stmt->execute([$gameId]);
            $players = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($players) > 0) {
                $currentIndex = array_search($playerId, $players, false);
                $nextIndex = (($currentIndex === false ? 0 : $currentIndex) + 1) % count($players);
                $nextPlayer = (int)$players[$nextIndex];
                $pdo->prepare("UPDATE games SET current_player_id = ? WHERE id = ?")
                    ->execute([$nextPlayer, $gameId]);
                $details['nextPlayerId'] = $nextPlayer;
            }
        }

        $pdo->prepare("
            INSERT INTO actions (turn_id, game_id, action_type, details)
            VALUES (?, ?, ?, ?)
        ")->execute([$turnId, $gameId, $action, json_encode($details, JSON_UNESCAPED_UNICODE)]);

        return $json($response, 200, [
            'ok' => true,
            'action' => $action,
            'details' => $details,
        ]);
=======
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
>>>>>>> a4acf109e35e07ea400bf924f7d67041ddafb0be
    });
};

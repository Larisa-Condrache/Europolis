<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app): void {

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
    });
};

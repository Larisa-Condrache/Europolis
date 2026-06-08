<?php

declare(strict_types=1);

use Europolis\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app): void {

    $json = function (Response $response, int $code, mixed $data): Response {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response->withStatus($code)->withHeader('Content-Type', 'application/json');
    };

    $app->post('/games/{gameId}/players', function (Request $request, Response $response, array $args) use ($json): Response {
        $gameId = (int)$args['gameId'];
        $body = json_decode((string)$request->getBody(), true) ?? [];

        if (empty($body['name'])) {
            return $json($response, 400, ['error' => 'name obligatoriu']);
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('SELECT * FROM games WHERE id = :id');
        $stmt->execute(['id' => $gameId]);
        $game = $stmt->fetch();

        if (!$game) {
            return $json($response, 404, ['error' => 'game not found']);
        }

        if ($game['status'] !== 'waiting') {
            return $json($response, 409, ['error' => 'game not accepting players']);
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM players WHERE game_id = :gameId');
        $stmt->execute(['gameId' => $gameId]);
        $count = (int)$stmt->fetchColumn();

        if ($count >= (int)$game['max_players']) {
            return $json($response, 409, ['error' => 'game full']);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO players (game_id, name, balance, position, status, in_jail, jail_turns)
             VALUES (:gameId, :name, 1500, 0, "active", 0, 0)'
        );
        $stmt->execute(['gameId' => $gameId, 'name' => $body['name']]);
        $id = (int)$pdo->lastInsertId();

        if ($count + 1 >= (int)$game['max_players']) {
            $pdo->prepare('UPDATE games SET status = "active" WHERE id = :gameId')
                ->execute(['gameId' => $gameId]);
        }

        return $json($response, 201, [
            'id' => $id,
            'gameId' => $gameId,
            'name' => $body['name'],
            'balance' => 1500,
            'position' => 0,
            'status' => 'active',
        ]);
    });

    $app->get('/games/{gameId}/players', function (Request $request, Response $response, array $args) use ($json): Response {
        $gameId = (int)$args['gameId'];
        $viewerPlayerId = (int)(($request->getQueryParams()['viewerPlayerId'] ?? 0));
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('SELECT id FROM games WHERE id = :id');
        $stmt->execute(['id' => $gameId]);

        if (!$stmt->fetch()) {
            return $json($response, 404, ['error' => 'game not found']);
        }

        $stmt = $pdo->prepare(
            'SELECT
                p.id,
                p.name,
                p.balance,
                p.position,
                p.status,
                GROUP_CONCAT(op.property_tile_index ORDER BY op.property_tile_index) AS properties
             FROM players p
             LEFT JOIN owned_properties op ON op.player_id = p.id AND op.game_id = p.game_id
             WHERE p.game_id = :gameId
             GROUP BY p.id, p.name, p.balance, p.position, p.status
             ORDER BY p.id'
        );
        $stmt->execute(['gameId' => $gameId]);

        $players = array_map(fn($player) => [
            'id' => (int)$player['id'],
            'name' => $player['name'],
            'balance' => ((int)$player['id'] === $viewerPlayerId) ? (int)$player['balance'] : null,
            'balanceHidden' => ((int)$player['id'] !== $viewerPlayerId),
            'position' => (int)$player['position'],
            'status' => $player['status'],
            'properties' => $player['properties']
                ? array_map('intval', explode(',', $player['properties']))
                : [],
        ], $stmt->fetchAll());

        return $json($response, 200, $players);
    });

    $app->delete('/games/{gameId}/players/{playerId}', function (Request $request, Response $response, array $args) use ($json): Response {
        $gameId = (int)$args['gameId'];
        $playerId = (int)$args['playerId'];
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('SELECT current_player_id FROM games WHERE id = :gameId');
        $stmt->execute(['gameId' => $gameId]);
        $game = $stmt->fetch();

        if (!$game) {
            return $json($response, 404, ['error' => 'game not found']);
        }

        $stmt = $pdo->prepare('SELECT id, name FROM players WHERE id = :playerId AND game_id = :gameId');
        $stmt->execute(['playerId' => $playerId, 'gameId' => $gameId]);
        $player = $stmt->fetch();

        if (!$player) {
            return $json($response, 404, ['error' => 'player not found']);
        }

        $pdo->beginTransaction();
        try {
            if ((int)$game['current_player_id'] === $playerId) {
                $pdo->prepare('UPDATE games SET current_player_id = NULL WHERE id = :gameId')
                    ->execute(['gameId' => $gameId]);
            }

            $pdo->prepare(
                'DELETE tp
                 FROM trade_properties tp
                 JOIN trades t ON t.id = tp.trade_id
                 WHERE t.game_id = :gameId
                   AND (t.from_player_id = :playerId OR t.to_player_id = :playerId)'
            )->execute(['gameId' => $gameId, 'playerId' => $playerId]);

            $pdo->prepare(
                'DELETE a
                 FROM actions a
                 JOIN turns t ON t.id = a.turn_id
                 WHERE t.game_id = :gameId AND t.player_id = :playerId'
            )->execute(['gameId' => $gameId, 'playerId' => $playerId]);

            $pdo->prepare(
                'DELETE FROM trades
                 WHERE game_id = :gameId
                   AND (from_player_id = :playerId OR to_player_id = :playerId)'
            )->execute(['gameId' => $gameId, 'playerId' => $playerId]);

            foreach (['chat_messages', 'owned_properties', 'turns'] as $table) {
                $pdo->prepare("DELETE FROM {$table} WHERE game_id = :gameId AND player_id = :playerId")
                    ->execute(['gameId' => $gameId, 'playerId' => $playerId]);
            }

            $pdo->prepare('DELETE FROM players WHERE id = :playerId AND game_id = :gameId')
                ->execute(['playerId' => $playerId, 'gameId' => $gameId]);

            $stmt = $pdo->prepare(
                'SELECT id
                 FROM players
                 WHERE game_id = :gameId AND status = "active"
                 ORDER BY id
                 LIMIT 1'
            );
            $stmt->execute(['gameId' => $gameId]);
            $nextPlayer = $stmt->fetchColumn();

            $pdo->prepare('UPDATE games SET current_player_id = :nextPlayer WHERE id = :gameId')
                ->execute([
                    'nextPlayer' => $nextPlayer ? (int)$nextPlayer : null,
                    'gameId' => $gameId,
                ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return $json($response, 500, [
                'error' => 'DELETE_PLAYER_FAILED',
                'message' => $e->getMessage(),
            ]);
        }

        return $json($response, 200, [
            'message' => 'Jucator sters',
            'playerId' => $playerId,
            'name' => $player['name'],
        ]);
    });

    $app->get('/players/{playerId}', function (Request $request, Response $response, array $args) use ($json): Response {
        $playerId = (int)$args['playerId'];
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            'SELECT p.*, g.status AS game_status
             FROM players p
             JOIN games g ON g.id = p.game_id
             WHERE p.id = :id'
        );
        $stmt->execute(['id' => $playerId]);
        $player = $stmt->fetch();

        if (!$player) {
            return $json($response, 404, ['error' => 'player not found']);
        }

        $stmt = $pdo->prepare(
            'SELECT property_tile_index, purchase_price, created_at
             FROM owned_properties
             WHERE player_id = :id
             ORDER BY property_tile_index'
        );
        $stmt->execute(['id' => $playerId]);

        $properties = array_map(function ($row) {
            $tile = europolis_get_tile((int)$row['property_tile_index']);
            return [
                'tileIndex' => (int)$row['property_tile_index'],
                'name' => $tile['name'],
                'type' => $tile['type'],
                'price' => (int)$row['purchase_price'],
                'createdAt' => $row['created_at'],
            ];
        }, $stmt->fetchAll());

        return $json($response, 200, [
            'id' => (int)$player['id'],
            'gameId' => (int)$player['game_id'],
            'gameStatus' => $player['game_status'],
            'name' => $player['name'],
            'balance' => (int)$player['balance'],
            'position' => (int)$player['position'],
            'status' => $player['status'],
            'properties' => $properties,
            'createdAt' => $player['created_at'],
        ]);
    });

    $app->get('/games/{gameId}/players/{playerId}', function (Request $request, Response $response, array $args) use ($json): Response {
        $gameId = (int)$args['gameId'];
        $playerId = (int)$args['playerId'];
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            'SELECT p.*, g.status AS game_status
             FROM players p
             JOIN games g ON g.id = p.game_id
             WHERE p.id = :playerId AND p.game_id = :gameId'
        );
        $stmt->execute(['playerId' => $playerId, 'gameId' => $gameId]);
        $player = $stmt->fetch();

        if (!$player) {
            return $json($response, 404, ['error' => 'player not found']);
        }

        $stmt = $pdo->prepare(
            'SELECT property_tile_index, purchase_price, created_at
             FROM owned_properties
             WHERE player_id = :id
             ORDER BY property_tile_index'
        );
        $stmt->execute(['id' => $playerId]);

        $properties = array_map(function ($row) {
            $tile = europolis_get_tile((int)$row['property_tile_index']);
            return [
                'tileIndex' => (int)$row['property_tile_index'],
                'name' => $tile['name'],
                'type' => $tile['type'],
                'price' => (int)$row['purchase_price'],
                'createdAt' => $row['created_at'],
            ];
        }, $stmt->fetchAll());

        return $json($response, 200, [
            'id' => (int)$player['id'],
            'gameId' => (int)$player['game_id'],
            'gameStatus' => $player['game_status'],
            'name' => $player['name'],
            'balance' => (int)$player['balance'],
            'position' => (int)$player['position'],
            'status' => $player['status'],
            'properties' => $properties,
            'createdAt' => $player['created_at'],
        ]);
    });

    $app->patch('/players/{playerId}', function (Request $request, Response $response, array $args) use ($json): Response {
        $playerId = (int)$args['playerId'];
        $body = json_decode((string)$request->getBody(), true) ?? [];
        $allowed = ['active', 'bankrupt'];

        if (isset($body['status']) && !in_array((string)$body['status'], $allowed, true)) {
            return $json($response, 400, ['error' => 'invalid status']);
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('SELECT id, game_id, status FROM players WHERE id = :id');
        $stmt->execute(['id' => $playerId]);
        $player = $stmt->fetch();

        if (!$player) {
            return $json($response, 404, ['error' => 'player not found']);
        }

        $gameId = (int)$player['game_id'];

        $fields = [];
        $params = ['id' => $playerId];

        if (array_key_exists('balance', $body)) {
            $fields[] = 'balance = :balance';
            $params['balance'] = (int)$body['balance'];
        }
        if (array_key_exists('money', $body)) {
            $fields[] = 'balance = :money';
            $params['money'] = (int)$body['money'];
        }
        if (array_key_exists('position', $body)) {
            $fields[] = 'position = :position';
            $params['position'] = max(0, min(39, (int)$body['position']));
        }
        if (array_key_exists('status', $body)) {
            if ((string)$body['status'] === 'bankrupt') {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM owned_properties WHERE game_id = :gameId AND player_id = :playerId');
                $stmt->execute(['gameId' => $gameId, 'playerId' => $playerId]);
                $assets = (int)$stmt->fetchColumn();

                if ($assets > 0) {
                    return $json($response, 409, [
                        'error' => 'bankruptcy_blocked_by_assets',
                        'message' => 'Jucatorul nu poate fi declarat bankrupt cat timp mai are proprietati care pot fi vandute.',
                        'propertiesAvailableToSell' => $assets,
                    ]);
                }
            }

            $fields[] = 'status = :status';
            $params['status'] = (string)$body['status'];
        }

        if (!$fields) {
            return $json($response, 400, ['error' => 'no supported fields']);
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE players SET ' . implode(', ', $fields) . ' WHERE id = :id')
                ->execute($params);

            $winner = null;
            if (($body['status'] ?? null) === 'bankrupt') {
                $pdo->prepare('UPDATE players SET balance = 0 WHERE id = :id')
                    ->execute(['id' => $playerId]);

                $stmt = $pdo->prepare('SELECT id, name FROM players WHERE game_id = :gameId AND status = "active" ORDER BY id ASC');
                $stmt->execute(['gameId' => $gameId]);
                $activePlayers = $stmt->fetchAll();

                if (count($activePlayers) === 1) {
                    $winner = $activePlayers[0];
                    $pdo->prepare('UPDATE games SET status = "finished", current_player_id = NULL WHERE id = :gameId')
                        ->execute(['gameId' => $gameId]);
                } elseif (count($activePlayers) > 1) {
                    $stmt = $pdo->prepare('SELECT current_player_id FROM games WHERE id = :gameId');
                    $stmt->execute(['gameId' => $gameId]);
                    if ((int)$stmt->fetchColumn() === $playerId) {
                        $pdo->prepare('UPDATE games SET current_player_id = :nextPlayer WHERE id = :gameId')
                            ->execute(['nextPlayer' => (int)$activePlayers[0]['id'], 'gameId' => $gameId]);
                    }
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return $json($response, 500, ['error' => 'PLAYER_UPDATE_FAILED', 'message' => $e->getMessage()]);
        }

        return $json($response, 200, [
            'playerId' => $playerId,
            'status' => $body['status'] ?? $player['status'],
            'gameId' => $gameId,
            'gameFinished' => $winner !== null,
            'winnerPlayerId' => $winner ? (int)$winner['id'] : null,
            'winner' => $winner ? ['id' => (int)$winner['id'], 'name' => $winner['name']] : null,
        ]);
    });
};

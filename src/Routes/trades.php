<?php

declare(strict_types=1);

use Europolis\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app): void {

    $json = function (Response $response, int $status, mixed $data): Response {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    };

    $formatTrade = function (array $row): array {
        return [
            'id' => (int)$row['id'],
            'gameId' => (int)$row['game_id'],
            'fromPlayerId' => (int)$row['from_player_id'],
            'fromPlayerName' => $row['from_player_name'] ?? null,
            'toPlayerId' => (int)$row['to_player_id'],
            'toPlayerName' => $row['to_player_name'] ?? null,
            'offeredMoney' => (int)$row['offered_money'],
            'status' => $row['status'],
            'offeredProps' => $row['offered_props'] ? array_map('intval', explode(',', $row['offered_props'])) : [],
            'requestedProps' => $row['requested_props'] ? array_map('intval', explode(',', $row['requested_props'])) : [],
            'createdAt' => $row['created_at'] ?? null,
        ];
    };

    $app->get('/games/{gameId}/trades', function (Request $request, Response $response, array $args) use ($json, $formatTrade): Response {
        $gameId = (int)$args['gameId'];
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            'SELECT t.*,
                fp.name AS from_player_name,
                tp.name AS to_player_name,
                GROUP_CONCAT(CASE WHEN trp.direction = "offered" THEN trp.property_tile_index END) AS offered_props,
                GROUP_CONCAT(CASE WHEN trp.direction = "requested" THEN trp.property_tile_index END) AS requested_props
             FROM trades t
             JOIN players fp ON fp.id = t.from_player_id
             JOIN players tp ON tp.id = t.to_player_id
             LEFT JOIN trade_properties trp ON trp.trade_id = t.id
             WHERE t.game_id = :gameId
             GROUP BY t.id, fp.name, tp.name
             ORDER BY t.id DESC'
        );
        $stmt->execute(['gameId' => $gameId]);

        return $json($response, 200, array_map($formatTrade, $stmt->fetchAll()));
    });

    $app->get('/games/{gameId}/trades/{tradeId}', function (Request $request, Response $response, array $args) use ($json, $formatTrade): Response {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT t.*,
                fp.name AS from_player_name,
                tp.name AS to_player_name,
                GROUP_CONCAT(CASE WHEN trp.direction = "offered" THEN trp.property_tile_index END) AS offered_props,
                GROUP_CONCAT(CASE WHEN trp.direction = "requested" THEN trp.property_tile_index END) AS requested_props
             FROM trades t
             JOIN players fp ON fp.id = t.from_player_id
             JOIN players tp ON tp.id = t.to_player_id
             LEFT JOIN trade_properties trp ON trp.trade_id = t.id
             WHERE t.game_id = :gameId AND t.id = :tradeId
             GROUP BY t.id, fp.name, tp.name'
        );
        $stmt->execute(['gameId' => (int)$args['gameId'], 'tradeId' => (int)$args['tradeId']]);
        $trade = $stmt->fetch();

        if (!$trade) {
            return $json($response, 404, ['error' => 'trade not found']);
        }

        return $json($response, 200, $formatTrade($trade));
    });

    $app->post('/games/{gameId}/trades', function (Request $request, Response $response, array $args) use ($json): Response {
        $gameId = (int)$args['gameId'];
        $body = json_decode((string)$request->getBody(), true) ?? [];

        if (empty($body['fromPlayerId']) || empty($body['toPlayerId'])) {
            return $json($response, 400, [
                'error' => 'BAD_REQUEST',
                'message' => 'fromPlayerId si toPlayerId sunt obligatorii',
            ]);
        }

        $pdo = Database::getConnection();
        $from = (int)$body['fromPlayerId'];
        $to = (int)$body['toPlayerId'];
        $money = (int)($body['offeredMoney'] ?? 0);

        $checkPlayer = $pdo->prepare('SELECT id FROM players WHERE id = :id AND game_id = :gameId');
        foreach ([$from, $to] as $playerId) {
            $checkPlayer->execute(['id' => $playerId, 'gameId' => $gameId]);
            if (!$checkPlayer->fetch()) {
                return $json($response, 400, ['error' => 'INVALID_PLAYER']);
            }
        }

        $stmt = $pdo->prepare('SELECT balance FROM players WHERE id = :id');
        $stmt->execute(['id' => $from]);
        if ((int)$stmt->fetchColumn() < $money) {
            return $json($response, 400, ['error' => 'INSUFFICIENT_FUNDS']);
        }

        $offered = $body['offeredPropertyIds'] ?? [];
        $requested = $body['requestedPropertyIds'] ?? [];

        $checkOwner = function (int $playerId, array $tiles) use ($pdo, $gameId): bool {
            foreach ($tiles as $tile) {
                $stmt = $pdo->prepare(
                    'SELECT player_id
                     FROM owned_properties
                     WHERE game_id = :gameId AND property_tile_index = :tile'
                );
                $stmt->execute(['gameId' => $gameId, 'tile' => (int)$tile]);
                $prop = $stmt->fetch();
                if (!$prop || (int)$prop['player_id'] !== $playerId) {
                    return false;
                }
            }
            return true;
        };

        if (!$checkOwner($from, $offered) || !$checkOwner($to, $requested)) {
            return $json($response, 400, ['error' => 'INVALID_TRADE']);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO trades (game_id, from_player_id, to_player_id, offered_money, status)
             VALUES (:gameId, :fromPlayer, :toPlayer, :money, "pending")'
        );
        $stmt->execute([
            'gameId' => $gameId,
            'fromPlayer' => $from,
            'toPlayer' => $to,
            'money' => $money,
        ]);
        $tradeId = (int)$pdo->lastInsertId();

        $insertProperty = $pdo->prepare(
            'INSERT INTO trade_properties (trade_id, property_tile_index, direction)
             VALUES (:tradeId, :tile, :direction)'
        );

        foreach ($offered as $tile) {
            $insertProperty->execute(['tradeId' => $tradeId, 'tile' => (int)$tile, 'direction' => 'offered']);
        }
        foreach ($requested as $tile) {
            $insertProperty->execute(['tradeId' => $tradeId, 'tile' => (int)$tile, 'direction' => 'requested']);
        }

        return $json($response, 201, ['tradeId' => $tradeId, 'status' => 'pending']);
    });

    $app->patch('/games/{gameId}/trades/{tradeId}', function (Request $request, Response $response, array $args) use ($json): Response {
        $gameId = (int)$args['gameId'];
        $tradeId = (int)$args['tradeId'];
        $body = json_decode((string)$request->getBody(), true) ?? [];
        $status = $body['status'] ?? null;

        if (!in_array($status, ['accepted', 'rejected'], true)) {
            return $json($response, 400, ['error' => 'BAD_REQUEST', 'message' => 'status invalid']);
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM trades WHERE id = :id AND game_id = :gameId AND status = "pending"');
        $stmt->execute(['id' => $tradeId, 'gameId' => $gameId]);
        $trade = $stmt->fetch();

        if (!$trade) {
            return $json($response, 404, ['error' => 'NOT_FOUND']);
        }

        if ($status === 'rejected') {
            $pdo->prepare('UPDATE trades SET status = "rejected" WHERE id = :id')
                ->execute(['id' => $tradeId]);
            return $json($response, 200, ['message' => 'Trade respins']);
        }

        $stmt = $pdo->prepare('SELECT * FROM trade_properties WHERE trade_id = :id');
        $stmt->execute(['id' => $tradeId]);

        foreach ($stmt->fetchAll() as $property) {
            $newOwner = $property['direction'] === 'offered'
                ? (int)$trade['to_player_id']
                : (int)$trade['from_player_id'];

            $pdo->prepare(
                'UPDATE owned_properties
                 SET player_id = :playerId
                 WHERE game_id = :gameId AND property_tile_index = :tile'
            )->execute([
                'playerId' => $newOwner,
                'gameId' => $gameId,
                'tile' => (int)$property['property_tile_index'],
            ]);
        }

        if ((int)$trade['offered_money'] > 0) {
            $money = (int)$trade['offered_money'];
            $pdo->prepare('UPDATE players SET balance = balance - :money WHERE id = :id')
                ->execute(['money' => $money, 'id' => (int)$trade['from_player_id']]);
            $pdo->prepare('UPDATE players SET balance = balance + :money WHERE id = :id')
                ->execute(['money' => $money, 'id' => (int)$trade['to_player_id']]);
        }

        $pdo->prepare('UPDATE trades SET status = "accepted" WHERE id = :id')
            ->execute(['id' => $tradeId]);

        return $json($response, 200, ['message' => 'Trade acceptat']);
    });
};

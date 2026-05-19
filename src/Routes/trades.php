<?php

declare(strict_types=1);

use Europolis\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app): void {

<<<<<<< HEAD
    // =========================================================
    // HELPER
    // =========================================================
    $json = function (Response $response, int $status, $data): Response {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    };

    // =========================================================
    // GET TRADES
    // =========================================================
    $app->get('/games/{gameId}/trades', function (
        Request $request,
        Response $response,
        array $args
    ) use ($json): Response {

        $gameId = (int)$args['gameId'];
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT t.*,
                GROUP_CONCAT(CASE WHEN tp.direction='offered'   THEN tp.property_tile_index END) as offered_props,
                GROUP_CONCAT(CASE WHEN tp.direction='requested' THEN tp.property_tile_index END) as requested_props
            FROM trades t
            LEFT JOIN trade_properties tp ON tp.trade_id = t.id
            WHERE t.game_id = :g
            GROUP BY t.id
            ORDER BY t.id DESC
        ");

        $stmt->execute(['g' => $gameId]);
        $rows = $stmt->fetchAll();

        $trades = array_map(function($row) {
            return [
                'id'             => (int)$row['id'],
                'fromPlayerId'   => (int)$row['from_player_id'],
                'toPlayerId'     => (int)$row['to_player_id'],
                'offeredMoney'   => (int)$row['offered_money'],
                'status'         => $row['status'],
                'offeredProps'   => $row['offered_props']   ? array_map('intval', explode(',', $row['offered_props']))   : [],
                'requestedProps' => $row['requested_props'] ? array_map('intval', explode(',', $row['requested_props'])) : [],
            ];
        }, $rows);

        return $json($response, 200, $trades);
    });

    // =========================================================
    // GET TRADE DETAILS
    // =========================================================
    $app->get('/games/{gameId}/trades/{tradeId}', function (
        Request $request,
        Response $response,
        array $args
    ) use ($json): Response {

        $gameId = (int)$args['gameId'];
        $tradeId = (int)$args['tradeId'];
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT t.*,
                fp.name AS from_player_name,
                tp.name AS to_player_name,
                GROUP_CONCAT(CASE WHEN trp.direction='offered' THEN trp.property_tile_index END) AS offered_props,
                GROUP_CONCAT(CASE WHEN trp.direction='requested' THEN trp.property_tile_index END) AS requested_props
            FROM trades t
            JOIN players fp ON fp.id = t.from_player_id
            JOIN players tp ON tp.id = t.to_player_id
            LEFT JOIN trade_properties trp ON trp.trade_id = t.id
            WHERE t.game_id = :gameId AND t.id = :tradeId
            GROUP BY t.id, fp.name, tp.name
        ");
        $stmt->execute(['gameId' => $gameId, 'tradeId' => $tradeId]);
        $trade = $stmt->fetch();

        if (!$trade) {
            return $json($response, 404, ['error' => 'trade not found']);
        }

        return $json($response, 200, [
            'id' => (int)$trade['id'],
            'gameId' => (int)$trade['game_id'],
            'fromPlayerId' => (int)$trade['from_player_id'],
            'fromPlayerName' => $trade['from_player_name'],
            'toPlayerId' => (int)$trade['to_player_id'],
            'toPlayerName' => $trade['to_player_name'],
            'offeredMoney' => (int)$trade['offered_money'],
            'status' => $trade['status'],
            'offeredProps' => $trade['offered_props'] ? array_map('intval', explode(',', $trade['offered_props'])) : [],
            'requestedProps' => $trade['requested_props'] ? array_map('intval', explode(',', $trade['requested_props'])) : [],
            'createdAt' => $trade['created_at'],
        ]);
    });

    // =========================================================
    // CREATE TRADE
    // =========================================================
    $app->post('/games/{gameId}/trades', function (
        Request $request,
        Response $response,
        array $args
    ) use ($json): Response {

        $gameId = (int)$args['gameId'];
        $body = json_decode((string)$request->getBody(), true) ?? [];

        if (empty($body['fromPlayerId']) || empty($body['toPlayerId'])) {
            return $json($response, 400, [
                'error' => 'BAD_REQUEST',
                'message' => 'fromPlayerId si toPlayerId sunt obligatorii'
            ]);
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT id FROM games WHERE id=:id");
        $stmt->execute(['id' => $gameId]);

        if (!$stmt->fetch()) {
            return $json($response, 404, [
                'error' => 'NOT_FOUND',
                'message' => 'Joc inexistent'
            ]);
        }

        $from  = (int)$body['fromPlayerId'];
        $to    = (int)$body['toPlayerId'];
        $money = (int)($body['offeredMoney'] ?? 0);

        $checkPlayer = $pdo->prepare("
            SELECT id FROM players WHERE id=:id AND game_id=:g
        ");

        $checkPlayer->execute(['id' => $from, 'g' => $gameId]);
        if (!$checkPlayer->fetch()) {
            return $json($response, 400, [
                'error' => 'INVALID_PLAYER',
                'message' => 'fromPlayer nu aparține jocului'
            ]);
        }

        $checkPlayer->execute(['id' => $to, 'g' => $gameId]);
        if (!$checkPlayer->fetch()) {
            return $json($response, 400, [
                'error' => 'INVALID_PLAYER',
                'message' => 'toPlayer nu aparține jocului'
            ]);
        }

        $stmt = $pdo->prepare("SELECT balance FROM players WHERE id=:id");
        $stmt->execute(['id' => $from]);
        $balance = (int)$stmt->fetchColumn();

        if ($balance < $money) {
            return $json($response, 400, ['error' => 'INSUFFICIENT_FUNDS']);
        }

        $offered   = $body['offeredPropertyIds'] ?? [];
        $requested = $body['requestedPropertyIds'] ?? [];

        $checkOwner = function ($playerId, $tiles) use ($pdo, $gameId): bool {
            foreach ($tiles as $tile) {
                $stmt = $pdo->prepare("
                    SELECT player_id FROM owned_properties
                    WHERE game_id=:g AND property_tile_index=:t
                ");
                $stmt->execute(['g' => $gameId, 't' => (int)$tile]);
                $prop = $stmt->fetch();
                if (!$prop || (int)$prop['player_id'] !== $playerId) {
                    return false;
                }
            }
            return true;
        };

        if (!$checkOwner($from, $offered) || !$checkOwner($to, $requested)) {
            return $json($response, 400, [
                'error' => 'INVALID_TRADE',
                'message' => 'Proprietati invalide'
            ]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO trades (game_id, from_player_id, to_player_id, offered_money, status)
            VALUES (:g,:f,:t,:m,'pending')
        ");
        $stmt->execute(['g' => $gameId, 'f' => $from, 't' => $to, 'm' => $money]);

        $tradeId = (int)$pdo->lastInsertId();

        $ins = $pdo->prepare("
            INSERT INTO trade_properties (trade_id, property_tile_index, direction)
            VALUES (:tid,:tile,:dir)
        ");

        foreach ($offered as $t) {
            $ins->execute(['tid' => $tradeId, 'tile' => (int)$t, 'dir' => 'offered']);
        }
        foreach ($requested as $t) {
            $ins->execute(['tid' => $tradeId, 'tile' => (int)$t, 'dir' => 'requested']);
        }

        return $json($response, 201, ['tradeId' => $tradeId, 'status' => 'pending']);
    });

    // =========================================================
    // ACCEPT / REJECT TRADE
    // =========================================================
    $app->patch('/games/{gameId}/trades/{tradeId}', function (
        Request $request,
        Response $response,
        array $args
    ) use ($json): Response {
=======
    $app->post('/games/{gameId}/trades', function (Request $request, Response $response, array $args): Response {
        $gameId  = (int) $args['gameId'];
        $rawBody = (string) $request->getBody();
        $body    = json_decode($rawBody, true) ?? [];

        if (empty($body['fromPlayerId']) || empty($body['toPlayerId'])) {
            $response->getBody()->write(json_encode([
                'error'   => 'BAD_REQUEST',
                'message' => 'Campurile fromPlayerId si toPlayerId sunt obligatorii.',
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $fromPlayerId = (int) $body['fromPlayerId'];
        $toPlayerId   = (int) $body['toPlayerId'];
        $offeredMoney = (int) ($body['offeredMoney'] ?? 0);

        $pdo  = Database::getConnection();
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
            'INSERT INTO trades (game_id, from_player_id, to_player_id, offered_money, status)
             VALUES (:game_id, :from_pid, :to_pid, :money, "pending")'
        );
        $stmt->execute([
            'game_id'  => $gameId,
            'from_pid' => $fromPlayerId,
            'to_pid'   => $toPlayerId,
            'money'    => $offeredMoney,
        ]);
        $tradeId = (int) $pdo->lastInsertId();

        $offeredPropertyIds   = $body['offeredPropertyIds']   ?? [];
        $requestedPropertyIds = $body['requestedPropertyIds'] ?? [];

        if (!empty($offeredPropertyIds) || !empty($requestedPropertyIds)) {
            $stmtProp = $pdo->prepare(
                'INSERT INTO trade_properties (trade_id, property_tile_index, direction)
                 VALUES (:trade_id, :tile_index, :direction)'
            );

            foreach ($offeredPropertyIds as $tileIndex) {
                $stmtProp->execute([
                    'trade_id'   => $tradeId,
                    'tile_index' => (int) $tileIndex,
                    'direction'  => 'offered',
                ]);
            }

            foreach ($requestedPropertyIds as $tileIndex) {
                $stmtProp->execute([
                    'trade_id'   => $tradeId,
                    'tile_index' => (int) $tileIndex,
                    'direction'  => 'requested',
                ]);
            }
        }

        $response->getBody()->write(json_encode([
            'tradeId'              => $tradeId,
            'gameId'               => $gameId,
            'fromPlayerId'         => $fromPlayerId,
            'toPlayerId'           => $toPlayerId,
            'offeredMoney'         => $offeredMoney,
            'offeredPropertyIds'   => array_values(array_map('intval', $offeredPropertyIds)),
            'requestedPropertyIds' => array_values(array_map('intval', $requestedPropertyIds)),
            'status'               => 'pending',
        ], JSON_UNESCAPED_UNICODE));

        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    });

    $app->patch('/games/{gameId}/trades/{tradeId}', function (Request $request, Response $response, array $args): Response {
        $gameId  = (int) $args['gameId'];
        $tradeId = (int) $args['tradeId'];
        $rawBody = (string) $request->getBody();
        $body    = json_decode($rawBody, true) ?? [];
>>>>>>> a4acf109e35e07ea400bf924f7d67041ddafb0be

        $gameId  = (int)$args['gameId'];
        $tradeId = (int)$args['tradeId'];
        $body = json_decode((string)$request->getBody(), true) ?? [];

<<<<<<< HEAD
        $status = $body['status'] ?? null;

        if (!in_array($status, ['accepted', 'rejected'], true)) {
            return $json($response, 400, [
                'error' => 'BAD_REQUEST',
                'message' => 'status invalid'
            ]);
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT * FROM trades
            WHERE id=:id AND game_id=:g AND status='pending'
        ");
        $stmt->execute(['id' => $tradeId, 'g' => $gameId]);
        $trade = $stmt->fetch();

        if (!$trade) {
            return $json($response, 404, [
                'error' => 'NOT_FOUND',
                'message' => 'Trade inexistent sau procesat'
            ]);
        }

        // REJECT
        if ($status === 'rejected') {
            $pdo->prepare("UPDATE trades SET status='rejected' WHERE id=:id")
                ->execute(['id' => $tradeId]);
            return $json($response, 200, ['message' => 'Trade respins']);
        }

        // ACCEPT — transferă proprietăți
        $stmt = $pdo->prepare("SELECT * FROM trade_properties WHERE trade_id=:id");
        $stmt->execute(['id' => $tradeId]);
        $props = $stmt->fetchAll();

        foreach ($props as $p) {
            $tile      = (int)$p['property_tile_index'];
            $newOwner  = $p['direction'] === 'offered'
                ? $trade['to_player_id']
                : $trade['from_player_id'];

            $pdo->prepare("
                UPDATE owned_properties
                SET player_id=:p
                WHERE game_id=:g AND property_tile_index=:t
            ")->execute(['p' => $newOwner, 'g' => $gameId, 't' => $tile]);
        }

        // transferă bani
        if ($trade['offered_money'] > 0) {
            $stmt = $pdo->prepare("SELECT balance FROM players WHERE id=:id");
            $stmt->execute(['id' => $trade['from_player_id']]);
            $balance = (int)$stmt->fetchColumn();

            if ($balance < $trade['offered_money']) {
                return $json($response, 400, ['error' => 'INSUFFICIENT_FUNDS']);
            }

            $pdo->prepare("UPDATE players SET balance = balance + :m WHERE id=:id")
                ->execute(['m' => $trade['offered_money'], 'id' => $trade['to_player_id']]);

            $pdo->prepare("UPDATE players SET balance = balance - :m WHERE id=:id")
                ->execute(['m' => $trade['offered_money'], 'id' => $trade['from_player_id']]);
        }

        $pdo->prepare("UPDATE trades SET status='accepted' WHERE id=:id")
            ->execute(['id' => $tradeId]);

        return $json($response, 200, ['message' => 'Trade acceptat']);
=======
        if (empty($body['status']) || !in_array($body['status'], $allowedStatuses, true)) {
            $response->getBody()->write(json_encode([
                'error'   => 'BAD_REQUEST',
                'message' => 'Status invalid. Valorile permise: accepted, rejected.',
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id FROM trades WHERE id = :id AND game_id = :game_id');
        $stmt->execute(['id' => $tradeId, 'game_id' => $gameId]);

        if (!$stmt->fetch()) {
            $response->getBody()->write(json_encode([
                'error'   => 'NOT_FOUND',
                'message' => 'Propunerea de schimb nu a fost gasita.',
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $stmt = $pdo->prepare('UPDATE trades SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $body['status'], 'id' => $tradeId]);

        $response->getBody()->write(json_encode([
            'message' => 'Raspunsul la propunere a fost inregistrat.',
            'gameId'  => $gameId,
            'tradeId' => $tradeId,
            'status'  => $body['status'],
        ], JSON_UNESCAPED_UNICODE));

        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
>>>>>>> a4acf109e35e07ea400bf924f7d67041ddafb0be
    });
};

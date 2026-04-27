<?php

declare(strict_types=1);

use Europolis\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app): void {

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

        $allowedStatuses = ['accepted', 'rejected'];

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
    });
};

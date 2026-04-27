<?php

declare(strict_types=1);

use Europolis\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app): void {

    $app->post('/games/{gameId}/players', function (Request $request, Response $response, array $args): Response {
        $gameId  = (int) $args['gameId'];
        $rawBody = (string) $request->getBody();
        $body    = json_decode($rawBody, true) ?? [];

        if (empty($body['name'])) {
            $response->getBody()->write(json_encode([
                'error'   => 'BAD_REQUEST',
                'message' => 'Campul name este obligatoriu.',
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, status, max_players FROM games WHERE id = :id');
        $stmt->execute(['id' => $gameId]);
        $game = $stmt->fetch();

        if (!$game) {
            $response->getBody()->write(json_encode([
                'error'   => 'NOT_FOUND',
                'message' => 'Jocul nu a fost gasit.',
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        if ($game['status'] !== 'waiting') {
            $response->getBody()->write(json_encode([
                'error'   => 'CONFLICT',
                'message' => 'Jocul nu mai accepta jucatori noi (status: ' . $game['status'] . ').',
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM players WHERE game_id = :game_id');
        $stmt->execute(['game_id' => $gameId]);
        $count = (int) $stmt->fetchColumn();

        if ($count >= (int) $game['max_players']) {
            $response->getBody()->write(json_encode([
                'error'   => 'CONFLICT',
                'message' => 'Jocul este plin (maxPlayers: ' . $game['max_players'] . ').',
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO players (game_id, name, balance, position, status)
             VALUES (:game_id, :name, 1500, 0, "active")'
        );
        $stmt->execute(['game_id' => $gameId, 'name' => $body['name']]);
        $playerId = (int) $pdo->lastInsertId();

        $response->getBody()->write(json_encode([
            'id'       => $playerId,
            'gameId'   => $gameId,
            'name'     => $body['name'],
            'balance'  => 1500,
            'position' => 0,
            'status'   => 'active',
        ], JSON_UNESCAPED_UNICODE));

        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    });

    $app->get('/games/{gameId}/players', function (Request $request, Response $response, array $args): Response {
        $gameId = (int) $args['gameId'];
        $pdo    = Database::getConnection();

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
            'SELECT id, name, balance, position, status
             FROM players WHERE game_id = :game_id ORDER BY id'
        );
        $stmt->execute(['game_id' => $gameId]);
        $rows = $stmt->fetchAll();

        $players = array_map(fn($p) => [
            'id'       => (int) $p['id'],
            'name'     => $p['name'],
            'balance'  => (int) $p['balance'],
            'position' => (int) $p['position'],
            'status'   => $p['status'],
        ], $rows);

        $response->getBody()->write(json_encode($players, JSON_UNESCAPED_UNICODE));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    });

    $app->patch('/players/{playerId}', function (Request $request, Response $response, array $args): Response {
        $playerId = (int) $args['playerId'];
        $rawBody  = (string) $request->getBody();
        $body     = json_decode($rawBody, true) ?? [];

        $allowedStatuses = ['active', 'bankrupt'];

        if (empty($body['status']) || !in_array($body['status'], $allowedStatuses, true)) {
            $response->getBody()->write(json_encode([
                'error'   => 'BAD_REQUEST',
                'message' => 'Status invalid. Valorile permise: active, bankrupt.',
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id FROM players WHERE id = :id');
        $stmt->execute(['id' => $playerId]);

        if (!$stmt->fetch()) {
            $response->getBody()->write(json_encode([
                'error'   => 'NOT_FOUND',
                'message' => 'Jucatorul nu a fost gasit.',
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $stmt = $pdo->prepare('UPDATE players SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $body['status'], 'id' => $playerId]);

        $response->getBody()->write(json_encode([
            'message'  => 'Statusul jucatorului a fost actualizat.',
            'playerId' => $playerId,
            'status'   => $body['status'],
        ], JSON_UNESCAPED_UNICODE));

        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    });
};

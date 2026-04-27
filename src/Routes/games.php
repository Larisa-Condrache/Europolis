<?php

declare(strict_types=1);

use Europolis\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app): void {

    $app->post('/games', function (Request $request, Response $response): Response {
        $rawBody = (string) $request->getBody();
        $body    = json_decode($rawBody, true) ?? [];

        if (empty($body['hostName']) || empty($body['maxPlayers'])) {
            $response->getBody()->write(json_encode([
                'error'   => 'BAD_REQUEST',
                'message' => 'Campurile hostName si maxPlayers sunt obligatorii.',
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $maxPlayers = (int) $body['maxPlayers'];

        if ($maxPlayers < 2 || $maxPlayers > 6) {
            $response->getBody()->write(json_encode([
                'error'   => 'BAD_REQUEST',
                'message' => 'Numarul de jucatori trebuie sa fie intre 2 si 6.',
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            'INSERT INTO games (status, max_players) VALUES (:status, :max_players)'
        );
        $stmt->execute(['status' => 'waiting', 'max_players' => $maxPlayers]);
        $gameId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO players (game_id, name, balance, position, status)
             VALUES (:game_id, :name, 1500, 0, "active")'
        );
        $stmt->execute(['game_id' => $gameId, 'name' => $body['hostName']]);
        $hostId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare('UPDATE games SET current_player_id = :pid WHERE id = :id');
        $stmt->execute(['pid' => $hostId, 'id' => $gameId]);

        $response->getBody()->write(json_encode([
            'id'         => $gameId,
            'status'     => 'waiting',
            'maxPlayers' => $maxPlayers,
            'players'    => [
                [
                    'id'       => $hostId,
                    'name'     => $body['hostName'],
                    'balance'  => 1500,
                    'position' => 0,
                    'status'   => 'active',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE));

        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    });

    $app->get('/games/{gameId}', function (Request $request, Response $response, array $args): Response {
        $gameId = (int) $args['gameId'];
        $pdo    = Database::getConnection();

        $stmt = $pdo->prepare('SELECT * FROM games WHERE id = :id');
        $stmt->execute(['id' => $gameId]);
        $game = $stmt->fetch();

        if (!$game) {
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

        $response->getBody()->write(json_encode([
            'id'              => (int) $game['id'],
            'status'          => $game['status'],
            'maxPlayers'      => (int) $game['max_players'],
            'currentPlayerId' => $game['current_player_id'] !== null
                                    ? (int) $game['current_player_id']
                                    : null,
            'players'         => $players,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    });

    $app->patch('/games/{gameId}', function (Request $request, Response $response, array $args): Response {
        $gameId  = (int) $args['gameId'];
        $rawBody = (string) $request->getBody();
        $body    = json_decode($rawBody, true) ?? [];

        $allowedStatuses = ['active', 'finished'];

        if (empty($body['status']) || !in_array($body['status'], $allowedStatuses, true)) {
            $response->getBody()->write(json_encode([
                'error'   => 'BAD_REQUEST',
                'message' => 'Status invalid. Valorile permise: active, finished.',
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

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

        $stmt = $pdo->prepare('UPDATE games SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $body['status'], 'id' => $gameId]);

        $response->getBody()->write(json_encode([
            'message' => 'Starea jocului a fost actualizata.',
            'gameId'  => $gameId,
            'status'  => $body['status'],
        ], JSON_UNESCAPED_UNICODE));

        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    });
};

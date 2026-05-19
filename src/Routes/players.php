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

    // =========================================================
    // ADD PLAYER
    // =========================================================
    $app->post('/games/{gameId}/players', function (
        Request $request,
        Response $response,
        array $args
    ) use ($json): Response {

        $gameId = (int) $args['gameId'];
        $body = json_decode((string)$request->getBody(), true) ?? [];

        if (empty($body['name'])) {
            return $json($response, 400, ['error' => 'name obligatoriu']);
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM games WHERE id=:id");
        $stmt->execute(['id' => $gameId]);
        $game = $stmt->fetch();

        if (!$game) {
            return $json($response, 404, ['error' => 'game not found']);
        }

        if ($game['status'] !== 'waiting') {
            return $json($response, 409, ['error' => 'game not accepting players']);
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM players WHERE game_id=:g");
        $stmt->execute(['g' => $gameId]);
        $count = (int)$stmt->fetchColumn();

        if ($count >= (int)$game['max_players']) {
            return $json($response, 409, ['error' => 'game full']);
        }

        $stmt = $pdo->prepare("
            INSERT INTO players (game_id, name, balance, position, status)
            VALUES (:g,:n,1500,0,'active')
        ");

        $stmt->execute([
            'g' => $gameId,
            'n' => $body['name']
        ]);

        $id = (int)$pdo->lastInsertId();

        // AUTO START GAME
        if ($count + 1 >= (int)$game['max_players']) {
            $pdo->prepare("UPDATE games SET status='active', current_player_id=:id WHERE id=:g")
                ->execute(['id' => $id, 'g' => $gameId]);
        }

        return $json($response, 201, [
            'id' => $id,
            'gameId' => $gameId,
            'name' => $body['name'],
            'balance' => 1500,
            'position' => 0,
            'status' => 'active'
        ]);
    });

    // =========================================================
    // GET PLAYERS (FIX JOIN LOGIC)
    // =========================================================
    $app->get('/games/{gameId}/players', function (
        Request $request,
        Response $response,
        array $args
    ) use ($json): Response {

        $gameId = (int)$args['gameId'];
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT id FROM games WHERE id=:id");
        $stmt->execute(['id' => $gameId]);

        if (!$stmt->fetch()) {
            return $json($response, 404, ['error' => 'game not found']);
        }

        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.name,
                p.balance,
                p.position,
                p.status,
                GROUP_CONCAT(op.property_tile_index ORDER BY op.property_tile_index) AS properties
            FROM players p
            LEFT JOIN owned_properties op
                ON op.player_id = p.id AND op.game_id = p.game_id
            WHERE p.game_id = :g
            GROUP BY p.id, p.name, p.balance, p.position, p.status
        ");

        $stmt->execute(['g' => $gameId]);
        $rows = $stmt->fetchAll();

        $players = array_map(fn($p) => [
            'id' => (int)$p['id'],
            'name' => $p['name'],
            'balance' => (int)$p['balance'],
            'position' => (int)$p['position'],
            'status' => $p['status'],
            'properties' => $p['properties']
                ? array_map('intval', explode(',', $p['properties']))
                : []
        ], $rows);

        return $json($response, 200, $players);
    });

    // =========================================================
    // DELETE PLAYER
    // =========================================================
    $app->delete('/games/{gameId}/players/{playerId}', function (
        Request $request,
        Response $response,
        array $args
    ) use ($json): Response {

        $gameId = (int)$args['gameId'];
        $playerId = (int)$args['playerId'];
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT current_player_id FROM games WHERE id = :gameId");
        $stmt->execute(['gameId' => $gameId]);
        $game = $stmt->fetch();

        if (!$game) {
            return $json($response, 404, ['error' => 'game not found']);
        }

        $stmt = $pdo->prepare("SELECT id, name FROM players WHERE id = :playerId AND game_id = :gameId");
        $stmt->execute(['playerId' => $playerId, 'gameId' => $gameId]);
        $player = $stmt->fetch();

        if (!$player) {
            return $json($response, 404, ['error' => 'player not found']);
        }

        $pdo->prepare("DELETE FROM players WHERE id = :playerId AND game_id = :gameId")
            ->execute(['playerId' => $playerId, 'gameId' => $gameId]);

        if ((int)$game['current_player_id'] === $playerId) {
            $stmt = $pdo->prepare("
                SELECT id
                FROM players
                WHERE game_id = :gameId AND status = 'active'
                ORDER BY id
                LIMIT 1
            ");
            $stmt->execute(['gameId' => $gameId]);
            $nextPlayer = $stmt->fetchColumn();

            $pdo->prepare("UPDATE games SET current_player_id = :nextPlayer WHERE id = :gameId")
                ->execute([
                    'nextPlayer' => $nextPlayer ? (int)$nextPlayer : null,
                    'gameId' => $gameId,
                ]);
        }

        return $json($response, 200, [
            'message' => 'Jucator sters',
            'playerId' => $playerId,
            'name' => $player['name'],
        ]);
    });

    // =========================================================
    // GET PLAYER DETAILS
    // =========================================================
    $app->get('/players/{playerId}', function (
        Request $request,
        Response $response,
        array $args
    ) use ($json): Response {

        $playerId = (int)$args['playerId'];
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT p.*, g.status AS game_status
            FROM players p
            JOIN games g ON g.id = p.game_id
            WHERE p.id = :id
        ");
        $stmt->execute(['id' => $playerId]);
        $player = $stmt->fetch();

        if (!$player) {
            return $json($response, 404, ['error' => 'player not found']);
        }

        $stmt = $pdo->prepare("
            SELECT property_tile_index, purchase_price, created_at
            FROM owned_properties
            WHERE player_id = :id
            ORDER BY property_tile_index
        ");
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

    // =========================================================
    // PATCH PLAYER STATUS
    // =========================================================
    $app->patch('/players/{playerId}', function (
        Request $request,
        Response $response,
        array $args
    ) use ($json): Response {

        $playerId = (int)$args['playerId'];
        $body = json_decode((string)$request->getBody(), true) ?? [];

        $allowed = ['active', 'bankrupt'];

        if (empty($body['status']) || !in_array($body['status'], $allowed, true)) {
            return $json($response, 400, ['error' => 'invalid status']);
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT id FROM players WHERE id=:id");
        $stmt->execute(['id' => $playerId]);

        if (!$stmt->fetch()) {
            return $json($response, 404, ['error' => 'player not found']);
        }

        $pdo->prepare("UPDATE players SET status=:s WHERE id=:id")
            ->execute(['s' => $body['status'], 'id' => $playerId]);

        return $json($response, 200, [
            'playerId' => $playerId,
            'status' => $body['status']
        ]);
    });
};

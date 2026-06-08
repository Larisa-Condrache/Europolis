<?php

declare(strict_types=1);

use Europolis\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app): void {

    $app->get('/games', function (Request $request, Response $response): Response {
        $pdo = Database::getConnection();

        $stmt = $pdo->query(
            'SELECT
                g.id,
                g.status,
                g.max_players,
                g.current_player_id,
                g.created_at,
                cp.name AS current_player_name,
                COUNT(p.id) AS player_count
             FROM games g
             LEFT JOIN players cp ON cp.id = g.current_player_id
             LEFT JOIN players p ON p.game_id = g.id
             GROUP BY g.id, g.status, g.max_players, g.current_player_id, g.created_at, cp.name
             ORDER BY FIELD(g.status, "active", "waiting", "finished"), g.id DESC'
        );

        $games = array_map(fn($game) => [
            'id' => (int)$game['id'],
            'status' => $game['status'],
            'maxPlayers' => (int)$game['max_players'],
            'playerCount' => (int)$game['player_count'],
            'currentPlayerId' => $game['current_player_id'] ? (int)$game['current_player_id'] : null,
            'currentPlayerName' => $game['current_player_name'],
            'createdAt' => $game['created_at'],
        ], $stmt->fetchAll());

        return write_json($response, 200, $games);
    });

    $app->post('/games', function (Request $request, Response $response): Response {
        $body = json_decode((string)$request->getBody(), true) ?? [];

        if (empty($body['hostName']) || empty($body['maxPlayers'])) {
            return write_json($response, 400, [
                'error' => 'BAD_REQUEST',
                'message' => 'hostName si maxPlayers sunt obligatorii',
            ]);
        }

        $maxPlayers = (int)$body['maxPlayers'];

        if ($maxPlayers < 2 || $maxPlayers > 6) {
            return write_json($response, 400, [
                'error' => 'BAD_REQUEST',
                'message' => 'maxPlayers trebuie intre 2 si 6',
            ]);
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            'INSERT INTO games (status, max_players, current_player_id)
             VALUES ("waiting", :max_players, NULL)'
        );
        $stmt->execute(['max_players' => $maxPlayers]);
        $gameId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO players (game_id, name, balance, position, status, in_jail, jail_turns)
             VALUES (:game_id, :name, 1500, 0, "active", 0, 0)'
        );
        $stmt->execute([
            'game_id' => $gameId,
            'name' => $body['hostName'],
        ]);
        $hostId = (int)$pdo->lastInsertId();

        $pdo->prepare('UPDATE games SET current_player_id = :playerId WHERE id = :gameId')
            ->execute(['playerId' => $hostId, 'gameId' => $gameId]);

        return write_json($response, 201, [
            'id' => $gameId,
            'status' => 'waiting',
            'maxPlayers' => $maxPlayers,
            'currentPlayerId' => $hostId,
            'myPlayerId' => $hostId,
            'players' => [[
                'id' => $hostId,
                'name' => $body['hostName'],
                'balance' => 1500,
                'position' => 0,
                'status' => 'active',
                'inJail' => false,
                'properties' => [],
            ]],
        ]);
    });

    $app->get('/games/{gameId}', function (Request $request, Response $response, array $args): Response {
        $gameId = (int)$args['gameId'];
        $viewerPlayerId = (int)(($request->getQueryParams()['viewerPlayerId'] ?? 0));
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('SELECT * FROM games WHERE id = :id');
        $stmt->execute(['id' => $gameId]);
        $game = $stmt->fetch();

        if (!$game) {
            return write_json($response, 404, [
                'error' => 'NOT_FOUND',
                'message' => 'Joc inexistent',
            ]);
        }

        if ($viewerPlayerId > 0) {
            $stmt = $pdo->prepare('SELECT id FROM players WHERE id = :id AND game_id = :gameId');
            $stmt->execute(['id' => $viewerPlayerId, 'gameId' => $gameId]);
            if (!$stmt->fetch()) {
                $viewerPlayerId = 0;
            }
        }

        $stmt = $pdo->prepare(
            'SELECT
                p.id,
                p.name,
                p.balance,
                p.position,
                p.status,
                COALESCE(p.in_jail, 0) AS in_jail,
                GROUP_CONCAT(op.property_tile_index ORDER BY op.property_tile_index) AS properties
             FROM players p
             LEFT JOIN owned_properties op ON op.player_id = p.id AND op.game_id = p.game_id
             WHERE p.game_id = :game_id
             GROUP BY p.id, p.name, p.balance, p.position, p.status, p.in_jail
             ORDER BY p.id'
        );
        $stmt->execute(['game_id' => $gameId]);

        $players = array_map(function ($player) use ($viewerPlayerId) {
            $id = (int)$player['id'];
            $isViewer = $viewerPlayerId > 0 && $id === $viewerPlayerId;

            return [
                'id' => $id,
                'name' => $player['name'],
                'balance' => $isViewer ? (int)$player['balance'] : null,
                'balanceHidden' => !$isViewer,
                'position' => (int)$player['position'],
                'status' => $player['status'],
                'inJail' => (int)$player['in_jail'] === 1,
                'properties' => $player['properties']
                    ? array_map('intval', explode(',', $player['properties']))
                    : [],
            ];
        }, $stmt->fetchAll());

        $winner = null;
        if ($game['status'] === 'finished') {
            foreach ($players as $candidate) {
                if ($candidate['status'] === 'active') {
                    $winner = [
                        'id' => (int)$candidate['id'],
                        'name' => $candidate['name'],
                    ];
                    break;
                }
            }
        }

        return write_json($response, 200, [
            'id' => (int)$game['id'],
            'status' => $game['status'],
            'maxPlayers' => (int)$game['max_players'],
            'currentPlayerId' => $game['current_player_id'] ? (int)$game['current_player_id'] : null,
            'viewerPlayerId' => $viewerPlayerId ?: null,
            'winnerPlayerId' => $winner ? (int)$winner['id'] : null,
            'winner' => $winner,
            'players' => $players,
        ]);
    });

    $app->patch('/games/{gameId}', function (Request $request, Response $response, array $args): Response {
        $gameId = (int)$args['gameId'];
        $body = json_decode((string)$request->getBody(), true) ?? [];
        $allowed = ['active', 'finished'];

        if (empty($body['status']) || !in_array($body['status'], $allowed, true)) {
            return write_json($response, 400, [
                'error' => 'BAD_REQUEST',
                'message' => 'status invalid',
            ]);
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('SELECT id FROM games WHERE id = :id');
        $stmt->execute(['id' => $gameId]);

        if (!$stmt->fetch()) {
            return write_json($response, 404, [
                'error' => 'NOT_FOUND',
                'message' => 'Joc inexistent',
            ]);
        }

        $pdo->prepare('UPDATE games SET status = :status WHERE id = :id')
            ->execute(['status' => $body['status'], 'id' => $gameId]);

        return write_json($response, 200, [
            'message' => 'Status actualizat',
            'gameId' => $gameId,
            'status' => $body['status'],
        ]);
    });

    $app->delete('/games/{gameId}', function (Request $request, Response $response, array $args): Response {
        $gameId = (int)$args['gameId'];
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('SELECT id FROM games WHERE id = :id');
        $stmt->execute(['id' => $gameId]);

        if (!$stmt->fetch()) {
            return write_json($response, 404, [
                'error' => 'NOT_FOUND',
                'message' => 'Joc inexistent',
            ]);
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE games SET current_player_id = NULL WHERE id = :id')
                ->execute(['id' => $gameId]);

            $pdo->prepare(
                'DELETE tp
                 FROM trade_properties tp
                 JOIN trades t ON t.id = tp.trade_id
                 WHERE t.game_id = :gameId'
            )->execute(['gameId' => $gameId]);

            $pdo->prepare(
                'DELETE a
                 FROM actions a
                 JOIN turns t ON t.id = a.turn_id
                 WHERE t.game_id = :gameId'
            )->execute(['gameId' => $gameId]);

            foreach ([
                'chat_messages',
                'trades',
                'owned_properties',
                'turns',
                'players',
                'games',
            ] as $table) {
                $pdo->prepare("DELETE FROM {$table} WHERE " . ($table === 'games' ? 'id' : 'game_id') . ' = :gameId')
                    ->execute(['gameId' => $gameId]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return write_json($response, 500, [
                'error' => 'DELETE_GAME_FAILED',
                'message' => $e->getMessage(),
            ]);
        }

        return write_json($response, 200, [
            'message' => 'Joc sters',
            'gameId' => $gameId,
        ]);
    });

    function write_json(Response $response, int $status, mixed $data): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
};

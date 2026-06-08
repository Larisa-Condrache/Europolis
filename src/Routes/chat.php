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

    $ensureChatTable = function (PDO $pdo): void {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS chat_messages (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                game_id INT UNSIGNED NOT NULL,
                player_id INT UNSIGNED NOT NULL,
                message VARCHAR(500) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_chat_game_created (game_id, created_at),
                CONSTRAINT fk_chat_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
                CONSTRAINT fk_chat_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    };

    $formatMessage = function (array $row): array {
        return [
            'id' => (int)$row['id'],
            'gameId' => (int)$row['game_id'],
            'playerId' => (int)$row['player_id'],
            'playerName' => $row['player_name'],
            'message' => $row['message'],
            'createdAt' => $row['created_at'],
        ];
    };

    $app->get('/games/{gameId}/chat', function (
        Request $request,
        Response $response,
        array $args
    ) use ($json, $ensureChatTable, $formatMessage): Response {
        $gameId = (int)$args['gameId'];
        $limit = min(80, max(1, (int)($request->getQueryParams()['limit'] ?? 40)));
        $pdo = Database::getConnection();
        $ensureChatTable($pdo);

        $stmt = $pdo->prepare('SELECT id FROM games WHERE id = ?');
        $stmt->execute([$gameId]);
        if (!$stmt->fetch()) {
            return $json($response, 404, ['error' => 'game not found']);
        }

        $stmt = $pdo->prepare(
            "SELECT cm.*, p.name AS player_name
             FROM chat_messages cm
             JOIN players p ON p.id = cm.player_id
             WHERE cm.game_id = ?
             ORDER BY cm.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$gameId]);
        $messages = array_reverse(array_map($formatMessage, $stmt->fetchAll()));

        return $json($response, 200, $messages);
    });

    $app->post('/games/{gameId}/chat', function (
        Request $request,
        Response $response,
        array $args
    ) use ($json, $ensureChatTable, $formatMessage): Response {
        $gameId = (int)$args['gameId'];
        $body = $request->getParsedBody() ?: [];
        $playerId = (int)($body['playerId'] ?? 0);
        $message = trim((string)($body['message'] ?? ''));
        $pdo = Database::getConnection();
        $ensureChatTable($pdo);

        if (!$playerId || $message === '') {
            return $json($response, 400, ['error' => 'playerId si message sunt obligatorii']);
        }

        if (function_exists('mb_strlen') && mb_strlen($message) > 500) {
            $message = mb_substr($message, 0, 500);
        } elseif (!function_exists('mb_strlen') && strlen($message) > 500) {
            $message = substr($message, 0, 500);
        }

        $stmt = $pdo->prepare('SELECT id FROM players WHERE id = ? AND game_id = ?');
        $stmt->execute([$playerId, $gameId]);
        if (!$stmt->fetch()) {
            return $json($response, 404, ['error' => 'player not found']);
        }

        $stmt = $pdo->prepare('INSERT INTO chat_messages (game_id, player_id, message) VALUES (?, ?, ?)');
        $stmt->execute([$gameId, $playerId, $message]);
        $messageId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'SELECT cm.*, p.name AS player_name
             FROM chat_messages cm
             JOIN players p ON p.id = cm.player_id
             WHERE cm.id = ?'
        );
        $stmt->execute([$messageId]);

        return $json($response, 201, $formatMessage($stmt->fetch()));
    });
};

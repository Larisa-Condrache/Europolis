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

    $ownedForGame = function (PDO $pdo, int $gameId): array {
        $stmt = $pdo->prepare("
            SELECT op.property_tile_index, op.player_id, op.purchase_price, op.created_at, p.name AS owner_name
            FROM owned_properties op
            JOIN players p ON p.id = op.player_id
            WHERE op.game_id = ?
        ");
        $stmt->execute([$gameId]);

        $owned = [];
        foreach ($stmt->fetchAll() as $row) {
            $owned[(int)$row['property_tile_index']] = $row;
        }

        return $owned;
    };

    $toProperty = function (array $tile, ?array $owner): array {
        return [
            'id' => $tile['index'],
            'tileIndex' => $tile['index'],
            'name' => $tile['name'],
            'type' => $tile['type'],
            'group' => $tile['color'],
            'price' => $tile['price'],
            'ownerId' => $owner ? (int)$owner['player_id'] : null,
            'ownerName' => $owner['owner_name'] ?? null,
            'purchasePrice' => $owner ? (int)$owner['purchase_price'] : null,
            'ownedAt' => $owner['created_at'] ?? null,
        ];
    };

    $app->get('/games/{gameId}/properties', function (
        Request $request,
        Response $response,
        array $args
    ) use ($json, $ownedForGame, $toProperty): Response {
        $gameId = (int)$args['gameId'];
        $query = $request->getQueryParams();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT id FROM games WHERE id = ?");
        $stmt->execute([$gameId]);
        if (!$stmt->fetch()) {
            return $json($response, 404, ['error' => 'game not found']);
        }

        $owned = $ownedForGame($pdo, $gameId);
        $properties = [];

        foreach (europolis_tiles() as $tile) {
            if (!in_array($tile['type'], ['property', 'station', 'utility'], true)) {
                continue;
            }

            $owner = $owned[$tile['index']] ?? null;
            $property = $toProperty($tile, $owner);

            if (isset($query['ownerId']) && $query['ownerId'] !== '' && (int)$query['ownerId'] !== ($property['ownerId'] ?? 0)) {
                continue;
            }

            if (!empty($query['group']) && $query['group'] !== $property['group']) {
                continue;
            }

            $properties[] = $property;
        }

        return $json($response, 200, $properties);
    });

    $app->get('/games/{gameId}/properties/{propertyId}', function (
        Request $request,
        Response $response,
        array $args
    ) use ($json, $ownedForGame, $toProperty): Response {
        $gameId = (int)$args['gameId'];
        $propertyId = (int)$args['propertyId'];
        $pdo = Database::getConnection();

        $tile = europolis_get_tile($propertyId);
        if (!in_array($tile['type'], ['property', 'station', 'utility'], true)) {
            return $json($response, 404, ['error' => 'property not found']);
        }

        $owned = $ownedForGame($pdo, $gameId);
        $property = $toProperty($tile, $owned[$propertyId] ?? null);
        $property['buildings'] = [];

        return $json($response, 200, $property);
    });

    $app->get('/games/{gameId}/properties/{propertyId}/buildings', function (
        Request $request,
        Response $response,
        array $args
    ) use ($json): Response {
        return $json($response, 200, []);
    });
};

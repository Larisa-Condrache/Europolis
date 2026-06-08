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

    $ensureUpgradeColumn = function (PDO $pdo): void {
        $stmt = $pdo->query("SHOW COLUMNS FROM owned_properties LIKE 'building_level'");
        if (!$stmt->fetch()) {
            $pdo->exec('ALTER TABLE owned_properties ADD building_level TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER purchase_price');
        }
    };

    $ownedForGame = function (PDO $pdo, int $gameId): array {
        $stmt = $pdo->prepare("
            SELECT op.property_tile_index, op.player_id, op.purchase_price, op.building_level, op.created_at, p.name AS owner_name
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
        $buildingLevel = $owner ? (int)($owner['building_level'] ?? 0) : 0;

        return [
            'id' => $tile['index'],
            'tileIndex' => $tile['index'],
            'name' => $tile['name'],
            'type' => $tile['type'],
            'group' => $tile['color'],
            'groupMembers' => $tile['type'] === 'property' ? europolis_group_members($tile['color']) : [],
            'requiresCompleteGroupToBuild' => $tile['type'] === 'property',
            'buildingProgression' => $tile['type'] === 'property' ? europolis_building_progression() : [],
            'price' => $tile['price'],
            'baseRent' => in_array($tile['type'], ['property', 'station', 'utility'], true)
                ? europolis_calculate_rent($tile, 0)
                : null,
            'rent' => in_array($tile['type'], ['property', 'station', 'utility'], true)
                ? europolis_calculate_rent($tile, $tile['type'] === 'property' ? $buildingLevel : 0)
                : null,
            'upgradeLevel' => $buildingLevel,
            'upgradeLabel' => europolis_upgrade_label($buildingLevel),
            'nextUpgradeCost' => ($tile['type'] === 'property' && $owner && $buildingLevel < 4)
                ? europolis_build_cost($tile, $buildingLevel + 1)
                : null,
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
    ) use ($json, $ownedForGame, $toProperty, $ensureUpgradeColumn): Response {
        $gameId = (int)$args['gameId'];
        $query = $request->getQueryParams();
        $pdo = Database::getConnection();
        $ensureUpgradeColumn($pdo);

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
    ) use ($json, $ownedForGame, $toProperty, $ensureUpgradeColumn): Response {
        $gameId = (int)$args['gameId'];
        $propertyId = (int)$args['propertyId'];
        $pdo = Database::getConnection();
        $ensureUpgradeColumn($pdo);

        $tile = europolis_get_tile($propertyId);
        if (!in_array($tile['type'], ['property', 'station', 'utility'], true)) {
            return $json($response, 404, ['error' => 'property not found']);
        }

        $owned = $ownedForGame($pdo, $gameId);
        $property = $toProperty($tile, $owned[$propertyId] ?? null);
        $property['buildings'] = [
            'level' => $property['upgradeLevel'],
            'label' => $property['upgradeLabel'],
            'rent' => $property['rent'],
        ];

        return $json($response, 200, $property);
    });

    $app->get('/games/{gameId}/properties/{propertyId}/buildings', function (
        Request $request,
        Response $response,
        array $args
    ) use ($json, $ensureUpgradeColumn): Response {
        $gameId = (int)$args['gameId'];
        $propertyId = (int)$args['propertyId'];
        $pdo = Database::getConnection();
        $ensureUpgradeColumn($pdo);

        $stmt = $pdo->prepare('SELECT building_level FROM owned_properties WHERE game_id = ? AND property_tile_index = ?');
        $stmt->execute([$gameId, $propertyId]);
        $level = $stmt->fetchColumn();

        if ($level === false) {
            return $json($response, 404, ['error' => 'property not owned']);
        }

        $tile = europolis_get_tile($propertyId);

        return $json($response, 200, [
            'level' => (int)$level,
            'label' => europolis_upgrade_label((int)$level),
            'rent' => europolis_calculate_rent($tile, (int)$level),
            'nextUpgradeCost' => (int)$level < 4 ? europolis_build_cost($tile, (int)$level + 1) : null,
            'progression' => europolis_building_progression(),
        ]);
    });

    $app->post('/games/{gameId}/properties/{propertyId}/buildings', function (
        Request $request,
        Response $response,
        array $args
    ) use ($json, $ensureUpgradeColumn): Response {
        $gameId = (int)$args['gameId'];
        $propertyId = (int)$args['propertyId'];
        $body = json_decode((string)$request->getBody(), true) ?? [];
        $playerId = (int)($body['playerId'] ?? 0);
        $pdo = Database::getConnection();
        $ensureUpgradeColumn($pdo);

        if (!$playerId) {
            return $json($response, 400, ['error' => 'playerId obligatoriu']);
        }

        $tile = europolis_get_tile($propertyId);
        if ($tile['type'] !== 'property') {
            return $json($response, 422, ['error' => 'Doar proprietatile pot primi upgrade.']);
        }

        $stmt = $pdo->prepare(
            'SELECT op.id, op.building_level, p.balance
             FROM owned_properties op
             JOIN players p ON p.id = op.player_id AND p.game_id = op.game_id
             WHERE op.game_id = ? AND op.property_tile_index = ? AND op.player_id = ?
             LIMIT 1'
        );
        $stmt->execute([$gameId, $propertyId, $playerId]);
        $owned = $stmt->fetch();

        if (!$owned) {
            return $json($response, 404, ['error' => 'Proprietatea nu apartine jucatorului.']);
        }

        $stmt = $pdo->prepare(
            'SELECT property_tile_index
             FROM owned_properties
             WHERE game_id = ? AND player_id = ?'
        );
        $stmt->execute([$gameId, $playerId]);
        $ownedIndexes = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $neededIndexes = [];
        foreach (europolis_tiles() as $candidate) {
            if ($candidate['type'] === 'property' && $candidate['color'] === $tile['color']) {
                $neededIndexes[] = (int)$candidate['index'];
            }
        }

        if (array_diff($neededIndexes, $ownedIndexes)) {
            return $json($response, 422, ['error' => 'Trebuie sa detii tot grupul de culoare inainte sa construiesti.']);
        }

        $currentLevel = (int)$owned['building_level'];
        if ($currentLevel >= 4) {
            return $json($response, 409, ['error' => 'Proprietatea are deja Hotel.']);
        }

        $nextLevel = $currentLevel + 1;
        $buildCost = europolis_build_cost($tile, $nextLevel);

        if ((int)$owned['balance'] < $buildCost) {
            return $json($response, 422, ['error' => 'Nu ai suficienti bani pentru upgrade.']);
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE players SET balance = balance - ? WHERE id = ? AND game_id = ?')
                ->execute([$buildCost, $playerId, $gameId]);
            $pdo->prepare('UPDATE owned_properties SET building_level = ? WHERE id = ?')
                ->execute([$nextLevel, (int)$owned['id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return $json($response, 500, ['error' => 'UPGRADE_FAILED', 'message' => $e->getMessage()]);
        }

        return $json($response, 200, [
            'propertyId' => $propertyId,
            'upgradeLevel' => $nextLevel,
            'upgradeLabel' => europolis_upgrade_label($nextLevel),
            'cost' => $buildCost,
            'rent' => europolis_calculate_rent($tile, $nextLevel),
        ]);
    });

    $app->post('/games/{gameId}/properties/{propertyId}/sell', function (
        Request $request,
        Response $response,
        array $args
    ) use ($json, $ensureUpgradeColumn): Response {
        $gameId = (int)$args['gameId'];
        $propertyId = (int)$args['propertyId'];
        $body = json_decode((string)$request->getBody(), true) ?? [];
        $playerId = (int)($body['playerId'] ?? 0);
        $pdo = Database::getConnection();
        $ensureUpgradeColumn($pdo);

        if (!$playerId) {
            return $json($response, 400, ['error' => 'playerId obligatoriu']);
        }

        $stmt = $pdo->prepare("
            SELECT op.*, p.name AS owner_name
            FROM owned_properties op
            JOIN players p ON p.id = op.player_id
            WHERE op.game_id = :gameId
              AND op.property_tile_index = :propertyId
              AND op.player_id = :playerId
            LIMIT 1
        ");
        $stmt->execute([
            'gameId' => $gameId,
            'propertyId' => $propertyId,
            'playerId' => $playerId,
        ]);
        $owned = $stmt->fetch();

        if (!$owned) {
            return $json($response, 404, [
                'error' => 'PROPERTY_NOT_OWNED',
                'message' => 'Proprietatea nu apartine jucatorului sau nu este cumparata.',
            ]);
        }

        $refund = intdiv((int)$owned['purchase_price'], 2);

        try {
            $pdo->beginTransaction();

            $pdo->prepare("UPDATE players SET balance = balance + :refund WHERE id = :playerId")
                ->execute(['refund' => $refund, 'playerId' => $playerId]);

            $pdo->prepare("DELETE FROM owned_properties WHERE id = :id")
                ->execute(['id' => (int)$owned['id']]);

            $stmt = $pdo->prepare("SELECT id, name, balance, position, status FROM players WHERE id = :playerId");
            $stmt->execute(['playerId' => $playerId]);
            $player = $stmt->fetch();

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return $json($response, 500, ['error' => 'SELL_FAILED', 'message' => $e->getMessage()]);
        }

        $tile = europolis_get_tile($propertyId);

        return $json($response, 200, [
            'message' => 'Proprietate vanduta catre banca',
            'refund' => $refund,
            'soldProperty' => [
                'tileIndex' => $propertyId,
                'name' => $tile['name'],
                'purchasePrice' => (int)$owned['purchase_price'],
            ],
            'player' => [
                'id' => (int)$player['id'],
                'name' => $player['name'],
                'balance' => (int)$player['balance'],
                'position' => (int)$player['position'],
                'status' => $player['status'],
            ],
        ]);
    });
};

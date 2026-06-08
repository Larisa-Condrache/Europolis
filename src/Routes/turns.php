<?php

declare(strict_types=1);

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



    $countPlayerAssets = function (PDO $pdo, int $gameId, int $playerId): int {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM owned_properties WHERE game_id = ? AND player_id = ?');
        $stmt->execute([$gameId, $playerId]);
        return (int)$stmt->fetchColumn();
    };

    $finishGameIfSingleActive = function (PDO $pdo, int $gameId): array {
        $stmt = $pdo->prepare('SELECT id, name FROM players WHERE game_id = ? AND status = "active" ORDER BY id ASC');
        $stmt->execute([$gameId]);
        $activePlayers = $stmt->fetchAll();

        if (count($activePlayers) === 1) {
            $winner = $activePlayers[0];
            $pdo->prepare('UPDATE games SET status = "finished", current_player_id = NULL WHERE id = ?')
                ->execute([$gameId]);

            return [
                'finished' => true,
                'winnerPlayerId' => (int)$winner['id'],
                'winnerName' => $winner['name'],
            ];
        }

        $currentPlayerId = null;
        if (count($activePlayers) > 0) {
            $currentPlayerId = (int)$activePlayers[0]['id'];
            $pdo->prepare('UPDATE games SET current_player_id = ? WHERE id = ?')
                ->execute([$currentPlayerId, $gameId]);
        }

        return [
            'finished' => false,
            'winnerPlayerId' => null,
            'winnerName' => null,
            'nextPlayerId' => $currentPlayerId,
        ];
    };

    $declareBankruptcy = function (PDO $pdo, int $gameId, int $playerId, ?int $creditorId = null) use ($finishGameIfSingleActive): array {
        $stmt = $pdo->prepare('SELECT id, name, balance FROM players WHERE id = ? AND game_id = ? LIMIT 1');
        $stmt->execute([$playerId, $gameId]);
        $player = $stmt->fetch();

        if (!$player) {
            return ['bankrupt' => false, 'message' => 'Jucatorul nu exista.'];
        }

        $remainingBalance = max(0, (int)$player['balance']);

        if ($creditorId !== null && $creditorId !== $playerId && $remainingBalance > 0) {
            $pdo->prepare('UPDATE players SET balance = balance + ? WHERE id = ? AND game_id = ?')
                ->execute([$remainingBalance, $creditorId, $gameId]);
        }

        $pdo->prepare('UPDATE players SET status = "bankrupt", balance = 0 WHERE id = ? AND game_id = ?')
            ->execute([$playerId, $gameId]);

        // Proprietatile jucatorului revin bancii si pot fi cumparate ulterior de alti jucatori.
        $pdo->prepare('DELETE FROM owned_properties WHERE game_id = ? AND player_id = ?')
            ->execute([$gameId, $playerId]);

        $gameResult = $finishGameIfSingleActive($pdo, $gameId);

        return array_merge([
            'bankrupt' => true,
            'bankruptPlayerId' => $playerId,
            'bankruptPlayerName' => $player['name'],
            'remainingBalanceTransferred' => $remainingBalance,
            'message' => 'Jucatorul a intrat in faliment deoarece nu mai poate plati si nu mai are proprietati de vandut.',
        ], $gameResult);
    };

    $formatTurn = function (array $turn): array {
        return [
            'turnId' => (int)$turn['id'],
            'gameId' => (int)$turn['game_id'],
            'playerId' => (int)$turn['player_id'],
            'playerName' => $turn['player_name'] ?? null,
            'dice' => [(int)$turn['dice1'], (int)$turn['dice2']],
            'fromPosition' => (int)$turn['from_position'],
            'toPosition' => (int)$turn['to_position'],
            'landedTile' => europolis_get_tile((int)$turn['to_position']),
            'createdAt' => $turn['created_at'],
        ];
    };

    $app->get('/games/{id}/turns', function (Request $request, Response $response, array $args) use ($json, $formatTurn) {
        $pdo = $this->get(PDO::class);
        $gameId = (int)$args['id'];
        $query = $request->getQueryParams();

        $sql = 'SELECT t.*, p.name AS player_name
                FROM turns t
                JOIN players p ON p.id = t.player_id
                WHERE t.game_id = ?';
        $params = [$gameId];

        if (!empty($query['playerId'])) {
            $sql .= ' AND t.player_id = ?';
            $params[] = (int)$query['playerId'];
        }

        $sql .= ' ORDER BY t.id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $json($response, 200, array_map($formatTurn, $stmt->fetchAll()));
    });

    $app->get('/games/{id}/turns/{turnId}', function (Request $request, Response $response, array $args) use ($json, $formatTurn) {
        $pdo = $this->get(PDO::class);
        $stmt = $pdo->prepare(
            'SELECT t.*, p.name AS player_name
             FROM turns t
             JOIN players p ON p.id = t.player_id
             WHERE t.game_id = ? AND t.id = ?'
        );
        $stmt->execute([(int)$args['id'], (int)$args['turnId']]);
        $turn = $stmt->fetch();

        if (!$turn) {
            return $json($response, 404, ['error' => 'turn not found']);
        }

        return $json($response, 200, $formatTurn($turn));
    });

    $app->get('/games/{id}/turns/{turnId}/actions', function (Request $request, Response $response, array $args) use ($json) {
        $pdo = $this->get(PDO::class);
        $stmt = $pdo->prepare(
            'SELECT id, turn_id, game_id, action_type, details, created_at
             FROM actions
             WHERE game_id = ? AND turn_id = ?
             ORDER BY id'
        );
        $stmt->execute([(int)$args['id'], (int)$args['turnId']]);

        $actions = array_map(fn($action) => [
            'id' => (int)$action['id'],
            'turnId' => (int)$action['turn_id'],
            'gameId' => (int)$action['game_id'],
            'actionType' => $action['action_type'],
            'details' => $action['details'] ? json_decode($action['details'], true) : null,
            'createdAt' => $action['created_at'],
        ], $stmt->fetchAll());

        return $json($response, 200, $actions);
    });

    $app->post('/games/{id}/turns', function (Request $request, Response $response, array $args) use ($json, $ensureUpgradeColumn) {
        $pdo = $this->get(PDO::class);
        $ensureUpgradeColumn($pdo);
        $gameId = (int)$args['id'];
        $body = $request->getParsedBody() ?: [];
        $playerId = (int)($body['playerId'] ?? 0);

        if (!$playerId) {
            return $json($response, 400, ['error' => 'Missing playerId']);
        }

        $stmt = $pdo->prepare('SELECT status, current_player_id FROM games WHERE id = ?');
        $stmt->execute([$gameId]);
        $game = $stmt->fetch();

        if (!$game) {
            return $json($response, 404, ['error' => 'game not found']);
        }
        if ($game['status'] !== 'active') {
            return $json($response, 409, ['error' => 'game not active']);
        }
        if ((int)$game['current_player_id'] !== $playerId) {
            return $json($response, 403, ['error' => 'not your turn']);
        }

        $stmt = $pdo->prepare(
            'SELECT position, balance, in_jail, jail_turns
             FROM players
             WHERE id = ? AND game_id = ?'
        );
        $stmt->execute([$playerId, $gameId]);
        $player = $stmt->fetch();

        if (!$player) {
            return $json($response, 404, ['error' => 'player not found']);
        }

        if ((int)$player['in_jail'] === 1) {
            $position = (int)$player['position'];
            $stmt = $pdo->prepare(
                'INSERT INTO turns (game_id, player_id, dice1, dice2, from_position, to_position)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$gameId, $playerId, 0, 0, $position, $position]);
            $turnId = (int)$pdo->lastInsertId();

            return $json($response, 201, [
                'turnId' => $turnId,
                'playerId' => $playerId,
                'dice' => [0, 0],
                'fromPosition' => $position,
                'toPosition' => $position,
                'landedTile' => europolis_get_tile($position),
                'availableActions' => ['pay_jail', 'end_turn'],
                'message' => 'Ești în închisoare. Plătește taxa ca să ieși sau termină tura.',
            ]);
        }

        $from = (int)$player['position'];
        if ($from >= europolis_board_size()) {
            $from = 0;
        }
        $dice1 = random_int(1, 6);
        $dice2 = random_int(1, 6);
        $steps = $dice1 + $dice2;
        $rawTo = $from + $steps;
        $passedStart = $rawTo >= europolis_board_size();
        $to = $rawTo % europolis_board_size();
        $tile = europolis_get_tile($to);
        $availableActions = [];
        $messageParts = [];
        $bonus = 0;
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM turns
             WHERE game_id = ? AND player_id = ? AND from_position > to_position'
        );
        $stmt->execute([$gameId, $playerId]);
        $previousStartPasses = (int)$stmt->fetchColumn();
        $startBonus = $previousStartPasses === 0 ? 400 : 200;

        if ($passedStart) {
            $bonus += $startBonus;
            $messageParts[] = 'Ai trecut prin START și ai încasat M400.';
        }

        if ($tile['type'] === 'go' && !$passedStart) {
            $bonus += $startBonus;
            $messageParts[] = 'Ai ajuns pe START și ai încasat M400.';
        }

        if ($passedStart || ($tile['type'] === 'go' && !$passedStart)) {
            array_pop($messageParts);
            $messageParts[] = "Ai trecut prin START si ai incasat M{$startBonus}.";
        }

        if ($tile['type'] === 'jackpot') {
            $bonus += 400;
            $messageParts[] = 'Jackpot Economic: ai încasat M400.';
        }

        if ($tile['type'] === 'gotojail') {
            $to = europolis_jail_index();
            $tile = europolis_get_tile(europolis_jail_index());
            $pdo->prepare(
                'UPDATE players
                 SET position = ?, in_jail = 1, jail_turns = 0
                 WHERE id = ? AND game_id = ?'
            )->execute([europolis_jail_index(), $playerId, $gameId]);
            $availableActions[] = 'pay_jail';
            $availableActions[] = 'end_turn';
        } else {
            $pdo->prepare('UPDATE players SET position = ? WHERE id = ? AND game_id = ?')
                ->execute([$to, $playerId, $gameId]);

            if ($bonus > 0) {
                $pdo->prepare('UPDATE players SET balance = balance + ? WHERE id = ? AND game_id = ?')
                    ->execute([$bonus, $playerId, $gameId]);
            }

            if (in_array($tile['type'], ['property', 'station', 'utility'], true)) {
                $stmt = $pdo->prepare(
                    'SELECT player_id FROM owned_properties WHERE game_id = ? AND property_tile_index = ?'
                );
                $stmt->execute([$gameId, $to]);
                $owner = $stmt->fetchColumn();

                if (!$owner) {
                    $availableActions[] = 'buy_property';
                } elseif ((int)$owner !== $playerId) {
                    $availableActions[] = 'pay_rent';
                }
            } elseif ($tile['type'] === 'tax') {
                $availableActions[] = 'pay_tax';
            } elseif (in_array($tile['type'], ['chance', 'community'], true)) {
                $availableActions[] = 'draw_card';
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO turns (game_id, player_id, dice1, dice2, from_position, to_position)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$gameId, $playerId, $dice1, $dice2, $from, $to]);
        $turnId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM players WHERE game_id = ? AND id != ? AND status = "active"'
        );
        $stmt->execute([$gameId, $playerId]);
        if ((int)$stmt->fetchColumn() > 0) {
            $availableActions[] = 'trade';
        }

        $mustResolve = array_intersect($availableActions, ['pay_rent', 'pay_tax']);
        if (!$mustResolve && !in_array('end_turn', $availableActions, true)) {
            $availableActions[] = 'end_turn';
        }

        return $json($response, 201, [
            'turnId' => $turnId,
            'playerId' => $playerId,
            'dice' => [$dice1, $dice2],
            'fromPosition' => $from,
            'toPosition' => $to,
            'landedTile' => $tile,
            'availableActions' => array_values(array_unique($availableActions)),
            'message' => implode(' ', $messageParts),
        ]);
    });

    $app->post('/games/{id}/turns/{turnId}/actions', function (Request $request, Response $response, array $args) use ($json, $ensureUpgradeColumn, $countPlayerAssets, $declareBankruptcy) {
        $pdo = $this->get(PDO::class);
        $ensureUpgradeColumn($pdo);
        $gameId = (int)$args['id'];
        $turnId = (int)$args['turnId'];
        $body = $request->getParsedBody() ?: [];
        $action = (string)($body['actionType'] ?? '');
        $playerId = (int)($body['playerId'] ?? 0);

        if ($action === '') {
            return $json($response, 400, ['error' => 'Missing actionType']);
        }
        if (!$playerId) {
            return $json($response, 400, ['error' => 'Missing playerId']);
        }

        $stmt = $pdo->prepare('SELECT current_player_id FROM games WHERE id = ?');
        $stmt->execute([$gameId]);
        $currentPlayerId = (int)$stmt->fetchColumn();

        if (!$currentPlayerId) {
            return $json($response, 404, ['error' => 'game not found']);
        }
        if ($playerId !== $currentPlayerId) {
            return $json($response, 403, ['error' => 'not your turn']);
        }

        $stmt = $pdo->prepare(
            'SELECT position, balance, in_jail, jail_turns
             FROM players
             WHERE id = ? AND game_id = ?'
        );
        $stmt->execute([$playerId, $gameId]);
        $player = $stmt->fetch();

        if (!$player) {
            return $json($response, 404, ['error' => 'current player not found']);
        }

        if ((int)$player['in_jail'] === 1 && !in_array($action, ['pay_jail', 'end_turn'], true)) {
            return $json($response, 403, [
                'error' => 'player_in_jail',
                'message' => 'Ești în închisoare. Trebuie să plătești taxa ca să ieși.',
            ]);
        }

        $position = (int)$player['position'];
        $tile = europolis_get_tile($position);
        $details = ['playerId' => $playerId, 'position' => $position, 'tile' => $tile];
        $turnHasAction = function (string $actionType) use ($pdo, $turnId, $gameId): bool {
            $stmt = $pdo->prepare(
                'SELECT id FROM actions WHERE turn_id = ? AND game_id = ? AND action_type = ? LIMIT 1'
            );
            $stmt->execute([$turnId, $gameId, $actionType]);
            return (bool)$stmt->fetchColumn();
        };

        if ($action === 'buy_property') {
            if (!in_array($tile['type'], ['property', 'station', 'utility'], true)) {
                return $json($response, 422, ['error' => 'tile is not buyable']);
            }

            $stmt = $pdo->prepare('SELECT id FROM owned_properties WHERE game_id = ? AND property_tile_index = ? LIMIT 1');
            $stmt->execute([$gameId, $position]);
            if ($stmt->fetch()) {
                return $json($response, 409, ['error' => 'property already owned']);
            }

            $price = (int)($tile['price'] ?? 0);
            if ((int)$player['balance'] < $price) {
                return $json($response, 422, ['error' => 'insufficient funds']);
            }

            $pdo->prepare('UPDATE players SET balance = balance - ? WHERE id = ? AND game_id = ?')
                ->execute([$price, $playerId, $gameId]);
            $pdo->prepare(
                'INSERT INTO owned_properties (game_id, player_id, property_tile_index, purchase_price)
                 VALUES (?, ?, ?, ?)'
            )->execute([$gameId, $playerId, $position, $price]);
            $details['price'] = $price;
        }

        if ($action === 'pay_rent') {
            if ($turnHasAction('pay_rent')) {
                return $json($response, 409, ['error' => 'rent already paid']);
            }

            $stmt = $pdo->prepare('SELECT player_id, building_level FROM owned_properties WHERE game_id = ? AND property_tile_index = ?');
            $stmt->execute([$gameId, $position]);
            $owned = $stmt->fetch();
            $ownerId = (int)($owned['player_id'] ?? 0);

            if (!$ownerId || $ownerId === $playerId) {
                return $json($response, 422, ['error' => 'rent not required']);
            }

            $rent = europolis_calculate_rent($tile, (int)($owned['building_level'] ?? 0));
            if ((int)$player['balance'] < $rent) {

                $assetCount = $countPlayerAssets($pdo, $gameId, $playerId);

                // Mai are proprietati => trebuie sa le vanda
                if ($assetCount > 0) {
                    return $json($response, 409, [
                        'error' => 'insufficient_funds_assets_available',
                        'message' => 'Nu ai suficienti bani pentru chirie, dar ai proprietati care pot fi vandute catre banca.',
                        'rent' => $rent,
                        'balance' => (int)$player['balance'],
                        'propertiesAvailableToSell' => $assetCount,
                    ]);
                }

                // Nu mai are proprietati => faliment
                $details['rent'] = $rent;
                $details['ownerId'] = $ownerId;
                $details['bankruptcy'] = $declareBankruptcy(
                    $pdo,
                    $gameId,
                    $playerId,
                    $ownerId
                );

            } else {
                $pdo->prepare('UPDATE players SET balance = balance - ? WHERE id = ? AND game_id = ?')
                    ->execute([$rent, $playerId, $gameId]);

                $pdo->prepare('UPDATE players SET balance = balance + ? WHERE id = ? AND game_id = ?')
                    ->execute([$rent, $ownerId, $gameId]);

                $details['rent'] = $rent;
                $details['ownerId'] = $ownerId;
            }
        }
        if ($action === 'pay_tax') {
            if ($turnHasAction('pay_tax')) {
                return $json($response, 409, ['error' => 'tax already paid']);
            }

            $tax = (int)($tile['price'] ?? 100);
            if ((int)$player['balance'] < $tax) {
                $assetCount = $countPlayerAssets($pdo, $gameId, $playerId);

                if ($assetCount > 0) {
                    return $json($response, 409, [
                        'error' => 'insufficient_funds_assets_available',
                        'message' => 'Nu ai suficienti bani pentru taxa, dar ai proprietati care pot fi vandute catre banca.',
                        'tax' => $tax,
                        'balance' => (int)$player['balance'],
                        'propertiesAvailableToSell' => $assetCount,
                    ]);
                }

                $details['tax'] = $tax;
                $details['bankruptcy'] = $declareBankruptcy($pdo, $gameId, $playerId, null);
            } else {
                $pdo->prepare('UPDATE players SET balance = balance - ? WHERE id = ? AND game_id = ?')
                    ->execute([$tax, $playerId, $gameId]);
                $details['tax'] = $tax;
            }
        }

        if ($action === 'pay_jail') {
            if ((int)$player['in_jail'] !== 1) {
                return $json($response, 422, ['error' => 'player is not in jail']);
            }

            $jailFee = 50;
            if ((int)$player['balance'] < $jailFee) {
                $assetCount = $countPlayerAssets($pdo, $gameId, $playerId);

                if ($assetCount > 0) {
                    return $json($response, 409, [
                        'error' => 'insufficient_funds_assets_available',
                        'message' => 'Nu ai suficienti bani pentru taxa de inchisoare, dar ai proprietati care pot fi vandute catre banca.',
                        'jailFee' => $jailFee,
                        'balance' => (int)$player['balance'],
                        'propertiesAvailableToSell' => $assetCount,
                    ]);
                }

                $details['jailFee'] = $jailFee;
                $details['bankruptcy'] = $declareBankruptcy($pdo, $gameId, $playerId, null);
            } else {
                $pdo->prepare(
                    'UPDATE players SET balance = balance - ?, in_jail = 0, jail_turns = 0 WHERE id = ? AND game_id = ?'
                )->execute([$jailFee, $playerId, $gameId]);
                $details['jailFee'] = $jailFee;
                $details['message'] = 'Ai plătit taxa și ai ieșit din închisoare.';
            }
        }

        if ($action === 'draw_card') {
            if (!in_array($tile['type'], ['chance', 'community'], true)) {
                return $json($response, 422, ['error' => 'card not available on this tile']);
            }

            $cards = [
                ['text' => 'Primești 100€ de la bancă.', 'money' => 100],
                ['text' => 'Plătești 50€ amendă.', 'money' => -50],
                ['text' => 'Avansează la START.', 'move' => 0],
                ['text' => 'Mergi la Închisoare Economică.', 'move' => europolis_jail_index(), 'jail' => true],
            ];
            $card = $cards[array_rand($cards)];

            if (isset($card['money'])) {
                $pdo->prepare('UPDATE players SET balance = balance + ? WHERE id = ? AND game_id = ?')
                    ->execute([(int)$card['money'], $playerId, $gameId]);
            }
            if (isset($card['move'])) {
                if (!empty($card['jail'])) {
                    $pdo->prepare('UPDATE players SET position = ?, in_jail = 1, jail_turns = 0 WHERE id = ? AND game_id = ?')
                        ->execute([europolis_jail_index(), $playerId, $gameId]);
                } else {
                    $pdo->prepare('UPDATE players SET position = ? WHERE id = ? AND game_id = ?')
                        ->execute([(int)$card['move'], $playerId, $gameId]);
                }
            }
            $details['card'] = $card;
        }

        if ($action === 'go_to_jail') {
            $pdo->prepare('UPDATE players SET position = ?, in_jail = 1, jail_turns = 0 WHERE id = ? AND game_id = ?')
                ->execute([europolis_jail_index(), $playerId, $gameId]);
            $details['message'] = 'Jucătorul a fost trimis la închisoare.';
            $details['newPosition'] = europolis_jail_index();
        }

        if ($action === 'end_turn') {
            if ($tile['type'] === 'tax' && !$turnHasAction('pay_tax')) {
                return $json($response, 409, [
                    'error' => 'tax_required',
                    'message' => 'Trebuie să plătești Taxe Internaționale înainte să termini tura.',
                ]);
            }

            if (in_array($tile['type'], ['property', 'station', 'utility'], true)) {
                $stmt = $pdo->prepare(
                    'SELECT player_id FROM owned_properties WHERE game_id = ? AND property_tile_index = ?'
                );
                $stmt->execute([$gameId, $position]);
                $ownerId = (int)$stmt->fetchColumn();

                if ($ownerId && $ownerId !== $playerId && !$turnHasAction('pay_rent')) {
                    return $json($response, 409, [
                        'error' => 'rent_required',
                        'message' => 'Trebuie să plătești chiria înainte să termini tura.',
                    ]);
                }
            }

            $stmt = $pdo->prepare('SELECT id FROM players WHERE game_id = ? AND status = "active" ORDER BY id ASC');
            $stmt->execute([$gameId]);
            $players = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($players) > 0) {
                $currentIndex = array_search($playerId, $players, false);
                $nextIndex = (($currentIndex === false ? 0 : $currentIndex) + 1) % count($players);
                $nextPlayer = (int)$players[$nextIndex];
                $pdo->prepare('UPDATE games SET current_player_id = ? WHERE id = ?')
                    ->execute([$nextPlayer, $gameId]);
                $details['nextPlayerId'] = $nextPlayer;
            }
        }

        $pdo->prepare(
            'INSERT INTO actions (turn_id, game_id, action_type, details)
             VALUES (?, ?, ?, ?)'
        )->execute([$turnId, $gameId, $action, json_encode($details, JSON_UNESCAPED_UNICODE)]);

        return $json($response, 200, ['ok' => true, 'action' => $action, 'details' => $details]);
    });
};

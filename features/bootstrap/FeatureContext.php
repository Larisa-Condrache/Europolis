<?php

use Behat\Behat\Context\Context;

final class FeatureContext implements Context
{
    private string $apiBase;
    private int $lastStatusCode = 0;
    private ?array $lastJson = null;
    private string $lastResponseBody = '';
    private ?int $gameId = null;
    private ?int $hostPlayerId = null;
    private ?int $secondPlayerId = null;
    private int $lastKnownPosition = 0;
    private ?int $tradeId = null;

    public function __construct()
    {
        $this->apiBase = rtrim((string)(getenv('EUROPOLIS_API_BASE') ?: 'http://localhost:8080'), '/');
    }

    /**
     * @Given API-ul este pornit
     */
    public function apiUlEstePornit(): void
    {
        $this->sendRequest('GET', '/games');
        $this->assertStatusCode(200);
        $this->assertJsonResponse();
    }

    /**
     * @When trimit o cerere POST la :path cu date valide
     */
    public function trimitOCererePostCuDateValide(string $path): void
    {
        if ($path !== '/games') {
            throw new RuntimeException("Pasul este definit pentru /games, primit: {$path}");
        }

        $this->createGame();
    }

    /**
     * @Then raspunsul trebuie sa aiba codul :code
     */
    public function raspunsulTrebuieSaAibaCodul(int $code): void
    {
        $this->assertStatusCode($code);
    }

    /**
     * @Then jocul trebuie sa fie creat
     */
    public function joculTrebuieSaFieCreat(): void
    {
        $this->assertStatusCode(201);
        $this->assertJsonResponse();
        $this->assertArrayHasKey('id', $this->lastJson);
        $this->assertArrayHasKey('players', $this->lastJson);

        if ($this->gameId === null || $this->hostPlayerId === null) {
            throw new RuntimeException('Game ID sau host player ID nu au fost extrase din raspuns.');
        }
    }

    /**
     * @Given exista un joc creat
     */
    public function existaUnJocCreat(): void
    {
        $this->createGame();
        $this->assertStatusCode(201);
    }

    /**
     * @When trimit o cerere POST la "\/games\/1\/players"
     */
    public function trimitOCererePostLaGamesPlayers(): void
    {
        $this->ensureGameCreated();
        $this->addPlayer('Player Behat');
    }

    /**
     * @Then jucatorul trebuie sa fie adaugat in joc
     */
    public function jucatorulTrebuieSaFieAdaugatInJoc(): void
    {
        $this->assertStatusCode(201);
        $this->assertJsonResponse();
        $this->assertArrayHasKey('id', $this->lastJson);

        if ($this->secondPlayerId === null) {
            throw new RuntimeException('Player ID nu a fost extras din raspuns.');
        }
    }

    /**
     * @Given exista un joc cu jucatori
     */
    public function existaUnJocCuJucatori(): void
    {
        $this->createGame(2);
        $this->addPlayer('Player Turn');
        $this->activateGame();
        $this->lastKnownPosition = 0;
    }

    /**
     * @When trimit o cerere POST la "\/games\/1\/turns"
     */
    public function trimitOCererePostLaGamesTurns(): void
    {
        $this->ensureGameWithPlayers();

        $this->sendRequest('POST', "/games/{$this->gameId}/turns", [
            'playerId' => $this->hostPlayerId,
        ]);
    }

    /**
     * @Then pozitia jucatorului trebuie sa se actualizeze
     */
    public function pozitiaJucatoruluiTrebuieSaSeActualizeze(): void
    {
        $this->assertStatusCode(201);
        $this->assertJsonResponse();
        $this->assertArrayHasKey('toPosition', $this->lastJson);
        $this->assertArrayHasKey('landedTile', $this->lastJson);

        if ((int)$this->lastJson['toPosition'] === $this->lastKnownPosition) {
            throw new RuntimeException('Pozitia jucatorului nu s-a schimbat dupa tura.');
        }
    }

    /**
     * @Given exista doi jucatori in joc
     */
    public function existaDoiJucatoriInJoc(): void
    {
        $this->createGame(2);
        $this->addPlayer('Player Trade');
        $this->activateGame();
    }

    /**
     * @When trimit o cerere POST la "\/games\/1\/trades"
     */
    public function trimitOCererePostLaGamesTrades(): void
    {
        $this->ensureGameWithPlayers();

        $this->sendRequest('POST', "/games/{$this->gameId}/trades", [
            'playerId' => $this->hostPlayerId,
            'fromPlayerId' => $this->hostPlayerId,
            'toPlayerId' => $this->secondPlayerId,
            'offeredMoney' => 25,
            'offeredPropertyIds' => [],
            'requestedPropertyIds' => [],
        ]);

        if (is_array($this->lastJson) && isset($this->lastJson['tradeId'])) {
            $this->tradeId = (int)$this->lastJson['tradeId'];
        }
    }

    /**
     * @Then schimbul trebuie sa fie inregistrat
     */
    public function schimbulTrebuieSaFieInregistrat(): void
    {
        $this->assertStatusCode(201);
        $this->assertJsonResponse();
        $this->assertArrayHasKey('tradeId', $this->lastJson);

        if ($this->tradeId === null) {
            throw new RuntimeException('Trade ID nu a fost extras din raspuns.');
        }
    }

    private function createGame(int $maxPlayers = 4): void
    {
        $this->sendRequest('POST', '/games', [
            'hostName' => 'Host Behat',
            'maxPlayers' => $maxPlayers,
        ]);

        $this->assertJsonResponse();
        $this->gameId = (int)($this->lastJson['id'] ?? 0) ?: null;
        $this->hostPlayerId = (int)(
            $this->lastJson['myPlayerId']
            ?? $this->lastJson['currentPlayerId']
            ?? $this->lastJson['players'][0]['id']
            ?? 0
        ) ?: null;
    }

    private function addPlayer(string $name): void
    {
        $this->ensureGameCreated();

        $this->sendRequest('POST', "/games/{$this->gameId}/players", [
            'name' => $name,
        ]);

        $this->assertJsonResponse();
        $this->secondPlayerId = (int)($this->lastJson['id'] ?? 0) ?: null;
    }

    private function activateGame(): void
    {
        $this->ensureGameWithPlayers();

        $this->sendRequest('PATCH', "/games/{$this->gameId}", [
            'status' => 'active',
        ]);

        if (!in_array($this->lastStatusCode, [200, 409], true)) {
            throw new RuntimeException("Jocul nu a putut fi activat. Cod primit: {$this->lastStatusCode}");
        }
    }

    private function ensureGameCreated(): void
    {
        if ($this->gameId === null || $this->hostPlayerId === null) {
            $this->createGame();
            $this->assertStatusCode(201);
        }
    }

    private function ensureGameWithPlayers(): void
    {
        $this->ensureGameCreated();

        if ($this->secondPlayerId === null) {
            $this->addPlayer('Player Behat 2');
            $this->assertStatusCode(201);
        }
    }

    private function sendRequest(string $method, string $path, ?array $payload = null): void
    {
        $url = $this->apiBase . $path;
        $curl = curl_init($url);

        $headers = ['Accept: application/json'];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
        ];

        if ($payload !== null) {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headers;
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);

        if ($body === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new RuntimeException("Cererea HTTP a esuat: {$error}. API base: {$this->apiBase}");
        }

        $this->lastStatusCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $this->lastResponseBody = (string)$body;
        curl_close($curl);

        $decoded = json_decode($this->lastResponseBody, true);
        $this->lastJson = is_array($decoded) ? $decoded : null;
    }

    private function assertStatusCode(int $expected): void
    {
        if ($this->lastStatusCode !== $expected) {
            throw new RuntimeException(
                "Cod HTTP asteptat {$expected}, primit {$this->lastStatusCode}. Raspuns: {$this->lastResponseBody}"
            );
        }
    }

    private function assertJsonResponse(): void
    {
        if ($this->lastJson === null) {
            throw new RuntimeException("Raspunsul nu este JSON valid: {$this->lastResponseBody}");
        }
    }

    private function assertArrayHasKey(string $key, ?array $data): void
    {
        if (!is_array($data) || !array_key_exists($key, $data)) {
            throw new RuntimeException("Raspunsul JSON nu contine cheia '{$key}'.");
        }
    }

    /**
 * @Given exista un joc activ cu doi jucatori pentru final
 */
public function existaUnJocActivCuDoiJucatoriPentruFinal(): void
{
    $this->createGame(2);
    $this->addPlayer('Player Loser');
    $this->activateGame();
}

/**
 * @When al doilea jucator pierde jocul
 */
public function alDoileaJucatorPierdeJocul(): void
{
    $this->ensureGameWithPlayers();

    $this->sendRequest('PATCH', "/players/{$this->secondPlayerId}", [
        'status' => 'bankrupt'
    ]);

    if (!in_array($this->lastStatusCode, [200, 204], true)) {
        throw new RuntimeException(
            "Jucatorul pierzator nu a putut fi declarat bankrupt. Cod primit: {$this->lastStatusCode}. Raspuns: {$this->lastResponseBody}"
        );
    }
}


/**
 * @When al doilea jucator ramane cu zero bani
 */
public function alDoileaJucatorRamaneCuZeroBani(): void
{
    $this->ensureGameWithPlayers();

    $this->sendRequest('PATCH', "/players/{$this->secondPlayerId}", [
        'balance' => 0,
        'status' => 'active'
    ]);

    if ($this->lastStatusCode !== 200) {
        throw new RuntimeException("Nu am putut seta soldul la zero. Raspuns: {$this->lastResponseBody}");
    }
}

/**
 * @Then jocul trebuie sa ramana activ
 */
public function joculTrebuieSaRamanaActiv(): void
{
    $this->sendRequest('GET', "/games/{$this->gameId}");
    $this->assertStatusCode(200);
    $this->assertJsonResponse();

    if (($this->lastJson['status'] ?? null) !== 'active') {
        throw new RuntimeException('Jocul nu a ramas activ. Raspuns: ' . $this->lastResponseBody);
    }

    $players = $this->lastJson['players'] ?? [];
    foreach ($players as $player) {
        if ((int)$player['id'] === $this->secondPlayerId && ($player['status'] ?? null) !== 'active') {
            throw new RuntimeException('Jucatorul cu 0 bani a fost declarat bankrupt prea devreme.');
        }
    }
}

/**
 * @Then primul jucator trebuie sa fie castigator
 */
public function primulJucatorTrebuieSaFieCastigator(): void
{
    $this->assertStatusCode(200);
    $this->assertJsonResponse();

    $this->sendRequest('GET', "/games/{$this->gameId}");
    $this->assertStatusCode(200);
    $this->assertJsonResponse();

    $status = $this->lastJson['status'] ?? null;
    $winnerId = $this->lastJson['winnerPlayerId']
        ?? $this->lastJson['winnerId']
        ?? $this->lastJson['winner']['id']
        ?? null;

    if ($status !== 'finished') {
        throw new RuntimeException("Jocul nu este finished. Status primit: " . json_encode($status));
    }

    if ((int)$winnerId !== $this->hostPlayerId) {
        throw new RuntimeException(
            "Castigator gresit. Asteptat: {$this->hostPlayerId}, primit: " . json_encode($winnerId)
        );
    }
}
}


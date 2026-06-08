param(
    [string]$BaseUrl = "http://localhost:8080"
)

$ErrorActionPreference = "Stop"

function Step($Name, $ScriptBlock) {
    Write-Host ""
    Write-Host "== $Name ==" -ForegroundColor Cyan
    $result = & $ScriptBlock
    if ($null -ne $result) {
        $result | ConvertTo-Json -Depth 12
    }
    return $result
}

function JsonBody($Hashtable) {
    return ($Hashtable | ConvertTo-Json -Depth 12)
}

$createdGame = Step "1. Creeaza joc nou" {
    Invoke-RestMethod `
        -Uri "$BaseUrl/games" `
        -Method POST `
        -ContentType "application/json" `
        -Body (JsonBody @{ hostName = "DemoHost"; maxPlayers = 2 })
}

$gameId = [int]$createdGame.id
$hostId = [int]$createdGame.players[0].id

$joinedPlayer = Step "2. Adauga al doilea jucator" {
    Invoke-RestMethod `
        -Uri "$BaseUrl/games/$gameId/players" `
        -Method POST `
        -ContentType "application/json" `
        -Body (JsonBody @{ name = "DemoPlayer" })
}

$playerId = [int]$joinedPlayer.id

$gameAfterJoin = Step "3. Citeste jocul cu jucatori si currentPlayerId" {
    Invoke-RestMethod -Uri "$BaseUrl/games/$gameId" -Method GET
}

$currentPlayerId = [int]$gameAfterJoin.currentPlayerId

$turn = Step "4. Ruleaza tura pentru jucatorul curent" {
    Invoke-RestMethod `
        -Uri "$BaseUrl/games/$gameId/turns" `
        -Method POST `
        -ContentType "application/json" `
        -Body (JsonBody @{ playerId = $currentPlayerId })
}

$turnId = [int]$turn.turnId
$action = if ($turn.availableActions -contains "buy_property") { "buy_property" } else { "end_turn" }

$actionResult = Step "5. Executa actiune disponibila: $action" {
    Invoke-RestMethod `
        -Uri "$BaseUrl/games/$gameId/turns/$turnId/actions" `
        -Method POST `
        -ContentType "application/json" `
        -Body (JsonBody @{ actionType = $action })
}

Step "6. GET actiunile turei" {
    Invoke-RestMethod -Uri "$BaseUrl/games/$gameId/turns/$turnId/actions" -Method GET
}

Step "7. GET proprietatile jocului" {
    Invoke-RestMethod -Uri "$BaseUrl/games/$gameId/properties" -Method GET
}

Step "8. GET detalii jucator nested" {
    Invoke-RestMethod -Uri "$BaseUrl/games/$gameId/players/$currentPlayerId" -Method GET
}

Step "9. GET lista jocurilor existente" {
    Invoke-RestMethod -Uri "$BaseUrl/games" -Method GET
}

Write-Host ""
Write-Host "Scenariu finalizat. ID-uri transferate automat: gameId=$gameId, hostId=$hostId, playerId=$playerId, currentPlayerId=$currentPlayerId, turnId=$turnId." -ForegroundColor Green

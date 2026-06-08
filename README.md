# Europolis API — PHP / Slim Framework

## Cerințe
- PHP >= 8.1
- Composer

## Instalare

```bash
composer install
```

## Pornire server local

```bash
composer start
# sau direct:
php -S localhost:8080 -t public
```

API-ul va fi disponibil la `http://localhost:8080`.

---

## Structura proiectului

Structura oglindește modelul problemei și use-case-urile din documentația Etapei 1.

```
europolis/
├── public/
│   └── index.php                        # Entry point — toate cererile trec prin aici
│
├── src/
│   ├── Routes/                          # Cap. 6 — Specificația API
│   │   ├── games.php                    # POST /games, GET /games/{id}, PATCH /games/{id}
│   │   ├── players.php                  # POST & GET /games/{id}/players, PATCH /players/{id}
│   │   ├── turns.php                    # POST & GET turns, POST actions
│   │   └── trades.php                   # POST & PATCH trades
│   │
│   ├── Models/                          # Cap. 3 — Modelul problemei (entități principale)
│   │   ├── Game.php                     # Sesiune de joc (waiting | active | finished)
│   │   ├── Player.php                   # Jucător (active | bankrupt)
│   │   ├── Board.php                    # Tabla de joc (40 de căsuțe)
│   │   ├── Tile.php                     # Căsuță (property | tax | chance | jail | ...)
│   │   ├── Property.php                 # Proprietate cumpărabilă (cu grup de culoare)
│   │   ├── Building.php                 # Casă sau hotel (max 4 case / 1 hotel)
│   │   ├── Trade.php                    # Schimb între jucători (pending | accepted | rejected)
│   │   ├── Card.php                     # Carte de șansă / comunitate
│   │   ├── Turn.php                     # Tura unui jucător (zaruri + acțiuni)
│   │   └── Bank.php                     # Singleton per joc (stoc clădiri, tranzacții)
│   │
│   ├── Services/                        # Cap. 4 — Use-Cases (logica de business)
│   │   ├── GameService.php              # UC1 Creare joc, UC3 Vizualizare, UC9 Finalizare
│   │   ├── TurnService.php              # UC4 Inițiere tură, UC5 Executare acțiune
│   │   ├── ActionService.php            # UC5 detaliat: buy, rent, tax, build, mortgage, jail
│   │   ├── TradeService.php             # UC6 Propunere trade, UC7 Răspuns trade
│   │   └── BoardService.php             # Inițializare tablă, rezolvare căsuțe, efecte cărți
│   │
│   ├── Repositories/                    # Acces la baza de date (PDO — de implementat)
│   │   ├── GameRepository.php
│   │   ├── PlayerRepository.php
│   │   ├── TurnRepository.php
│   │   ├── PropertyRepository.php
│   │   ├── TradeRepository.php
│   │   └── BuildingRepository.php
│   │
│   └── Middleware/                      # Cap. 5 — Reguli de business (validare centralizată)
│       ├── JsonResponseMiddleware.php   # Content-Type: application/json pe toate răspunsurile
│       ├── PlayerTurnMiddleware.php     # 403 dacă nu este tura jucătorului
│       └── GameStateMiddleware.php      # 404 / 409 dacă jocul nu există sau nu e activ
│
├── docs/
│   └── openapi.yaml                     # Specificația API (Swagger / OpenAPI 3.0)
│
├── composer.json
└── README.md
```

---

## Endpoint-uri implementate

| Metodă | Rută | UC | Descriere |
|--------|------|----|-----------|
| `POST` | `/games` | UC1 | Creează o sesiune nouă |
| `GET` | `/games/{gameId}` | UC3 | Starea curentă a jocului |
| `PATCH` | `/games/{gameId}` | UC1 | Pornire joc (status: active) |
| `POST` | `/games/{gameId}/players` | UC2 | Adaugă un jucător |
| `GET` | `/games/{gameId}/players` | UC3 | Lista jucătorilor |
| `PATCH` | `/players/{playerId}` | UC8 | Actualizează statusul (bankrupt) |
| `POST` | `/games/{gameId}/turns` | UC4 | Inițiază tura și aruncă zarurile |
| `GET` | `/games/{gameId}/turns/{turnId}` | UC4 | Detalii tură |
| `POST` | `/games/{gameId}/turns/{turnId}/actions` | UC5 | Execută o acțiune |
| `POST` | `/games/{gameId}/trades` | UC6 | Creează o propunere de schimb |
| `PATCH` | `/games/{gameId}/trades/{tradeId}` | UC7 | Acceptă / respinge schimbul |

---

## Coduri de răspuns (Cap. 7)

| Cod | Semnificație |
|-----|--------------|
| `200 OK` | Operația a fost procesată cu succes |
| `201 Created` | O resursă nouă a fost creată |
| `400 Bad Request` | Date invalide sau incomplete |
| `403 Forbidden` | Acțiunea nu este permisă în contextul curent |
| `404 Not Found` | Resursa solicitată nu există |
| `409 Conflict` | Există un conflict de stare |
| `422 Unprocessable Entity` | Regulă de business încălcată |

### Format erori standardizat

```json
{
  "error": "NOT_YOUR_TURN",
  "message": "Jucătorul nu poate acționa în afara turei sale."
}
```

---

## Reguli de business validate de server (Cap. 5)

| Regulă | Cod |
|--------|-----|
| Un jucător poate acționa doar în tura sa | `403` |
| Nu poți cumpăra o proprietate aparținând altui jucător | `409` |
| Nu poți construi fără a deține toate proprietățile din grup | `422` |
| Maximum 4 case sau 1 hotel per proprietate | `422` |
| Zarurile se aruncă o singură dată per tură (excepție: dublu) | `409` |
| Nu poți ipoteca o proprietate cu clădiri pe ea | `422` |
| Condiții de eliberare din închisoare: plată, carte specială sau dublu | `422` |
| Schimburile sunt valide doar pentru bunuri proprii | `403` |

---

## Pași următori

1. **Baza de date** — configurează PDO + MySQL și implementează metodele din `Repositories/`
2. **Conectare servicii la routes** — înlocuiește datele simulate cu apeluri către `Services/`
3. **Middleware** — activează `PlayerTurnMiddleware` și `GameStateMiddleware` pe rutele relevante
4. **BoardService::initializeBoard()** — configurează cele 40 de căsuțe ale tablei Europolis
5. **Testare** — PHPUnit pentru unit tests pe `Services/` și `Models/`

---

## Automated Testing

The project uses Behat and Gherkin for automated API testing.

Implemented scenarios:
- Create game
- Add player
- Execute turn
- Create trade

Run tests:

```bash
vendor/bin/behat
```

Expected output:

```text
4 scenarios (4 passed)
13 steps (13 passed)
```

## Regula de castig si faliment

Jocul nu se termina atunci cand un jucator ajunge la soldul 0. Un jucator este declarat `bankrupt` doar in momentul in care trebuie sa plateasca o obligatie de joc, de exemplu chirie, taxa sau taxa de inchisoare, nu are suficienti bani si nu mai detine nicio proprietate care poate fi vanduta catre banca.

Daca jucatorul nu are suficienti bani, dar mai are proprietati, serverul nu il elimina din joc. API-ul returneaza un raspuns care indica faptul ca jucatorul trebuie sa vanda active pentru a putea plati. Proprietatile vandute catre banca revin in circuitul jocului si pot fi cumparate ulterior.

Dupa declararea unui jucator ca `bankrupt`, serverul verifica automat numarul jucatorilor activi. Daca ramane un singur jucator activ, jocul trece in statusul `finished`, iar raspunsul API include `winnerPlayerId` si datele castigatorului.

## Testare automata pentru faliment

Testele Behat/Gherkin verifica inclusiv regula de castig:

- un jucator cu sold 0 ramane activ si jocul nu se termina automat;
- un jucator fara bani si fara proprietati poate fi declarat `bankrupt`;
- cand ramane un singur jucator activ, jocul devine `finished`, iar primul jucator este identificat ca invingator.

Rulare teste:

```bash
vendor/bin/behat
```

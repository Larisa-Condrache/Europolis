API Description — Joc Europolis/Monopoly
1. Scopul API-ului

API-ul aplicației Europolis are rolul de a expune logica jocului sub forma unui set de endpoint-uri REST, care permit clienților web sau mobili să creeze jocuri, să alăture jucători, să gestioneze turele de joc și să execute acțiuni specifice regulilor Monopoly/Europolis. Prin intermediul acestui API, serverul devine autoritatea centrală asupra regulilor jocului, validând toate acțiunile jucătorilor și menținând starea consistentă a sesiunii de joc.

Separarea dintre client și server permite ca aplicația să poată fi accesată de pe mai multe dispozitive simultan și facilitează extinderea ulterioară a sistemului cu funcționalități suplimentare, precum modul spectator, salvarea jocurilor sau integrarea unor jucători automatizați.

2. Principii generale de proiectare

API-ul este proiectat în stil REST și utilizează metodele HTTP standard pentru manipularea resurselor. Comunicarea dintre client și server se realizează prin mesaje în format JSON, iar serverul este responsabil pentru validarea tuturor regulilor de joc și pentru menținerea unei stări consistente a aplicației.

Principalele principii urmărite în proiectarea API-ului sunt:

separarea clară a resurselor principale ale sistemului;
utilizarea consecventă a metodelor HTTP (GET, POST, PATCH);
transmiterea datelor în format JSON;
utilizarea codurilor de răspuns HTTP pentru semnalarea succesului sau a erorilor;
validarea server-side a tuturor acțiunilor jucătorilor;
posibilitatea extinderii ulterioare fără modificarea structurii de bază a API-ului.
3. Resurse API identificate

În urma analizei problemei, au fost identificate următoarele resurse principale ale API-ului:

Resursă	Rol
games	gestionează sesiunile de joc
players	gestionează jucătorii participanți la un joc
turns	gestionează desfășurarea turelor de joc
actions	gestionează acțiunile efectuate într-o tură
properties	gestionează informațiile despre proprietăți și proprietarii acestora
trades	gestionează schimburile dintre jucători

Resursa games reprezintă nucleul API-ului, deoarece toate celelalte resurse sunt definite în raport cu o sesiune activă de joc. Resursa players este asociată unui joc, iar resursele turns, actions și trades depind de starea curentă a acestuia.

4. Endpoint-uri principale

Endpoint-urile reprezintă punctele de acces prin care clientul interacționează cu serverul. În tabelul următor sunt prezentate principalele endpoint-uri ale API-ului:

Endpoint	Metodă	Descriere
/games	POST	creează un joc nou
/games/{gameId}	GET	returnează starea curentă a jocului
/games/{gameId}	PATCH	modifică starea jocului
/games/{gameId}/players	POST	adaugă un jucător într-un joc
/games/{gameId}/players	GET	returnează lista jucătorilor din joc
/players/{playerId}	PATCH	actualizează statusul unui jucător
/games/{gameId}/turns	POST	inițiază o tură și aruncă zarurile
/games/{gameId}/turns/{turnId}	GET	returnează detaliile unei ture
/games/{gameId}/turns/{turnId}/actions	POST	execută o acțiune de joc
/games/{gameId}/trades	POST	creează o propunere de schimb
/games/{gameId}/trades/{tradeId}	PATCH	acceptă sau respinge o propunere de schimb
5. Descrierea operațiilor principale
5.1 Creare joc

Endpoint: POST /games
Scop: creează o nouă sesiune de joc.
Actor principal: host / jucător creator.

Cererea conține informațiile minime necesare pentru inițializarea unui joc nou, cum ar fi numele jucătorului care creează sesiunea și numărul maxim de participanți. La crearea jocului, serverul inițializează starea jocului cu statusul waiting, adaugă primul jucător și pregătește structura internă a tablei și a resurselor asociate.

Exemplu request:

{
  "hostName": "Ana",
  "maxPlayers": 4
}

Exemplu response:

{
  "id": 1,
  "status": "waiting",
  "players": [
    {
      "id": 1,
      "name": "Ana",
      "balance": 1500,
      "position": 0,
      "status": "active"
    }
  ]
}
5.2 Alăturare jucător

Endpoint: POST /games/{gameId}/players
Scop: adaugă un jucător nou într-o sesiune existentă.
Actor principal: jucător.

Operația permite înscrierea unui nou participant într-un joc aflat în starea waiting. Serverul validează existența jocului, numărul maxim de jucători și faptul că jocul nu a început încă.

Exemplu request:

{
  "name": "Mihai"
}

Exemplu response:

{
  "id": 2,
  "name": "Mihai",
  "balance": 1500,
  "position": 0,
  "status": "active"
}
5.3 Obținere stare joc

Endpoint: GET /games/{gameId}
Scop: returnează starea curentă a jocului.
Actor principal: orice jucător participant.

Această operație permite clientului să obțină o imagine asupra stării curente a sesiunii: statusul jocului, jucătorii activi, jucătorul curent și alte date necesare pentru randarea interfeței.

Exemplu response:

{
  "id": 1,
  "status": "active",
  "currentPlayerId": 2,
  "players": [
    {
      "id": 1,
      "name": "Ana",
      "balance": 1380,
      "position": 7,
      "status": "active"
    },
    {
      "id": 2,
      "name": "Mihai",
      "balance": 1500,
      "position": 0,
      "status": "active"
    }
  ]
}
5.4 Inițiere tură

Endpoint: POST /games/{gameId}/turns
Scop: începe tura jucătorului curent și aruncă zarurile.
Actor principal: jucătorul aflat în tură.

Serverul validează faptul că cererea este trimisă de jucătorul curent și că zarurile nu au fost deja aruncate pentru tura activă. În urma operației, serverul calculează noua poziție, identifică tipul căsuței atinse și returnează acțiunile disponibile.

Exemplu request:

{
  "playerId": 2
}

Exemplu response:

{
  "turnId": 10,
  "playerId": 2,
  "dice": [3, 5],
  "fromPosition": 7,
  "toPosition": 15,
  "landedTile": {
    "index": 15,
    "name": "Brașov",
    "type": "property"
  },
  "availableActions": [
    "buy_property",
    "end_turn"
  ]
}
5.5 Executare acțiune

Endpoint: POST /games/{gameId}/turns/{turnId}/actions
Scop: execută o acțiune disponibilă în cadrul turei curente.
Actor principal: jucătorul aflat în tură.

Prin acest endpoint sunt executate acțiuni precum cumpărarea unei proprietăți, plata chiriei, încheierea turei sau utilizarea unei cărți speciale. Serverul validează disponibilitatea acțiunii și regulile specifice asociate.

Exemplu request:

{
  "actionType": "buy_property"
}

Exemplu response:

{
  "message": "Action executed successfully",
  "gameStatus": "active",
  "player": {
    "id": 2,
    "balance": 1320
  }
}
5.6 Propunere trade

Endpoint: POST /games/{gameId}/trades
Scop: creează o propunere de schimb între doi jucători.
Actor principal: jucător.

Această operație permite inițierea unei negocieri care poate conține bani, proprietăți sau ambele. Serverul verifică existența celor doi jucători și dreptul de proprietate asupra bunurilor oferite în schimb.

Exemplu request:

{
  "fromPlayerId": 1,
  "toPlayerId": 2,
  "offeredMoney": 200,
  "requestedPropertyIds": [14]
}

Exemplu response:

{
  "tradeId": 3,
  "status": "pending"
}
6. Modele de mesaje

Pentru comunicarea dintre client și server, API-ul utilizează mesaje JSON standardizate. Principalele tipuri de obiecte sunt următoarele:

Game
{
  "id": 1,
  "status": "active",
  "currentPlayerId": 2
}
Player
{
  "id": 2,
  "name": "Mihai",
  "balance": 1500,
  "position": 0,
  "status": "active"
}
Turn
{
  "id": 10,
  "playerId": 2,
  "dice": [3, 5],
  "fromPosition": 7,
  "toPosition": 15,
  "availableActions": ["buy_property", "end_turn"]
}
ActionRequest
{
  "actionType": "buy_property"
}
ErrorResponse
{
  "error": "NOT_YOUR_TURN",
  "message": "Jucătorul nu poate acționa în afara turei sale."
}
7. Coduri de răspuns

API-ul utilizează coduri HTTP standard pentru a semnaliza rezultatul operațiilor efectuate:

Cod	Semnificație
200 OK	operația a fost procesată cu succes
201 Created	o resursă nouă a fost creată
400 Bad Request	cererea conține date invalide sau incomplete
403 Forbidden	acțiunea nu este permisă în contextul curent
404 Not Found	resursa solicitată nu există
409 Conflict	există un conflict de stare
422 Unprocessable Entity	regula de business nu permite executarea operației

Codurile de răspuns sunt utilizate pentru a diferenția erorile de sintaxă, erorile de permisiune și încălcările regulilor specifice jocului.

8. Reguli de business asociate API-ului

Serverul validează centralizat regulile jocului înainte de a executa orice operație expusă prin API. Cele mai importante reguli sunt:

doar jucătorul curent poate iniția o tură sau executa acțiuni în cadrul acesteia;
un joc aflat în starea active nu mai poate accepta jucători noi;
o proprietate deja deținută nu poate fi cumpărată din nou;
o acțiune poate fi executată doar dacă se află în lista de acțiuni disponibile pentru tura curentă;
construirea de case sau hoteluri este permisă doar dacă jucătorul deține monopol complet pe grupul respectiv;
dacă un jucător nu poate achita o obligație financiară, statusul său poate deveni bankrupt;
schimburile între jucători pot fi validate doar dacă bunurile oferite aparțin inițiatorului.

Aceste reguli sunt reflectate în răspunsurile API prin coduri HTTP adecvate și mesaje explicite de eroare.

9. Observații privind extinderea ulterioară

Structura propusă pentru API permite extinderea ulterioară a aplicației prin adăugarea unor funcționalități precum:

autentificare și conturi persistente de utilizator;
modul spectator;
notificări în timp real prin WebSocket;
salvarea și reluarea jocurilor;
suport pentru variante alternative de tablă;
integrarea unor jucători automatizați de tip AI.

Aceste extinderi pot fi adăugate fără modificarea fundamentală a resurselor deja identificate, ceea ce confirmă flexibilitatea modelului API propus.

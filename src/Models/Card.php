<?php

declare(strict_types=1);

namespace Europolis\Models;

/**
 * Card — o carte de șansă sau comunitate.
 *
 * Efecte posibile:
 *   move         – mișcare la o anumită poziție
 *   move_relative – mișcare cu N pași
 *   pay          – jucătorul plătește o sumă
 *   collect      – jucătorul colectează o sumă
 *   collect_from_players – fiecare jucător plătește jucătorului curent
 *   pay_to_players       – jucătorul curent plătește fiecărui jucător
 *   get_out_of_jail      – carte de ieșire din închisoare
 *   go_to_jail   – trimitere la închisoare
 *
 * (Cap. 3 — Modelul problemei, Etapa 1)
 */
class Card
{
    public int    $id;
    public string $deck;        // 'chance' | 'community'
    public string $description; // textul afișat jucătorului
    public string $effectType;  // tipul efectului (vezi lista de mai sus)
    public array  $effectData;  // parametrii efectului (ex: ['position' => 0], ['amount' => 50])

    // TODO: BoardService::applyCardEffect(Card $card, Player $player, Game $game)
}

<?php

declare(strict_types=1);

namespace Europolis\Models;

/**
 * Tile — o căsuță a tablei de joc.
 *
 * Tipuri posibile:
 *   property   – proprietate cumpărabilă
 *   tax        – taxă (ex: impozit de lux, impozit pe venit)
 *   chance     – carte de șansă
 *   community  – carte de comunitate
 *   jail       – închisoare (vizitator sau deținut)
 *   go_to_jail – trimitere la închisoare
 *   start      – căsuța START
 *   free       – parcare liberă
 *   station    – gară
 *   utility    – utilitate (apă, electricitate)
 *
 * (Cap. 3 — Modelul problemei, Etapa 1)
 */
class Tile
{
    public int    $index;   // 0–39
    public string $name;
    public string $type;    // vezi tipurile de mai sus

    // TODO: referință la Property dacă type === 'property'
}

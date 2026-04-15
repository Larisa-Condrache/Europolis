<?php

declare(strict_types=1);

namespace Europolis\Models;

/**
 * Building — casă sau hotel construit pe o proprietate.
 *
 * (Cap. 3 — Modelul problemei, Etapa 1)
 */
class Building
{
    public int    $id;
    public int    $propertyId;
    public string $type;    // 'house' | 'hotel'

    // Reguli de business (Cap. 5):
    // - Stocul de clădiri este gestionat de Bank (singleton per joc)
    // - Max 32 case și 12 hoteluri per joc (stoc limitat)
    // - Construcția trebuie să fie uniformă în cadrul grupului de culoare
}

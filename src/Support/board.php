<?php

declare(strict_types=1);

function europolis_tiles(): array
{
    return [
        0 => ['index' => 0, 'type' => 'go', 'name' => 'START', 'price' => null, 'color' => null],
        1 => ['index' => 1, 'type' => 'property', 'name' => 'Timisoara', 'price' => 60, 'color' => 'purple'],
        2 => ['index' => 2, 'type' => 'community', 'name' => 'Fond Comunitar', 'price' => null, 'color' => null],
        3 => ['index' => 3, 'type' => 'property', 'name' => 'Arad', 'price' => 60, 'color' => 'purple'],
        4 => ['index' => 4, 'type' => 'tax', 'name' => 'Taxa Venit', 'price' => 200, 'color' => null],
        5 => ['index' => 5, 'type' => 'station', 'name' => 'Gara Nord', 'price' => 200, 'color' => 'gray'],
        6 => ['index' => 6, 'type' => 'property', 'name' => 'Oradea', 'price' => 100, 'color' => 'teal'],
        7 => ['index' => 7, 'type' => 'chance', 'name' => 'Sansa', 'price' => null, 'color' => null],
        8 => ['index' => 8, 'type' => 'property', 'name' => 'Cluj-Napoca', 'price' => 100, 'color' => 'teal'],
        9 => ['index' => 9, 'type' => 'property', 'name' => 'Deva', 'price' => 120, 'color' => 'teal'],
        10 => ['index' => 10, 'type' => 'jail', 'name' => 'Inchisoare', 'price' => null, 'color' => null],
        11 => ['index' => 11, 'type' => 'property', 'name' => 'Sibiu', 'price' => 140, 'color' => 'orange'],
        12 => ['index' => 12, 'type' => 'utility', 'name' => 'Utilitate E', 'price' => 150, 'color' => 'gray'],
        13 => ['index' => 13, 'type' => 'property', 'name' => 'Sfantu Gheorghe', 'price' => 140, 'color' => 'orange'],
        14 => ['index' => 14, 'type' => 'property', 'name' => 'Focsani', 'price' => 160, 'color' => 'orange'],
        15 => ['index' => 15, 'type' => 'station', 'name' => 'Gara Sud', 'price' => 200, 'color' => 'gray'],
        16 => ['index' => 16, 'type' => 'property', 'name' => 'Brasov', 'price' => 180, 'color' => 'red'],
        17 => ['index' => 17, 'type' => 'community', 'name' => 'Fond Comunitar', 'price' => null, 'color' => null],
        18 => ['index' => 18, 'type' => 'property', 'name' => 'Ploiesti', 'price' => 180, 'color' => 'red'],
        19 => ['index' => 19, 'type' => 'property', 'name' => 'Pitesti', 'price' => 200, 'color' => 'red'],
        20 => ['index' => 20, 'type' => 'parking', 'name' => 'Parcare Libera', 'price' => null, 'color' => null],
        21 => ['index' => 21, 'type' => 'property', 'name' => 'Craiova', 'price' => 220, 'color' => 'yellow'],
        22 => ['index' => 22, 'type' => 'chance', 'name' => 'Sansa', 'price' => null, 'color' => null],
        23 => ['index' => 23, 'type' => 'property', 'name' => 'Targu Mures', 'price' => 220, 'color' => 'yellow'],
        24 => ['index' => 24, 'type' => 'property', 'name' => 'Constanta', 'price' => 240, 'color' => 'yellow'],
        25 => ['index' => 25, 'type' => 'station', 'name' => 'Gara Est', 'price' => 200, 'color' => 'gray'],
        26 => ['index' => 26, 'type' => 'property', 'name' => 'Galati', 'price' => 260, 'color' => 'green'],
        27 => ['index' => 27, 'type' => 'community', 'name' => 'Fond Comunitar', 'price' => null, 'color' => null],
        28 => ['index' => 28, 'type' => 'property', 'name' => 'Braila', 'price' => 260, 'color' => 'green'],
        29 => ['index' => 29, 'type' => 'utility', 'name' => 'Utilitate A', 'price' => 150, 'color' => 'gray'],
        30 => ['index' => 30, 'type' => 'gotojail', 'name' => 'Mergi la Puscarie', 'price' => null, 'color' => null],
        31 => ['index' => 31, 'type' => 'property', 'name' => 'Iasi', 'price' => 300, 'color' => 'blue'],
        32 => ['index' => 32, 'type' => 'property', 'name' => 'Iasi Nord', 'price' => 300, 'color' => 'blue'],
        33 => ['index' => 33, 'type' => 'community', 'name' => 'Fond Comunitar', 'price' => null, 'color' => null],
        34 => ['index' => 34, 'type' => 'property', 'name' => 'Suceava', 'price' => 320, 'color' => 'blue'],
        35 => ['index' => 35, 'type' => 'station', 'name' => 'Gara Vest', 'price' => 200, 'color' => 'gray'],
        36 => ['index' => 36, 'type' => 'chance', 'name' => 'Sansa', 'price' => null, 'color' => null],
        37 => ['index' => 37, 'type' => 'property', 'name' => 'Bucuresti', 'price' => 350, 'color' => 'pink'],
        38 => ['index' => 38, 'type' => 'tax', 'name' => 'Taxa Lux', 'price' => 100, 'color' => null],
        39 => ['index' => 39, 'type' => 'property', 'name' => 'Parlament', 'price' => 400, 'color' => 'pink'],
    ];
}

function europolis_get_tile(int $index): array
{
    $tiles = europolis_tiles();
    return $tiles[$index] ?? ['index' => $index, 'type' => 'unknown', 'name' => 'Necunoscut', 'price' => null, 'color' => null];
}


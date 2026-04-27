<?php

declare(strict_types=1);

namespace Europolis;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Singleton PDO – returneaza aceeasi instanta pe toata durata requestului.
 * Utilizare: $pdo = Database::getConnection();
 */
class Database
{
    private static ?PDO $instance = null;

    // Impiedica instantierea directa
    private function __construct() {}

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = 'mysql:host=127.0.0.1;dbname=europolis;charset=utf8mb4';

            try {
                self::$instance = new PDO($dsn, 'root', '', [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                throw new RuntimeException(
                    'Conexiunea la baza de date a esuat: ' . $e->getMessage()
                );
            }
        }

        return self::$instance;
    }
}

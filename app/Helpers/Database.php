<?php

namespace App\Helpers;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Simple PDO singleton wrapper.
 * Two payroll PCs both point this at the SAME MySQL host
 * (either a shared server on the network, or one PC acts as
 * the DB host) so data never forks between machines.
 */
class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $db   = $_ENV['DB_DATABASE'] ?? '';
            $user = $_ENV['DB_USERNAME'] ?? '';
            $pass = $_ENV['DB_PASSWORD'] ?? '';

            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                throw new RuntimeException('Database connection failed: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }
}
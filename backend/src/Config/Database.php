<?php

declare(strict_types=1);

namespace Stolpersteine\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $config = Config::get('db');

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $config['host'],
                $config['port'] ?? 3306,
                $config['name']
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                $pdo = new PDO($dsn, $config['user'], $config['password'], $options);

                // UTC für alle Zeitfelder erzwingen
                $pdo->exec("SET time_zone = '+00:00'");
                $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

                self::$instance = $pdo;
            } catch (PDOException $e) {
                // Keine DB-Details nach außen geben
                throw new \RuntimeException('Datenbankverbindung fehlgeschlagen.', 0, $e);
            }
        }

        return self::$instance;
    }

    // Nur für Tests: Verbindung zurücksetzen
    public static function reset(): void
    {
        self::$instance = null;
    }
}

<?php
declare(strict_types=1);
namespace TestLab;
use PDO;

final class Database
{
    public static function connect(array $config): PDO
    {
        if (!extension_loaded('pdo_mysql')) { throw new \RuntimeException('PHP extension pdo_mysql is required.'); }
        $db = $config['db'];
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], (int)$db['port'], $db['database']);
        $pdo = new PDO($dsn, $db['username'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
        $pdo->exec("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");
        $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        return $pdo;
    }
}

<?php

declare(strict_types=1);

namespace App\Core;

use App\Config;
use PDO;

class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $relativePath = Config::get('DATABASE_PATH', 'database/data.sqlite');
        $path = dirname(__DIR__, 2) . '/' . ltrim($relativePath, '/');

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');

        $schema = file_get_contents(dirname(__DIR__, 2) . '/schema.sql');
        $pdo->exec($schema);

        self::$connection = $pdo;
        return $pdo;
    }
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

/**
 * Returns one shared PDO connection for the current request.
 * Native prepared statements are enabled to keep SQL values separate from SQL code.
 */
function db(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    try {
        $connection = new PDO($dsn, DB_USER, DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $exception) {
        error_log('SecureScope database connection failed: ' . $exception->getMessage());
        throw new RuntimeException('The application could not connect to its database.');
    }

    return $connection;
}

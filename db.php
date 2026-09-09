<?php

declare(strict_types=1);

/**
 * MediCare Database Connection
 *
 * Local XAMPP:
 *   Host:     127.0.0.1
 *   Database: medicare
 *   User:     root
 *   Password: empty
 *
 * Railway:
 *   Connection details are loaded from environment variables.
 */

$isRailway = getenv('RAILWAY_ENVIRONMENT_NAME') !== false;

if ($isRailway) {

    /*
     * Railway MySQL connection
     */
    $host = getenv('MYSQLHOST') ?: '127.0.0.1';
    $port = getenv('MYSQLPORT') ?: '3306';
    $dbname = getenv('MYSQLDATABASE') ?: 'medicare';
    $username = getenv('MYSQLUSER') ?: 'root';
    $password = getenv('MYSQLPASSWORD') ?: '';

} else {

    /*
     * Local XAMPP connection
     */
    $host = '127.0.0.1';
    $port = '3306';
    $dbname = 'medicare';
    $username = 'root';
    $password = '';

}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $host,
    $port,
    $dbname
);

try {

    $pdo = new PDO(
        $dsn,
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_TIMEOUT => 10,
        ]
    );

} catch (PDOException $e) {

    error_log(
        'MediCare database connection failed: ' .
        $e->getMessage()
    );

    http_response_code(503);

    exit(
        'The healthcare service is temporarily unavailable. ' .
        'Please try again later.'
    );
}
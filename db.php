<?php

declare(strict_types=1);

/**
 * MediCare Database Connection
 *
 * Railway:
 *   DB_HOST
 *   DB_PORT
 *   DB_NAME
 *   DB_USER
 *   DB_PASS
 *
 * Local XAMPP fallback:
 *   Host:     127.0.0.1
 *   Database: medicare
 *   User:     root
 *   Password: empty
 */

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'medicare';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';

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

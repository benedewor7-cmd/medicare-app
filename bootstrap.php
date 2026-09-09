<?php
declare(strict_types=1);

/**
 * MediCare application bootstrap.
 * Centralises security, sessions, authentication, CSRF and output escaping.
 */

const APP_NAME = 'MediCare';

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

session_name('medicare_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/db.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $location): never
{
    header('Location: ' . $location, true, 302);
    exit;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['role']);
}

function require_auth(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

function require_role(string ...$roles): void
{
    require_auth();

    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function current_user_id(): int
{
    return (int) $_SESSION['user_id'];
}

function current_role(): string
{
    return (string) $_SESSION['role'];
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function verify_csrf(?string $token): void
{
    if (!is_string($token) || $token === '' || empty($_SESSION['_csrf'])
        || !hash_equals($_SESSION['_csrf'], $token)) {
        http_response_code(419);
        exit('Security token expired. Please go back and try again.');
    }
}

function post_string(string $key, int $maxLength = 10000): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    $value = trim($value);
    return mb_substr($value, 0, $maxLength);
}

function get_int(string $key): ?int
{
    $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);
    return ($value === false || $value === null || $value < 1) ? null : $value;
}

function safe_date(string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    return ($date && $date->format('Y-m-d') === $value) ? $date : null;
}

function safe_time(string $value): ?DateTimeImmutable
{
    $time = DateTimeImmutable::createFromFormat('H:i', $value);
    return ($time && $time->format('H:i') === $value) ? $time : null;
}

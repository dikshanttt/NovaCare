<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$requiredSettings = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];
$missingSettings = [];

foreach ($requiredSettings as $setting) {
    if (!isset($_ENV[$setting]) || trim((string) $_ENV[$setting]) === '') {
        $missingSettings[] = $setting;
    }
}

if ($missingSettings !== []) {
    error_log('NovaCare configuration is missing required settings: ' . implode(', ', $missingSettings));
    http_response_code(500);
    exit('Application configuration is incomplete. Please contact the administrator.');
}

if (!ctype_digit((string) $_ENV['DB_PORT'])) {
    error_log('NovaCare configuration contains an invalid DB_PORT value.');
    http_response_code(500);
    exit('Application configuration is invalid. Please contact the administrator.');
}

define('DB_HOST', (string) $_ENV['DB_HOST']);
define('DB_NAME', (string) $_ENV['DB_NAME']);
define('DB_PORT', (string) $_ENV['DB_PORT']);
define('DB_USER', (string) $_ENV['DB_USER']);
define('DB_PASS', (string) $_ENV['DB_PASSWORD']);

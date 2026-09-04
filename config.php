<?php

function loadEnvFile($path)
{
    if (!file_exists($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        list($name, $value) = array_map('trim', explode('=', $line, 2));
        if ($name !== '' && getenv($name) === false) {
            putenv("$name=$value");
        }
    }
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

loadEnvFile(__DIR__ . '/.env');

$servername = getenv('DB_HOST') ?: 'localhost';
$username = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$database = getenv('DB_NAME');

if (!$username || !$database) {
    die('Database configuration missing. Copy .env.example to .env and fill in your credentials.');
}

$conn = mysqli_connect($servername, $username, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

<?php
function loadEnv($path)
{
    if (!file_exists($path)) {
        throw new Exception("Environment file not found.");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Use explode with a fallback to avoid undefined offset errors
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            putenv(trim($parts[0]) . '=' . trim($parts[1]));
        }
    }
}

if (!getenv('DB_HOST')) {
    loadEnv(__DIR__ . '/../.env');
}

// Set up the DSN
$hostname = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';
$defaultSchema = getenv('DB_NAME') ?: 'copycat';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

$dsn = "mysql:host=$hostname;dbname=$defaultSchema;charset=$charset;port=$port";

$option = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
];

global $pdo;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    try {
        $pdo = new PDO($dsn, $username, $password, $option);
    } catch (PDOException $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}
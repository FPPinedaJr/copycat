<?php
declare(strict_types=1);

/**
 * Database Connection Setup
 * Loads environment variables and initializes a global PDO instance.
 */

if (!function_exists('loadEnv')) {
    /**
     * Minimalist .env loader
     */
    function loadEnv(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

// Load environment variables from the root or public folder
loadEnv(__DIR__ . '/../../.env');
loadEnv(__DIR__ . '/../.env');

// Optional: Include secondary config if it exists
if (file_exists(__DIR__ . '/config.php')) {
    include_once __DIR__ . '/config.php';
}

// Database configuration with fallback values
$dbConfig = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: '3306',
    'user' => getenv('DB_USER') ?: 'root',
    'pass' => getenv('DB_PASS') ?: '',
    'name' => getenv('DB_NAME') ?: 'copycat',
    'charset' => getenv('DB_CHARSET') ?: 'utf8mb4'
];

$dsn = sprintf(
    "mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'],
    $dbConfig['port'],
    $dbConfig['name'],
    $dbConfig['charset']
);

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    global $pdo;

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], $options);
    }
} catch (PDOException $e) {
    // In a real app, you should log this error properly
    error_log("Database Connection Failed: " . $e->getMessage());
    die("A database error occurred. Please try again later.");
}
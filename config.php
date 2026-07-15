<?php
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$isLocalEnv = $httpHost === '' || strpos($httpHost, 'localhost') !== false || strpos($httpHost, '127.0.0.1') !== false;

error_reporting(E_ALL);
ini_set('display_errors', $isLocalEnv ? '1' : '0');
ini_set('log_errors', '1');

if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'allinoneabroad';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$adminPassword = getenv('ADMIN_PASSWORD') ?: null;

function connectDb(): mysqli
{
    global $dbHost, $dbName, $dbUser, $dbPass;

    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($conn->connect_error) {
        throw new RuntimeException('Database connection failed: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

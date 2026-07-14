<?php
require_once __DIR__ . '/config.php';

try {
    $conn = new mysqli($dbHost, $dbUser, $dbPass);
    if ($conn->connect_error) {
        throw new RuntimeException('Could not connect to MySQL: ' . $conn->connect_error);
    }

    if (!$conn->select_db($dbName)) {
        if (!$conn->query("CREATE DATABASE IF NOT EXISTS `$dbName`")) {
            throw new RuntimeException('Could not create database: ' . $conn->error);
        }
        $conn->select_db($dbName);
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_name VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(30) NOT NULL,
            shipping_address TEXT NOT NULL,
            city VARCHAR(100) NOT NULL,
            state VARCHAR(100) NOT NULL,
            pincode VARCHAR(20) NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            items JSON NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    echo 'Database setup completed successfully.';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Setup failed: ' . $e->getMessage();
}

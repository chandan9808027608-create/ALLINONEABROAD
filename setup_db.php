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
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            category VARCHAR(20) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            original_price DECIMAL(10,2) NULL,
            stock INT NOT NULL DEFAULT 0,
            image VARCHAR(500) NOT NULL,
            description VARCHAR(255) NULL,
            badge VARCHAR(50) NULL,
            rating TINYINT NOT NULL DEFAULT 5,
            reviews INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS banner_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            message VARCHAR(200) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $bannerCount = $conn->query('SELECT COUNT(*) AS c FROM banner_messages')->fetch_assoc();
    if ((int)$bannerCount['c'] === 0) {
        $conn->query("
            INSERT INTO banner_messages (message, sort_order) VALUES
            ('🎉 Grand Opening — Up to 50% Off', 1),
            ('🚚 Free Delivery on Orders Above Rs. 799', 2),
            ('💳 eSewa • Khalti • FonePay • COD', 3)
        ");
    }

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

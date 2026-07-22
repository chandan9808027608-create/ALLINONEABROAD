<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "PHP version: " . phpversion() . "\n";

require_once __DIR__ . '/config.php';
echo "config.php loaded OK\n";

echo "imgUrl defined: " . (function_exists('imgUrl') ? 'YES' : 'NO') . "\n";
if (function_exists('imgUrl')) {
    echo "imgUrl test: " . imgUrl('images/products/Grelac1.png') . "\n";
}

$conn = connectDb();
echo "DB connected OK\n";

$result = $conn->query("SHOW COLUMNS FROM products LIKE 'piece_type'");
echo "piece_type column exists: " . ($result && $result->num_rows > 0 ? 'YES' : 'NO') . "\n";

$stmt = $conn->prepare('SELECT id, name, category, price, original_price, stock, image, images, colors, country_of_origin, piece_type, description, badge, rating, reviews FROM products WHERE id = ? LIMIT 1');
if (!$stmt) {
    echo "PREPARE FAILED: " . $conn->error . "\n";
} else {
    $id = 29;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    echo "Query executed. Result type: " . gettype($result) . "\n";
    if ($result) {
        $row = $result->fetch_assoc();
        echo "Row fetched: " . ($row ? 'YES' : 'NO (no matching id)') . "\n";
    }
}
echo "ALL CHECKS PASSED\n";

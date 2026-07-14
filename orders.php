<?php
require_once __DIR__ . '/config.php';

try {
    $conn = connectDb();
    $result = $conn->query('SELECT id, customer_name, email, phone, shipping_address, city, state, pincode, payment_method, items, total_amount, created_at FROM orders ORDER BY id DESC');
} catch (Throwable $e) {
    echo '<h1>Database unavailable</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Orders Dashboard — ALL IN ONE ABROAD</title>
  <style>
    body { font-family: Inter, Arial, sans-serif; margin: 24px; color: #111827; }
    table { width: 100%; border-collapse: collapse; margin-top: 16px; }
    th, td { border: 1px solid #e5e7eb; padding: 10px; text-align: left; vertical-align: top; font-size: 13px; }
    th { background: #f9fafb; }
    .empty { color: #6b7280; }
  </style>
</head>
<body>
  <h1>Orders Dashboard</h1>
  <p>Use this page to verify that checkout orders are being stored in the Hostinger database.</p>
  <?php if ($result && $result->num_rows > 0): ?>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Customer</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Address</th>
          <th>Payment</th>
          <th>Total</th>
          <th>Created</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td>#<?= (int)$row['id'] ?></td>
            <td><?= htmlspecialchars($row['customer_name']) ?></td>
            <td><?= htmlspecialchars($row['email']) ?></td>
            <td><?= htmlspecialchars($row['phone']) ?></td>
            <td><?= htmlspecialchars($row['shipping_address'] . ', ' . $row['city'] . ', ' . $row['state'] . ' - ' . $row['pincode']) ?></td>
            <td><?= htmlspecialchars($row['payment_method']) ?></td>
            <td>Rs. <?= number_format((float)$row['total_amount'], 2) ?></td>
            <td><?= htmlspecialchars($row['created_at']) ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="empty">No orders yet. Once a customer places an order, it will appear here.</p>
  <?php endif; ?>
</body>
</html>

<?php
require_once __DIR__ . '/config.php';

session_start();

function renderLoginForm(?string $error = null): void
{
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8"/>
      <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
      <title>Admin Login — ALL IN ONE ABROAD</title>
      <style>
        body { font-family: Inter, Arial, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f9fafb; }
        form { background: #fff; padding: 32px; border-radius: 12px; box-shadow: 0 8px 40px rgba(0,0,0,0.1); width: 100%; max-width: 320px; }
        h1 { font-size: 18px; margin: 0 0 16px; }
        input { width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 12px; font-size: 14px; }
        button { width: 100%; padding: 10px; border: none; border-radius: 8px; background: #f97316; color: #fff; font-weight: 700; cursor: pointer; }
        .error { color: #dc2626; font-size: 13px; margin-bottom: 12px; }
      </style>
    </head>
    <body>
      <form method="post">
        <h1>Orders Dashboard Login</h1>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <input type="password" name="password" placeholder="Admin password" autofocus required/>
        <button type="submit">Sign In</button>
      </form>
    </body>
    </html>
    <?php
}

if (!$adminPassword) {
    http_response_code(503);
    echo '<h1>Orders dashboard not configured</h1><p>Set ADMIN_PASSWORD in config.local.php to enable access.</p>';
    exit;
}

if (isset($_POST['password'])) {
    if (hash_equals($adminPassword, (string)$_POST['password'])) {
        session_regenerate_id(true);
        $_SESSION['orders_admin'] = true;
    } else {
        renderLoginForm('Incorrect password.');
        exit;
    }
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    renderLoginForm();
    exit;
}

if (empty($_SESSION['orders_admin'])) {
    renderLoginForm();
    exit;
}

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
  <h1>Orders Dashboard <a href="?logout=1" style="font-size:12px;font-weight:400;color:#6b7280;">(log out)</a></h1>
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

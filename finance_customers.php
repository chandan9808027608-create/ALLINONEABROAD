<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/finance_common.php';
requireFinanceAuth();
$conn = connectDb();

$error = null;
$showForm = isset($_GET['add']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['formAction'] ?? '') === 'add_customer') {
    $name = trim((string)($_POST['name'] ?? ''));
    $phone = preg_replace('/[^0-9]/', '', (string)($_POST['phone'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));

    if ($name === '') {
        $error = "Enter the customer's name.";
    } elseif ($phone !== '' && !preg_match('/^[0-9]{10}$/', $phone)) {
        $error = 'Enter a valid 10-digit phone number.';
    } else {
        if ($phone !== '') {
            $stmt = $conn->prepare('SELECT id FROM finance_customers WHERE phone = ? LIMIT 1');
            bindExec($stmt, [['s', $phone]]);
            if ($stmt->get_result()->fetch_assoc()) {
                $error = 'A customer with this phone number already exists.';
            }
            $stmt->close();
        }
        if ($error === null) {
            $phoneVal = $phone !== '' ? $phone : null;
            $addressVal = $address !== '' ? $address : null;
            $stmt = $conn->prepare('INSERT INTO finance_customers (name, phone, address) VALUES (?, ?, ?)');
            bindExec($stmt, [['s', $name], ['s', $phoneVal], ['s', $addressVal]]);
            $stmt->close();
            header('Location: finance_customers.php');
            exit;
        }
    }
    $showForm = true;
}

$customers = [];
$res = $conn->query('SELECT id, name, phone, address FROM finance_customers ORDER BY name');
while ($row = $res->fetch_assoc()) {
    $customers[] = $row;
}
$balances = financeCustomerBalances($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Customers — Finance — ALL IN ONE ABROAD</title>
  <?php financeStyles(); ?>
</head>
<body>
  <?php financeTopbar('customers'); ?>

  <div class="wrap narrow">
    <div class="panel">
      <div class="panel-head">
        <h2>Customers</h2>
        <div style="display:flex;gap:10px;align-items:center;">
          <input type="search" id="custSearch" placeholder="Search by name or phone" style="width:220px;" oninput="filterCustomers(this.value)"/>
          <a href="finance_customers.php<?= $showForm ? '' : '?add=1' ?>" class="btn btn-icon <?= $showForm ? 'btn-outline' : 'btn-primary' ?>"><?= $showForm ? '×' : '+' ?></a>
        </div>
      </div>

      <?php if ($showForm): ?>
      <div class="panel-body" style="border-bottom:1px solid #e5e7eb;">
        <?php if ($error): ?><div class="hint" style="color:#dc2626;margin-bottom:10px;"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
          <input type="hidden" name="formAction" value="add_customer"/>
          <div class="field">
            <label class="field-label">Name</label>
            <input type="text" name="name" required value="<?= e($_POST['name'] ?? '') ?>"/>
          </div>
          <div class="row">
            <div class="field">
              <label class="field-label">Phone (10 digits)</label>
              <input type="tel" name="phone" maxlength="10" value="<?= e($_POST['phone'] ?? '') ?>"/>
            </div>
          </div>
          <div class="field">
            <label class="field-label">Address</label>
            <input type="text" name="address" value="<?= e($_POST['address'] ?? '') ?>"/>
          </div>
          <div class="row">
            <a href="finance_customers.php" class="btn btn-outline" style="flex:1;">Cancel</a>
            <button type="submit" class="btn btn-primary" style="flex:1;">Save</button>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <?php if (count($customers) === 0): ?>
        <div class="empty">
          <div class="empty-icon">👥</div>
          <p>No customers saved yet. Add one above.</p>
        </div>
      <?php else: ?>
        <div id="custList">
          <?php foreach ($customers as $c): ?>
            <?php $balance = $balances[(int)$c['id']] ?? 0.0; ?>
            <a href="finance_customer.php?id=<?= (int)$c['id'] ?>" class="feed-row" style="text-decoration:none;" data-search="<?= e(strtolower($c['name'] . ' ' . ($c['phone'] ?? ''))) ?>">
              <div class="feed-main">
                <div class="feed-title"><?= e($c['name']) ?></div>
                <div class="feed-sub">
                  <?= $c['phone'] ? e($c['phone']) : '' ?><?= $c['phone'] && $c['address'] ? ' · ' : '' ?><?= $c['address'] ? e($c['address']) : '' ?>
                </div>
              </div>
              <?php if (abs($balance) > 0.004): ?>
                <div class="feed-amt">
                  <div class="<?= $balance > 0 ? 'amt-red' : 'amt-green' ?>" style="font-size:13px;"><?= fmtMoney(abs($balance)) ?></div>
                  <div class="feed-account"><?= $balance > 0 ? 'owes you' : 'you owe' ?></div>
                </div>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
    function filterCustomers(q) {
      q = q.trim().toLowerCase();
      document.querySelectorAll('#custList .feed-row').forEach(function (row) {
        row.style.display = row.dataset.search.includes(q) ? '' : 'none';
      });
    }
  </script>
</body>
</html>

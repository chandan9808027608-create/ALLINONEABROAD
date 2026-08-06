<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/finance_common.php';
requireFinanceAuth();
$conn = connectDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['formAction'] ?? '') === 'add_account') {
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name !== '') {
        $stmt = $conn->prepare('INSERT INTO finance_bank_accounts (name) VALUES (?)');
        bindExec($stmt, [['s', $name]]);
        $stmt->close();
    }
    header('Location: finance_bank_accounts.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['formAction'] ?? '') === 'rename_account') {
    $accId = (int)($_POST['account_id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    if ($accId > 0 && $name !== '') {
        $stmt = $conn->prepare('UPDATE finance_bank_accounts SET name = ? WHERE id = ?');
        bindExec($stmt, [['s', $name], ['i', $accId]]);
        $stmt->close();
    }
    header('Location: finance_bank_accounts.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['formAction'] ?? '') === 'delete_account') {
    $accId = (int)($_POST['account_id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM finance_bank_accounts WHERE id = ?');
    bindExec($stmt, [['i', $accId]]);
    $stmt->close();
    header('Location: finance_bank_accounts.php');
    exit;
}

$accounts = [];
$res = $conn->query('SELECT id, name FROM finance_bank_accounts ORDER BY name');
while ($row = $res->fetch_assoc()) {
    $accounts[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Bank Accounts — Finance — ALL IN ONE ABROAD</title>
  <?php financeStyles(); ?>
</head>
<body>
  <?php financeTopbar('bank'); ?>

  <div class="wrap narrow">
    <div class="page-head">
      <a href="finance.php" class="back">←</a>
      <h1>Bank Accounts</h1>
    </div>

    <p class="hint" style="margin-bottom:16px;">
      Add every bank account your business uses — each one then shows up as an option (alongside Cash) when recording
      a Sale, Purchase, or Expense, so you can track cash-in-hand and each bank's balance separately.
    </p>

    <form method="post" class="row" style="margin-bottom:18px;">
      <input type="hidden" name="formAction" value="add_account"/>
      <input type="text" name="name" placeholder="e.g. Nabil Bank - Current" required style="flex:3;"/>
      <button type="submit" class="btn btn-primary" style="flex:1;">Add</button>
    </form>

    <div class="feed-row">
      <div class="feed-icon" style="background:#f3f4f6;">💵</div>
      <div class="feed-main"><div class="feed-title">Cash</div></div>
      <div class="hint">Always available</div>
    </div>

    <?php if (count($accounts) === 0): ?>
      <div class="empty">
        <div class="empty-icon">🏦</div>
        <p>No bank accounts yet — add one above.</p>
      </div>
    <?php else: ?>
      <?php foreach ($accounts as $acc): ?>
        <div class="feed-row">
          <div class="feed-icon b">🏦</div>
          <form method="post" style="flex:1;display:flex;gap:8px;align-items:center;">
            <input type="hidden" name="formAction" value="rename_account"/>
            <input type="hidden" name="account_id" value="<?= (int)$acc['id'] ?>"/>
            <input type="text" name="name" value="<?= e($acc['name']) ?>" style="flex:1;"/>
            <button type="submit" class="btn btn-outline btn-sm">Save</button>
          </form>
          <form method="post" onsubmit="return confirm('Remove this bank account?');" style="margin-left:8px;">
            <input type="hidden" name="formAction" value="delete_account"/>
            <input type="hidden" name="account_id" value="<?= (int)$acc['id'] ?>"/>
            <button type="submit" class="btn btn-danger btn-sm">✕</button>
          </form>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</body>
</html>

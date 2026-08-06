<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/finance_common.php';
requireFinanceAuth();
$conn = connectDb();

$isOut = ($_GET['type'] ?? '') === 'out';
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['formAction'] ?? '') === 'quick_payment') {
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $amount = is_numeric($_POST['amount'] ?? null) ? (float)$_POST['amount'] : 0;
    $note = trim((string)($_POST['note'] ?? ''));
    $bankAccountId = is_numeric($_POST['bank_account_id'] ?? null) ? (int)$_POST['bank_account_id'] : null;

    if ($customerId <= 0) {
        $error = 'Search or pick a saved customer for this payment.';
    } elseif ($amount <= 0) {
        $error = 'Add a valid amount in Rs.';
    } else {
        $stmt = $conn->prepare('SELECT id, name FROM finance_customers WHERE id = ?');
        bindExec($stmt, [['i', $customerId]]);
        $customer = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$customer) {
            $error = 'Search or pick a saved customer for this payment.';
        } else {
            $entryType = $isOut ? 'debit' : 'credit';
            $noteVal = $note !== '' ? $note : null;
            $stmt = $conn->prepare('INSERT INTO finance_ledger_entries (customer_id, entry_type, amount, note, bank_account_id) VALUES (?, ?, ?, ?, ?)');
            bindExec($stmt, [['i', $customerId], ['s', $entryType], ['d', $amount], ['s', $noteVal], ['i', $bankAccountId]]);
            $stmt->close();
            $success = ($isOut ? 'Payment out recorded: ' : 'Payment in recorded: ') . fmtMoney($amount) . ' for ' . $customer['name'] . '.';
        }
    }
}

$customers = [];
$res = $conn->query('SELECT id, name, phone FROM finance_customers ORDER BY name');
while ($row = $res->fetch_assoc()) {
    $customers[] = $row;
}
$bankAccounts = financeBankAccountNames($conn);

$meta = $isOut
    ? ['label' => 'Payment Out', 'color' => '#DC2626', 'bg' => '#FEF2F2', 'icon' => '⬆️']
    : ['label' => 'Payment In', 'color' => '#059669', 'bg' => '#ECFDF5', 'icon' => '⬇️'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($meta['label']) ?> — Finance — ALL IN ONE ABROAD</title>
  <?php financeStyles(); ?>
</head>
<body>
  <?php financeTopbar('dashboard'); ?>

  <div class="wrap narrow">
    <div style="display:flex;align-items:center;gap:10px;padding:16px;border-radius:14px;margin-bottom:16px;background:<?= $meta['bg'] ?>;">
      <span style="font-size:20px;"><?= $meta['icon'] ?></span>
      <span style="font-size:15px;font-weight:800;color:<?= $meta['color'] ?>;"><?= e($meta['label']) ?></span>
    </div>

    <div class="card">
      <?php if ($error): ?><div class="hint" style="color:#dc2626;margin-bottom:12px;"><?= e($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="hint" style="color:#059669;margin-bottom:12px;"><?= e($success) ?></div><?php endif; ?>

      <form method="post">
        <input type="hidden" name="formAction" value="quick_payment"/>

        <div class="field">
          <label class="field-label">Customer</label>
          <select name="customer_id" required>
            <option value="">— Select a saved customer —</option>
            <?php foreach ($customers as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?><?= $c['phone'] ? ' (' . e($c['phone']) . ')' : '' ?></option>
            <?php endforeach; ?>
          </select>
          <div class="hint"><?= count($customers) ?> saved customer<?= count($customers) === 1 ? '' : 's' ?>. Add new ones from the Customers page.</div>
        </div>

        <div class="field">
          <label class="field-label"><?= $isOut ? 'Amount paid out (Rs.)' : 'Amount received (Rs.)' ?></label>
          <input type="number" step="0.01" min="0.01" name="amount" placeholder="e.g. 1000" required/>
        </div>

        <div class="field">
          <label class="field-label">Note (optional)</label>
          <input type="text" name="note" placeholder="e.g. Cash payment"/>
        </div>

        <div class="field">
          <label class="field-label">Payment account</label>
          <select name="bank_account_id">
            <option value="">Cash</option>
            <?php foreach ($bankAccounts as $bid => $bname): ?>
              <option value="<?= $bid ?>"><?= e($bname) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit" class="btn" style="width:100%;background:<?= $meta['color'] ?>;color:#fff;padding:12px;">
          <?= $isOut ? 'Record payment out' : 'Record payment in' ?>
        </button>
      </form>
    </div>
  </div>
</body>
</html>

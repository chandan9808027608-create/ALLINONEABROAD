<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/finance_common.php';
requireFinanceAuth();
$conn = connectDb();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: finance_customers.php');
    exit;
}

$error = null;
$editing = isset($_GET['edit']);
$showAddEntry = isset($_GET['add_entry']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['formAction'] ?? '') === 'update_customer') {
    $name = trim((string)($_POST['name'] ?? ''));
    $phone = preg_replace('/[^0-9]/', '', (string)($_POST['phone'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));

    if ($name === '') {
        $error = "Enter the customer's name.";
    } elseif ($phone !== '' && !preg_match('/^[0-9]{10}$/', $phone)) {
        $error = 'Enter a valid 10-digit phone number.';
    } else {
        if ($phone !== '') {
            $stmt = $conn->prepare('SELECT id FROM finance_customers WHERE phone = ? AND id != ? LIMIT 1');
            bindExec($stmt, [['s', $phone], ['i', $id]]);
            if ($stmt->get_result()->fetch_assoc()) {
                $error = 'A customer with this phone number already exists.';
            }
            $stmt->close();
        }
        if ($error === null) {
            $phoneVal = $phone !== '' ? $phone : null;
            $addressVal = $address !== '' ? $address : null;
            $stmt = $conn->prepare('UPDATE finance_customers SET name = ?, phone = ?, address = ? WHERE id = ?');
            bindExec($stmt, [['s', $name], ['s', $phoneVal], ['s', $addressVal], ['i', $id]]);
            $stmt->close();
            header('Location: finance_customer.php?id=' . $id);
            exit;
        }
    }
    $editing = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['formAction'] ?? '') === 'delete_customer') {
    $stmt = $conn->prepare('DELETE FROM finance_customers WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    header('Location: finance_customers.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['formAction'] ?? '') === 'add_entry') {
    $entryType = ($_POST['entry_type'] ?? '') === 'debit' ? 'debit' : 'credit';
    $amount = is_numeric($_POST['amount'] ?? null) ? (float)$_POST['amount'] : 0;
    $note = trim((string)($_POST['note'] ?? ''));
    $bankAccountId = is_numeric($_POST['bank_account_id'] ?? null) ? (int)$_POST['bank_account_id'] : null;

    if ($amount <= 0) {
        $error = 'Add a valid amount in Rs.';
        $showAddEntry = true;
    } else {
        $noteVal = $note !== '' ? $note : null;
        $stmt = $conn->prepare('INSERT INTO finance_ledger_entries (customer_id, entry_type, amount, note, bank_account_id) VALUES (?, ?, ?, ?, ?)');
        bindExec($stmt, [['i', $id], ['s', $entryType], ['d', $amount], ['s', $noteVal], ['i', $bankAccountId]]);
        $stmt->close();
        header('Location: finance_customer.php?id=' . $id);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['formAction'] ?? '') === 'delete_entry') {
    $entryId = (int)($_POST['entry_id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM finance_ledger_entries WHERE id = ? AND customer_id = ?');
    $stmt->bind_param('ii', $entryId, $id);
    $stmt->execute();
    $stmt->close();
    header('Location: finance_customer.php?id=' . $id);
    exit;
}

$stmt = $conn->prepare('SELECT id, name, phone, address FROM finance_customers WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$customer) {
    header('Location: finance_customers.php');
    exit;
}

$bankAccounts = financeBankAccountNames($conn);

$entries = [];
$stmt = $conn->prepare('SELECT id, entry_type, amount, note, bank_account_id, created_at FROM finance_ledger_entries WHERE customer_id = ? ORDER BY created_at DESC, id DESC');
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $entries[] = $row;
}
$stmt->close();

$balance = 0.0;
foreach ($entries as $entry) {
    $balance += $entry['entry_type'] === 'debit' ? (float)$entry['amount'] : -(float)$entry['amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($customer['name']) ?> — Finance — ALL IN ONE ABROAD</title>
  <?php financeStyles(); ?>
</head>
<body>
  <?php financeTopbar('customers'); ?>

  <div class="wrap narrow">
    <div class="page-head">
      <a href="finance_customers.php" class="back">←</a>
      <h1><?= e($customer['name']) ?></h1>
    </div>

    <?php if ($editing): ?>
      <div class="card">
        <h2 style="font-size:14px;font-weight:800;margin-bottom:14px;">Edit customer</h2>
        <?php if ($error): ?><div class="hint" style="color:#dc2626;margin-bottom:10px;"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
          <input type="hidden" name="formAction" value="update_customer"/>
          <div class="field">
            <label class="field-label">Name</label>
            <input type="text" name="name" required value="<?= e($customer['name']) ?>"/>
          </div>
          <div class="field">
            <label class="field-label">Phone (10 digits)</label>
            <input type="tel" name="phone" maxlength="10" value="<?= e($customer['phone'] ?? '') ?>"/>
          </div>
          <div class="field">
            <label class="field-label">Address</label>
            <input type="text" name="address" value="<?= e($customer['address'] ?? '') ?>"/>
          </div>
          <div class="row">
            <a href="finance_customer.php?id=<?= $id ?>" class="btn btn-outline" style="flex:1;">Cancel</a>
            <button type="submit" class="btn btn-primary" style="flex:1;">Save</button>
          </div>
        </form>
      </div>
    <?php else: ?>
      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
          <div>
            <?php if ($customer['phone']): ?><div class="hint" style="font-size:13px;color:#374151;">📞 <?= e($customer['phone']) ?></div><?php endif; ?>
            <?php if ($customer['address']): ?><div class="hint" style="font-size:13px;color:#374151;margin-top:4px;">📍 <?= e($customer['address']) ?></div><?php endif; ?>
            <?php if (!$customer['phone'] && !$customer['address']): ?><div class="hint">No contact details saved.</div><?php endif; ?>
          </div>
          <div style="display:flex;gap:8px;">
            <a href="finance_customer.php?id=<?= $id ?>&edit=1" class="btn btn-outline btn-sm">Edit</a>
            <form method="post" onsubmit="return confirm('Delete this customer? Their ledger history will be permanently removed.');" style="display:inline;">
              <input type="hidden" name="formAction" value="delete_customer"/>
              <button type="submit" class="btn btn-danger btn-sm">Delete</button>
            </form>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="balance-card">
      <div class="bc-label"><?= $balance > 0 ? 'Customer owes you' : ($balance < 0 ? 'You owe customer' : 'Balance') ?></div>
      <div class="bc-value" style="color:<?= $balance > 0 ? '#f87171' : ($balance < 0 ? '#34d399' : '#fff') ?>;"><?= fmtMoney(abs($balance)) ?></div>
    </div>

    <?php if ($showAddEntry): ?>
      <div class="card">
        <h2 style="font-size:14px;font-weight:800;margin-bottom:14px;">Add ledger entry</h2>
        <?php if ($error): ?><div class="hint" style="color:#dc2626;margin-bottom:10px;"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
          <input type="hidden" name="formAction" value="add_entry"/>
          <div class="seg">
            <label class="seg-btn credit"><input type="radio" name="entry_type" value="credit" checked/>Payment received</label>
            <label class="seg-btn debit"><input type="radio" name="entry_type" value="debit"/>Customer owes</label>
          </div>
          <div class="field">
            <label class="field-label">Amount (Rs.)</label>
            <input type="number" step="0.01" min="0.01" name="amount" required/>
          </div>
          <div class="field">
            <label class="field-label">Note (optional)</label>
            <input type="text" name="note"/>
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
          <div class="row">
            <a href="finance_customer.php?id=<?= $id ?>" class="btn btn-outline" style="flex:1;">Cancel</a>
            <button type="submit" class="btn btn-primary" style="flex:1;">Add entry</button>
          </div>
        </form>
      </div>
    <?php else: ?>
      <a href="finance_customer.php?id=<?= $id ?>&add_entry=1" class="btn btn-primary" style="width:100%;margin-bottom:18px;">+ Add ledger entry</a>
    <?php endif; ?>

    <h2 style="font-size:14px;font-weight:800;margin-bottom:10px;">History</h2>
    <?php if (count($entries) === 0): ?>
      <div class="empty">
        <div class="empty-icon">📄</div>
        <p>No ledger entries yet.</p>
      </div>
    <?php else: ?>
      <?php foreach ($entries as $entry): ?>
        <div class="feed-row">
          <div class="feed-icon <?= $entry['entry_type'] === 'debit' ? 'r' : 'g' ?>"><?= $entry['entry_type'] === 'debit' ? '↑' : '↓' ?></div>
          <div class="feed-main">
            <div class="feed-title"><?= $entry['entry_type'] === 'debit' ? 'Owes' : 'Paid' ?> <?= fmtMoney((float)$entry['amount']) ?></div>
            <div class="feed-sub">
              <?= $entry['note'] ? e($entry['note']) : 'Manual entry' ?> ·
              <?= e(date('M j, Y', strtotime($entry['created_at']))) ?>
              <?= $entry['bank_account_id'] ? ' · ' . e($bankAccounts[(int)$entry['bank_account_id']] ?? 'Bank') : '' ?>
            </div>
          </div>
          <form method="post" onsubmit="return confirm('Remove this entry?');">
            <input type="hidden" name="formAction" value="delete_entry"/>
            <input type="hidden" name="entry_id" value="<?= (int)$entry['id'] ?>"/>
            <button type="submit" class="feed-del" style="background:none;border:none;cursor:pointer;">✕</button>
          </form>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</body>
</html>

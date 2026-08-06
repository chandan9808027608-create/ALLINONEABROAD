<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/finance_common.php';
requireFinanceAuth();
$conn = connectDb();

$validTypes = ['sale', 'purchase', 'expense'];
$typeParam = $_GET['type'] ?? 'all';
$filter = in_array($typeParam, $validTypes, true) ? $typeParam : 'all';
$isLockedToType = $filter !== 'all';
$isAddFlow = ($_GET['add'] ?? '') === '1';
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$baseQuery = $isLockedToType ? ('?type=' . $filter) : '';

function financeDayLabel(string $createdAt): string
{
    $d = new DateTimeImmutable($createdAt);
    $dayStart = $d->setTime(0, 0, 0);
    $todayStart = (new DateTimeImmutable('now'))->setTime(0, 0, 0);
    $diffDays = (int)floor(($todayStart->getTimestamp() - $dayStart->getTimestamp()) / 86400);
    if ($diffDays === 0) {
        return 'Today';
    }
    if ($diffDays === 1) {
        return 'Yesterday';
    }
    if ($diffDays > 1) {
        return $diffDays . ' days ago';
    }
    return $d->format('M j, Y');
}

// --- category management ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['formAction'] ?? '') === 'add_category') {
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name !== '') {
        $stmt = $conn->prepare('INSERT INTO finance_expense_categories (name) VALUES (?)');
        bindExec($stmt, [['s', $name]]);
        $stmt->close();
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['formAction'] ?? '') === 'rename_category') {
    $catId = (int)($_POST['category_id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    if ($catId > 0 && $name !== '') {
        $stmt = $conn->prepare('UPDATE finance_expense_categories SET name = ? WHERE id = ?');
        bindExec($stmt, [['s', $name], ['i', $catId]]);
        $stmt->close();
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['formAction'] ?? '') === 'delete_category') {
    $catId = (int)($_POST['category_id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM finance_expense_categories WHERE id = ?');
    bindExec($stmt, [['i', $catId]]);
    $stmt->close();
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// --- save (create/update) a transaction ---
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['formAction'] ?? '') === 'save_transaction') {
    $txId = (int)($_POST['tx_id'] ?? 0);
    $type = in_array($_POST['type'] ?? '', $validTypes, true) ? $_POST['type'] : 'sale';
    $partyName = trim((string)($_POST['party_name'] ?? ''));
    $partyVal = $partyName !== '' ? $partyName : null;
    $note = trim((string)($_POST['note'] ?? ''));
    $noteVal = $note !== '' ? $note : null;
    $bankAccountId = is_numeric($_POST['bank_account_id'] ?? null) ? (int)$_POST['bank_account_id'] : null;
    $paymentMode = $bankAccountId ? 'bank' : 'cash';

    if ($type === 'expense') {
        $amount = is_numeric($_POST['amount'] ?? null) ? (float)$_POST['amount'] : 0;
        $billDate = trim((string)($_POST['expense_date'] ?? '')) ?: date('Y-m-d');
        $categoryId = is_numeric($_POST['category_id'] ?? null) ? (int)$_POST['category_id'] : null;

        if ($amount <= 0) {
            $error = 'Add a valid amount in Rs.';
        } else {
            $params = [
                ['s', $type], ['d', $amount], ['s', $noteVal], ['s', $partyVal], ['s', $billDate],
                ['i', $categoryId], ['s', $paymentMode], ['i', $bankAccountId],
            ];
            if ($txId > 0) {
                $stmt = $conn->prepare('UPDATE finance_transactions SET type=?, amount=?, note=?, party_name=?, bill_date=?, expense_category_id=?, payment_mode=?, bank_account_id=?, items=NULL, discount_amount=0, vat_amount=0, bill_no=NULL, party_address=NULL, vat_pan_no=NULL WHERE id=?');
                $params[] = ['i', $txId];
            } else {
                $stmt = $conn->prepare('INSERT INTO finance_transactions (type, amount, note, party_name, bill_date, expense_category_id, payment_mode, bank_account_id) VALUES (?,?,?,?,?,?,?,?)');
            }
            bindExec($stmt, $params);
            $stmt->close();
            header('Location: finance_transactions.php' . $baseQuery);
            exit;
        }
    } else {
        $descriptions = $_POST['item_description'] ?? [];
        $qtys = $_POST['item_qty'] ?? [];
        $rates = $_POST['item_rate'] ?? [];
        $items = [];
        $subtotal = 0.0;
        foreach ($descriptions as $i => $desc) {
            $desc = trim((string)$desc);
            $qty = is_numeric($qtys[$i] ?? null) ? (float)$qtys[$i] : 0;
            $rate = is_numeric($rates[$i] ?? null) ? (float)$rates[$i] : 0;
            if ($desc === '' || $qty <= 0 || $rate < 0) {
                continue;
            }
            $amt = $qty * $rate;
            $items[] = ['description' => $desc, 'qty' => $qty, 'rate' => $rate, 'amount' => $amt];
            $subtotal += $amt;
        }
        $discount = is_numeric($_POST['discount_amount'] ?? null) ? (float)$_POST['discount_amount'] : 0;
        $vat = is_numeric($_POST['vat_amount'] ?? null) ? (float)$_POST['vat_amount'] : 0;
        $grandTotal = $subtotal - $discount + $vat;
        $billNo = trim((string)($_POST['bill_no'] ?? ''));
        $billNoVal = $billNo !== '' ? $billNo : null;
        $billDate = trim((string)($_POST['bill_date'] ?? '')) ?: date('Y-m-d');
        $partyAddress = trim((string)($_POST['party_address'] ?? ''));
        $partyAddressVal = $partyAddress !== '' ? $partyAddress : null;
        $vatPanNo = trim((string)($_POST['vat_pan_no'] ?? ''));
        $vatPanVal = $vatPanNo !== '' ? $vatPanNo : null;

        if (count($items) === 0) {
            $error = 'Add at least one item with a description, quantity, and rate.';
        } elseif ($grandTotal <= 0) {
            $error = 'The grand total must be more than zero — check item amounts and discount/VAT.';
        } else {
            $itemsJson = json_encode($items);
            $params = [
                ['s', $type], ['d', $grandTotal], ['s', $noteVal], ['s', $partyVal], ['s', $billNoVal],
                ['s', $billDate], ['s', $partyAddressVal], ['s', $vatPanVal], ['s', $itemsJson],
                ['d', $discount], ['d', $vat], ['s', $paymentMode], ['i', $bankAccountId],
            ];
            if ($txId > 0) {
                $stmt = $conn->prepare('UPDATE finance_transactions SET type=?, amount=?, note=?, party_name=?, bill_no=?, bill_date=?, party_address=?, vat_pan_no=?, items=?, discount_amount=?, vat_amount=?, payment_mode=?, bank_account_id=?, expense_category_id=NULL WHERE id=?');
                $params[] = ['i', $txId];
            } else {
                $stmt = $conn->prepare('INSERT INTO finance_transactions (type, amount, note, party_name, bill_no, bill_date, party_address, vat_pan_no, items, discount_amount, vat_amount, payment_mode, bank_account_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
            }
            bindExec($stmt, $params);
            $stmt->close();
            header('Location: finance_transactions.php' . $baseQuery);
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['formAction'] ?? '') === 'delete_transaction') {
    $txId = (int)($_POST['tx_id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM finance_transactions WHERE id = ?');
    bindExec($stmt, [['i', $txId]]);
    $stmt->close();
    header('Location: finance_transactions.php' . $baseQuery);
    exit;
}

// --- reference data for the form ---
$categories = [];
$res = $conn->query('SELECT id, name FROM finance_expense_categories ORDER BY name');
while ($row = $res->fetch_assoc()) {
    $categories[] = $row;
}
$bankAccounts = financeBankAccountNames($conn);

$customers = [];
$res = $conn->query('SELECT id, name, phone, address FROM finance_customers ORDER BY name');
while ($row = $res->fetch_assoc()) {
    $customers[] = $row;
}
$customerNames = array_map(fn($c) => $c['name'], $customers);

$historicalExpenseNames = [];
$res = $conn->query("SELECT DISTINCT party_name FROM finance_transactions WHERE type = 'expense' AND party_name IS NOT NULL AND party_name != ''");
while ($row = $res->fetch_assoc()) {
    $historicalExpenseNames[] = $row['party_name'];
}
$partyNameOptions = array_values(array_unique(array_merge($customerNames, $historicalExpenseNames)));

$customerAddressByName = [];
foreach ($customers as $c) {
    if (!empty($c['address'])) {
        $customerAddressByName[strtolower($c['name'])] = $c['address'];
    }
}

$editingTx = null;
if ($editId > 0) {
    $stmt = $conn->prepare('SELECT * FROM finance_transactions WHERE id = ?');
    bindExec($stmt, [['i', $editId]]);
    $editingTx = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($editingTx) {
        $editingTx['items'] = $editingTx['items'] ? json_decode($editingTx['items'], true) : [];
    }
}

$isQuickAddFlow = $isLockedToType && $isAddFlow && !$editingTx;
$showForm = $editingTx !== null || $isQuickAddFlow || (!$isLockedToType && ($_GET['form'] ?? '') === '1');
$formType = $editingTx ? $editingTx['type'] : ($filter === 'all' ? 'sale' : $filter);
$formIsBill = $formType !== 'expense';

// --- feed ---
$feed = [];
if ($isLockedToType) {
    $stmt = $conn->prepare('SELECT * FROM finance_transactions WHERE type = ? ORDER BY created_at DESC, id DESC');
    bindExec($stmt, [['s', $filter]]);
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $feed[] = ['kind' => 'business', 'created_at' => $row['created_at'], 'tx' => $row];
    }
    $stmt->close();
} else {
    $res = $conn->query('SELECT * FROM finance_transactions ORDER BY created_at DESC, id DESC');
    while ($row = $res->fetch_assoc()) {
        $feed[] = ['kind' => 'business', 'created_at' => $row['created_at'], 'tx' => $row];
    }
    $res = $conn->query('SELECT l.*, c.name AS customer_name FROM finance_ledger_entries l JOIN finance_customers c ON c.id = l.customer_id ORDER BY l.created_at DESC, l.id DESC');
    while ($row = $res->fetch_assoc()) {
        $feed[] = ['kind' => 'ledger', 'created_at' => $row['created_at'], 'entry' => $row];
    }
    usort($feed, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
}

$sections = [];
foreach ($feed as $item) {
    $label = financeDayLabel($item['created_at']);
    if (!empty($sections) && end($sections)['title'] === $label) {
        $sections[count($sections) - 1]['items'][] = $item;
    } else {
        $sections[] = ['title' => $label, 'items' => [$item]];
    }
}

// --- trend chart datasets (day/week/month) for the currently locked metric ---
$allTransactions = [];
$res = $conn->query('SELECT type, amount, created_at FROM finance_transactions');
while ($row = $res->fetch_assoc()) {
    $allTransactions[] = $row;
}
$trendCounts = ['day' => 30, 'week' => 12, 'month' => 12];
$trendDatasets = [];
foreach ($trendCounts as $granularity => $count) {
    $buckets = financePeriodBuckets($granularity, $count);
    $data = [];
    foreach ($buckets as $bucket) {
        if ($filter === 'all') {
            $net = 0.0;
            foreach ($allTransactions as $t) {
                $ts = strtotime($t['created_at']);
                if ($ts < $bucket['start'] || $ts >= $bucket['end']) {
                    continue;
                }
                $net += $t['type'] === 'sale' ? (float)$t['amount'] : -(float)$t['amount'];
            }
            $data[] = ['label' => $bucket['label'], 'net' => $net];
        } else {
            $value = 0.0;
            foreach ($allTransactions as $t) {
                if ($t['type'] !== $filter) {
                    continue;
                }
                $ts = strtotime($t['created_at']);
                if ($ts < $bucket['start'] || $ts >= $bucket['end']) {
                    continue;
                }
                $value += (float)$t['amount'];
            }
            $data[] = ['label' => $bucket['label'], 'value' => $value];
        }
    }
    $trendDatasets[$granularity] = $data;
}

$categoryNameById = [];
foreach ($categories as $cat) {
    $categoryNameById[(int)$cat['id']] = $cat['name'];
}

$typeMeta = [
    'sale' => ['label' => 'Sale', 'icon' => '📈', 'class' => 'g', 'color' => '#059669'],
    'purchase' => ['label' => 'Purchase', 'icon' => '🛒', 'class' => 'b', 'color' => '#2563eb'],
    'expense' => ['label' => 'Expense', 'icon' => '🧾', 'class' => 'r', 'color' => '#dc2626'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Transactions — Finance — ALL IN ONE ABROAD</title>
  <?php financeStyles(); ?>
  <style>
    .type-tabs { display: flex; gap: 8px; }
    .type-tab { font-size: 12.5px; font-weight: 700; color: #6b7280; padding: 7px 14px; border-radius: 100px; border: 1.5px solid #e5e7eb; text-decoration: none; }
    .type-tab.active { background: #f97316; color: #fff; border-color: #f97316; }
  </style>
</head>
<body>
  <?php financeTopbar('transactions'); ?>

  <div class="wrap">
    <div class="panel-head" style="background:#fff;border:1.5px solid #e5e7eb;border-radius:14px;margin-bottom:16px;">
      <?php if ($isLockedToType): ?>
        <div style="display:flex;align-items:center;gap:8px;">
          <a href="finance_transactions.php" class="back" style="text-decoration:none;color:#374151;font-size:18px;">←</a>
          <h1 style="font-size:15px;font-weight:800;"><?= e($typeMeta[$filter]['label']) ?></h1>
        </div>
      <?php else: ?>
        <div class="type-tabs">
          <a href="finance_transactions.php" class="type-tab active">All</a>
          <a href="finance_transactions.php?type=sale" class="type-tab">Sales</a>
          <a href="finance_transactions.php?type=purchase" class="type-tab">Purchase</a>
          <a href="finance_transactions.php?type=expense" class="type-tab">Expense</a>
        </div>
      <?php endif; ?>
      <?php if (!$isLockedToType && !$editingTx): ?>
        <a href="finance_transactions.php<?= $showForm ? '' : '?form=1' ?>" class="btn btn-icon <?= $showForm ? 'btn-outline' : 'btn-primary' ?>"><?= $showForm ? '×' : '+' ?></a>
      <?php endif; ?>
    </div>

    <?php if (!($showForm && $isQuickAddFlow)): ?>
      <div class="panel">
        <div class="panel-body">
          <div class="g-toggle" id="trend-toggle">
            <button type="button" class="g-btn" data-g="day">Day</button>
            <button type="button" class="g-btn" data-g="week">Week</button>
            <button type="button" class="g-btn active" data-g="month">Month</button>
          </div>
          <div id="trend-chart" class="bars-wrap"></div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($showForm): ?>
    <div class="card">
      <form method="post" id="txForm">
        <input type="hidden" name="formAction" value="save_transaction"/>
        <input type="hidden" name="tx_id" value="<?= $editingTx ? (int)$editingTx['id'] : 0 ?>"/>
        <input type="hidden" name="type" value="<?= e($formType) ?>"/>

        <?php if ($error): ?><div class="hint" style="color:#dc2626;margin-bottom:12px;"><?= e($error) ?></div><?php endif; ?>

        <datalist id="partyNames">
          <?php foreach ($partyNameOptions as $n): ?><option value="<?= e($n) ?>"><?php endforeach; ?>
        </datalist>

        <?php if ($formIsBill): ?>
          <div class="row">
            <div class="field">
              <label class="field-label">Date</label>
              <input type="date" name="bill_date" value="<?= e($editingTx['bill_date'] ?? date('Y-m-d')) ?>"/>
            </div>
            <div class="field">
              <label class="field-label">Bill No.</label>
              <input type="text" name="bill_no" placeholder="e.g. 0234" value="<?= e($editingTx['bill_no'] ?? '') ?>"/>
            </div>
          </div>
          <div class="field">
            <label class="field-label"><?= $formType === 'purchase' ? 'Vendor/supplier name' : 'Party name' ?></label>
            <input type="text" name="party_name" list="partyNames" id="partyNameInput" value="<?= e($editingTx['party_name'] ?? '') ?>"/>
            <div class="hint">Pick a saved customer for auto-fill, or type a new name.</div>
          </div>
          <div class="field">
            <label class="field-label">Address (optional)</label>
            <input type="text" name="party_address" id="partyAddressInput" value="<?= e($editingTx['party_address'] ?? '') ?>"/>
          </div>
          <div class="field">
            <label class="field-label">VAT/PAN No. (optional)</label>
            <input type="text" name="vat_pan_no" value="<?= e($editingTx['vat_pan_no'] ?? '') ?>"/>
          </div>

          <div id="itemRows">
            <?php
            $initialItems = ($editingTx && count($editingTx['items']) > 0) ? $editingTx['items'] : [['description' => '', 'qty' => 1, 'rate' => '']];
            foreach ($initialItems as $idx => $item):
            ?>
              <div class="item-row">
                <div class="item-row-head"><span>Item <?= $idx + 1 ?></span><a href="#" class="remove-item">✕</a></div>
                <input type="text" name="item_description[]" placeholder="Item description" value="<?= e($item['description'] ?? '') ?>" style="margin-bottom:8px;"/>
                <div class="row">
                  <div class="field"><label class="field-label">Qty</label><input type="number" step="any" name="item_qty[]" class="item-qty" value="<?= e((string)($item['qty'] ?? 1)) ?>"/></div>
                  <div class="field"><label class="field-label">Rate</label><input type="number" step="0.01" name="item_rate[]" class="item-rate" value="<?= e((string)($item['rate'] ?? '')) ?>"/></div>
                  <div class="field"><label class="field-label">Amount</label><div class="item-amt">0</div></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <a href="#" id="addItemBtn" style="display:inline-block;margin-bottom:14px;font-size:12.5px;font-weight:700;color:#ea580c;text-decoration:none;">+ Add item</a>

          <div style="display:flex;justify-content:space-between;padding-top:10px;border-top:1px solid #f3f4f6;margin-bottom:10px;font-size:12.5px;color:#6b7280;">
            <span>Subtotal</span><span id="subtotalDisplay" style="font-weight:700;color:#374151;">Rs. 0</span>
          </div>
          <details <?= ($editingTx && ((float)$editingTx['discount_amount'] > 0 || (float)$editingTx['vat_amount'] > 0)) ? 'open' : '' ?> style="margin-bottom:14px;">
            <summary style="font-size:12.5px;font-weight:700;color:#ea580c;cursor:pointer;">+ Add discount / VAT</summary>
            <div class="row" style="margin-top:10px;">
              <div class="field"><label class="field-label">Discount</label><input type="number" step="0.01" name="discount_amount" id="discountInput" value="<?= e((string)($editingTx['discount_amount'] ?? 0)) ?>"/></div>
              <div class="field"><label class="field-label">VAT</label><input type="number" step="0.01" name="vat_amount" id="vatInput" value="<?= e((string)($editingTx['vat_amount'] ?? 0)) ?>"/></div>
            </div>
          </details>
          <div class="field">
            <label class="field-label">Note (optional)</label>
            <input type="text" name="note" value="<?= e($editingTx['note'] ?? '') ?>"/>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;background:#f9fafb;border-radius:8px;padding:10px 14px;margin-bottom:14px;">
            <span style="font-size:13.5px;font-weight:800;">G. Total</span>
            <span id="grandTotalDisplay" style="font-size:16px;font-weight:800;">Rs. 0</span>
          </div>
        <?php else: ?>
          <div class="field">
            <label class="field-label">Date</label>
            <input type="date" name="expense_date" value="<?= e($editingTx['bill_date'] ?? date('Y-m-d')) ?>"/>
          </div>
          <div class="field">
            <label class="field-label">Name</label>
            <input type="text" name="party_name" list="partyNames" placeholder="Who was this paid to?" value="<?= e($editingTx['party_name'] ?? '') ?>"/>
          </div>

          <div class="field">
            <label class="field-label">Expense category</label>
            <select name="category_id">
              <option value="">Select a category</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>" <?= ($editingTx && (int)($editingTx['expense_category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <details style="margin-bottom:14px;">
            <summary style="font-size:12px;font-weight:700;color:#6b7280;cursor:pointer;">Manage categories</summary>
            <div style="margin-top:10px;">
              <?php foreach ($categories as $cat): ?>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                  <form method="post" style="flex:1;display:flex;gap:6px;">
                    <input type="hidden" name="formAction" value="rename_category"/>
                    <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>"/>
                    <input type="text" name="name" value="<?= e($cat['name']) ?>" style="flex:1;"/>
                    <button type="submit" class="btn btn-outline btn-sm">Save</button>
                  </form>
                  <form method="post" onsubmit="return confirm('Remove this category?');">
                    <input type="hidden" name="formAction" value="delete_category"/>
                    <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>"/>
                    <button type="submit" class="btn btn-danger btn-sm">✕</button>
                  </form>
                </div>
              <?php endforeach; ?>
              <form method="post" style="display:flex;gap:6px;margin-top:8px;">
                <input type="hidden" name="formAction" value="add_category"/>
                <input type="text" name="name" placeholder="New category name" style="flex:1;"/>
                <button type="submit" class="btn btn-primary btn-sm">Add</button>
              </form>
            </div>
          </details>

          <div class="field">
            <label class="field-label">Amount (Rs.)</label>
            <input type="number" step="0.01" min="0.01" name="amount" required value="<?= e((string)($editingTx['amount'] ?? '')) ?>"/>
          </div>
          <div class="field">
            <label class="field-label">Remark (optional)</label>
            <input type="text" name="note" value="<?= e($editingTx['note'] ?? '') ?>"/>
          </div>
        <?php endif; ?>

        <div class="field">
          <label class="field-label">Payment account</label>
          <select name="bank_account_id">
            <option value="">Cash</option>
            <?php foreach ($bankAccounts as $bid => $bname): ?>
              <option value="<?= $bid ?>" <?= ($editingTx && (int)($editingTx['bank_account_id'] ?? 0) === $bid) ? 'selected' : '' ?>><?= e($bname) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="row">
          <a href="finance_transactions.php<?= $baseQuery ?>" class="btn btn-outline" style="flex:1;">Cancel</a>
          <button type="submit" class="btn btn-primary" style="flex:1;">Save</button>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <?php if (count($feed) === 0): ?>
      <div class="empty">
        <div class="empty-icon">💵</div>
        <p>No transactions yet.</p>
      </div>
    <?php else: ?>
      <?php foreach ($sections as $section): ?>
        <div class="day-heading"><?= e($section['title']) ?></div>
        <?php foreach ($section['items'] as $item): ?>
          <?php if ($item['kind'] === 'business'): ?>
            <?php $tx = $item['tx']; $meta = $typeMeta[$tx['type']]; $accountLabel = $tx['bank_account_id'] ? ($bankAccounts[(int)$tx['bank_account_id']] ?? 'Bank') : 'Cash'; $items = $tx['items'] ? json_decode($tx['items'], true) : []; ?>
            <div class="feed-row">
              <a href="finance_transactions.php<?= $baseQuery ?>&edit=<?= (int)$tx['id'] ?>" style="text-decoration:none;color:inherit;display:flex;align-items:center;gap:12px;flex:1;min-width:0;">
                <div class="feed-icon <?= $meta['class'] ?>"><?= $meta['icon'] ?></div>
                <div class="feed-main">
                  <div class="feed-title">
                    <?= $meta['label'] ?><?= $tx['party_name'] ? ' · ' . e($tx['party_name']) : '' ?><?= $tx['bill_no'] ? ' · Bill #' . e($tx['bill_no']) : '' ?><?= $tx['expense_category_id'] && isset($categoryNameById[(int)$tx['expense_category_id']]) ? ' · ' . e($categoryNameById[(int)$tx['expense_category_id']]) : '' ?>
                  </div>
                  <div class="feed-sub">
                    <?= count($items) > 0 ? count($items) . ' item' . (count($items) === 1 ? '' : 's') : ($tx['note'] ? e($tx['note']) : e(date('M j, Y', strtotime($tx['created_at'])))) ?>
                  </div>
                </div>
                <div class="feed-amt">
                  <div style="color:<?= $meta['color'] ?>;font-weight:800;font-size:13.5px;"><?= fmtMoney((float)$tx['amount']) ?></div>
                  <div class="feed-account"><?= e($accountLabel) ?></div>
                </div>
              </a>
              <form method="post" onsubmit="return confirm('Delete this transaction?');">
                <input type="hidden" name="formAction" value="delete_transaction"/>
                <input type="hidden" name="tx_id" value="<?= (int)$tx['id'] ?>"/>
                <button type="submit" class="feed-del" style="background:none;border:none;cursor:pointer;">🗑</button>
              </form>
            </div>
          <?php else: ?>
            <?php $entry = $item['entry']; $isDebit = $entry['entry_type'] === 'debit'; ?>
            <a href="finance_customer.php?id=<?= (int)$entry['customer_id'] ?>" class="feed-row" style="text-decoration:none;">
              <div class="feed-icon <?= $isDebit ? 'r' : 'g' ?>"><?= $isDebit ? '↑' : '↓' ?></div>
              <div class="feed-main">
                <div class="feed-title"><?= $isDebit ? 'Customer owes' : 'Received from customer' ?> · <?= e($entry['customer_name']) ?></div>
                <div class="feed-sub"><?= $entry['note'] ? e($entry['note']) : 'Manual entry' ?> · <?= e(date('M j, Y', strtotime($entry['created_at']))) ?></div>
              </div>
              <div class="<?= $isDebit ? 'amt-red' : 'amt-green' ?>" style="font-size:13.5px;"><?= fmtMoney((float)$entry['amount']) ?></div>
            </a>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php financeChartScript(); ?>
  <script>
    var trendDatasets = <?= json_encode($trendDatasets) ?>;
    var isNet = <?= $filter === 'all' ? 'true' : 'false' ?>;
    var trendColor = <?= json_encode($typeMeta[$filter]['color'] ?? '#2563EB') ?>;
    if (document.getElementById('trend-chart')) {
      if (isNet) {
        renderNetBars('trend-chart', trendDatasets.month);
      } else {
        renderBars('trend-chart', trendDatasets.month, trendColor);
      }
      attachGranularityToggle('trend-toggle', 'trend-chart', trendDatasets, isNet ? renderNetBars : function (id, data) { renderBars(id, data, trendColor); });
    }

    // Item rows: add/remove + live subtotal/grand-total recompute.
    var itemRowsEl = document.getElementById('itemRows');
    function recomputeTotals() {
      if (!itemRowsEl) return;
      var subtotal = 0;
      itemRowsEl.querySelectorAll('.item-row').forEach(function (row) {
        var qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        var rate = parseFloat(row.querySelector('.item-rate').value) || 0;
        var amt = qty * rate;
        row.querySelector('.item-amt').textContent = Math.round(amt).toLocaleString();
        subtotal += amt;
      });
      var discount = parseFloat((document.getElementById('discountInput') || {}).value) || 0;
      var vat = parseFloat((document.getElementById('vatInput') || {}).value) || 0;
      var grand = subtotal - discount + vat;
      var subEl = document.getElementById('subtotalDisplay');
      var grandEl = document.getElementById('grandTotalDisplay');
      if (subEl) subEl.textContent = fmtRs(subtotal);
      if (grandEl) grandEl.textContent = fmtRs(grand);
    }
    function renumberItems() {
      itemRowsEl.querySelectorAll('.item-row').forEach(function (row, i) {
        row.querySelector('.item-row-head span').textContent = 'Item ' + (i + 1);
      });
    }
    if (itemRowsEl) {
      itemRowsEl.addEventListener('input', recomputeTotals);
      itemRowsEl.addEventListener('click', function (ev) {
        if (ev.target.classList.contains('remove-item')) {
          ev.preventDefault();
          if (itemRowsEl.querySelectorAll('.item-row').length > 1) {
            ev.target.closest('.item-row').remove();
            renumberItems();
            recomputeTotals();
          }
        }
      });
      var addBtn = document.getElementById('addItemBtn');
      if (addBtn) {
        addBtn.addEventListener('click', function (ev) {
          ev.preventDefault();
          var row = document.createElement('div');
          row.className = 'item-row';
          row.innerHTML = '<div class="item-row-head"><span>Item</span><a href="#" class="remove-item">✕</a></div>' +
            '<input type="text" name="item_description[]" placeholder="Item description" style="margin-bottom:8px;"/>' +
            '<div class="row">' +
            '<div class="field"><label class="field-label">Qty</label><input type="number" step="any" name="item_qty[]" class="item-qty" value="1"/></div>' +
            '<div class="field"><label class="field-label">Rate</label><input type="number" step="0.01" name="item_rate[]" class="item-rate"/></div>' +
            '<div class="field"><label class="field-label">Amount</label><div class="item-amt">0</div></div>' +
            '</div>';
          itemRowsEl.appendChild(row);
          renumberItems();
        });
      }
      var discountInput = document.getElementById('discountInput');
      var vatInput = document.getElementById('vatInput');
      if (discountInput) discountInput.addEventListener('input', recomputeTotals);
      if (vatInput) vatInput.addEventListener('input', recomputeTotals);
      recomputeTotals();
    }

    // Auto-fill the party address when the typed name exactly matches a
    // saved customer (bill forms only).
    var customerAddressByName = <?= json_encode($customerAddressByName) ?>;
    var partyNameInput = document.getElementById('partyNameInput');
    var partyAddressInput = document.getElementById('partyAddressInput');
    if (partyNameInput && partyAddressInput) {
      partyNameInput.addEventListener('input', function () {
        var addr = customerAddressByName[partyNameInput.value.trim().toLowerCase()];
        if (addr) partyAddressInput.value = addr;
      });
    }
  </script>
</body>
</html>

<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/finance_common.php';
requireFinanceAuth();
$conn = connectDb();

$customerCount = (int)$conn->query('SELECT COUNT(*) c FROM finance_customers')->fetch_assoc()['c'];

$transactions = [];
$res = $conn->query('SELECT id, type, amount, created_at FROM finance_transactions');
while ($row = $res->fetch_assoc()) {
    $transactions[] = $row;
}

$ledgerEntries = [];
$res = $conn->query('SELECT id, customer_id, entry_type, amount, created_at FROM finance_ledger_entries');
while ($row = $res->fetch_assoc()) {
    $ledgerEntries[] = $row;
}

$totals = ['sale' => 0.0, 'purchase' => 0.0, 'expense' => 0.0];
foreach ($transactions as $t) {
    $totals[$t['type']] += (float)$t['amount'];
}

$balances = financeCustomerBalances($conn);
$toReceive = 0.0;
$toGive = 0.0;
foreach ($balances as $b) {
    if ($b > 0) {
        $toReceive += $b;
    } else {
        $toGive += -$b;
    }
}

$availableBalance = $totals['sale'] - $totals['purchase'] - $totals['expense'];

$year = (int)date('Y');
$yearReceived = 0.0;
$yearPaid = 0.0;
foreach ($transactions as $t) {
    if ((int)date('Y', strtotime($t['created_at'])) !== $year) {
        continue;
    }
    if ($t['type'] === 'sale') {
        $yearReceived += (float)$t['amount'];
    }
    if ($t['type'] === 'purchase' || $t['type'] === 'expense') {
        $yearPaid += (float)$t['amount'];
    }
}
foreach ($ledgerEntries as $entry) {
    if ((int)date('Y', strtotime($entry['created_at'])) !== $year) {
        continue;
    }
    if ($entry['entry_type'] === 'credit') {
        $yearReceived += (float)$entry['amount'];
    }
    if ($entry['entry_type'] === 'debit') {
        $yearPaid += (float)$entry['amount'];
    }
}

// Cashflow bar-chart datasets for the day/week/month toggle - each bucket
// shows both cash in and cash out so a big payment in and out the same day
// doesn't net to ~zero and look like nothing happened.
$cashflowCounts = ['day' => 10, 'week' => 8, 'month' => 6];
$cashflowDatasets = [];
foreach ($cashflowCounts as $granularity => $count) {
    $buckets = financePeriodBuckets($granularity, $count);
    $data = [];
    foreach ($buckets as $bucket) {
        $inAmt = 0.0;
        $outAmt = 0.0;
        foreach ($transactions as $t) {
            $ts = strtotime($t['created_at']);
            if ($ts < $bucket['start'] || $ts >= $bucket['end']) {
                continue;
            }
            if ($t['type'] === 'sale') {
                $inAmt += (float)$t['amount'];
            }
            if ($t['type'] === 'purchase' || $t['type'] === 'expense') {
                $outAmt += (float)$t['amount'];
            }
        }
        foreach ($ledgerEntries as $entry) {
            $ts = strtotime($entry['created_at']);
            if ($ts < $bucket['start'] || $ts >= $bucket['end']) {
                continue;
            }
            if ($entry['entry_type'] === 'credit') {
                $inAmt += (float)$entry['amount'];
            }
            if ($entry['entry_type'] === 'debit') {
                $outAmt += (float)$entry['amount'];
            }
        }
        $data[] = ['label' => $bucket['label'], 'inAmt' => $inAmt, 'outAmt' => $outAmt];
    }
    $cashflowDatasets[$granularity] = $data;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Finance — ALL IN ONE ABROAD</title>
  <?php financeStyles(); ?>
</head>
<body>
  <?php financeTopbar('dashboard'); ?>

  <div class="wrap">
    <div class="grid-2" style="margin-bottom:14px;">
      <div class="tile red">
        <div class="tile-label">To Receive</div>
        <div class="tile-value"><?= fmtMoney($toReceive) ?></div>
      </div>
      <div class="tile green" style="background:#ecfdf5;border-color:#a7f3d0;">
        <div class="tile-label" style="color:#047857;">To Give</div>
        <div class="tile-value" style="color:#047857;"><?= fmtMoney($toGive) ?></div>
      </div>
    </div>

    <div class="grid-4" style="margin-bottom:14px;">
      <a href="finance_transactions.php?type=sale" class="tile">
        <div class="tile-label">Sales</div>
        <div class="tile-value" style="color:#059669;"><?= fmtMoney($totals['sale']) ?></div>
      </a>
      <a href="finance_transactions.php?type=purchase" class="tile">
        <div class="tile-label">Purchase</div>
        <div class="tile-value" style="color:#2563eb;"><?= fmtMoney($totals['purchase']) ?></div>
      </a>
      <a href="finance_transactions.php?type=expense" class="tile">
        <div class="tile-label">Expense</div>
        <div class="tile-value" style="color:#dc2626;"><?= fmtMoney($totals['expense']) ?></div>
      </a>
      <a href="finance_transactions.php" class="tile">
        <div class="tile-label">Available Balance</div>
        <div class="tile-value" style="color:<?= $availableBalance >= 0 ? '#2563eb' : '#dc2626' ?>;"><?= fmtMoney($availableBalance) ?></div>
      </a>
    </div>

    <div class="grid-2" style="margin-bottom:20px;">
      <a href="finance_totals.php?kind=received" class="tile" style="background:#ecfdf5;border-color:#a7f3d0;">
        <div class="tile-label" style="color:#047857;">Total Received (<?= $year ?>)</div>
        <div class="tile-value" style="color:#047857;"><?= fmtMoney($yearReceived) ?></div>
      </a>
      <a href="finance_totals.php?kind=paid" class="tile red">
        <div class="tile-label">Total Paid (<?= $year ?>)</div>
        <div class="tile-value"><?= fmtMoney($yearPaid) ?></div>
      </a>
    </div>

    <h2 style="font-size:14px;font-weight:800;margin-bottom:10px;">Shortcuts</h2>
    <div class="grid-4" style="margin-bottom:24px;">
      <a href="finance_customers.php" class="shortcut"><div class="shortcut-icon">👥</div><div class="shortcut-label">Customers</div></a>
      <a href="finance_quick_payment.php?type=in" class="shortcut"><div class="shortcut-icon">⬇️</div><div class="shortcut-label">Payment In</div></a>
      <a href="finance_quick_payment.php?type=out" class="shortcut"><div class="shortcut-icon">⬆️</div><div class="shortcut-label">Payment Out</div></a>
      <a href="finance_transactions.php?type=sale&add=1" class="shortcut"><div class="shortcut-icon">📈</div><div class="shortcut-label">Sales</div></a>
      <a href="finance_transactions.php?type=purchase&add=1" class="shortcut"><div class="shortcut-icon">🛒</div><div class="shortcut-label">Purchase</div></a>
      <a href="finance_transactions.php?type=expense&add=1" class="shortcut"><div class="shortcut-icon">🧾</div><div class="shortcut-label">Expenses</div></a>
      <a href="finance_bank_accounts.php" class="shortcut"><div class="shortcut-icon">🏦</div><div class="shortcut-label">Bank Accounts</div></a>
      <a href="finance_transactions.php" class="shortcut"><div class="shortcut-icon">📋</div><div class="shortcut-label">Transactions</div></a>
    </div>

    <div class="panel">
      <div class="panel-head"><h2>Cashflow</h2></div>
      <div class="panel-body">
        <div class="g-toggle" id="cashflow-toggle">
          <button type="button" class="g-btn active" data-g="day">Day</button>
          <button type="button" class="g-btn" data-g="week">Week</button>
          <button type="button" class="g-btn" data-g="month">Month</button>
        </div>
        <div id="cashflow-chart" class="bars-wrap"></div>
        <div class="chart-legend">
          <span><span class="dot" style="background:#6ee7b7;"></span> Cash in</span>
          <span><span class="dot" style="background:#fca5a5;"></span> Cash out</span>
        </div>
      </div>
    </div>

    <p style="text-align:center;font-size:12px;color:#9ca3af;">
      Across <?= $customerCount ?> customer<?= $customerCount === 1 ? '' : 's' ?>
    </p>
  </div>

  <?php financeChartScript(); ?>
  <script>
    var cashflowDatasets = <?= json_encode($cashflowDatasets) ?>;
    renderDualBars('cashflow-chart', cashflowDatasets.day);
    attachGranularityToggle('cashflow-toggle', 'cashflow-chart', cashflowDatasets, renderDualBars);
  </script>
</body>
</html>

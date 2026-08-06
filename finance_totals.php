<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/finance_common.php';
requireFinanceAuth();
$conn = connectDb();

$kind = ($_GET['kind'] ?? '') === 'paid' ? 'paid' : 'received';
$meta = $kind === 'received'
    ? ['title' => 'Total Received', 'color' => '#059669', 'bg' => '#ECFDF5', 'icon' => '⬇️']
    : ['title' => 'Total Paid', 'color' => '#DC2626', 'bg' => '#FEF2F2', 'icon' => '⬆️'];

$transactions = [];
$res = $conn->query('SELECT id, type, amount, party_name, note, created_at FROM finance_transactions');
while ($row = $res->fetch_assoc()) {
    $transactions[] = $row;
}
$ledgerEntries = [];
$res = $conn->query('SELECT id, entry_type, amount, note, created_at FROM finance_ledger_entries');
while ($row = $res->fetch_assoc()) {
    $ledgerEntries[] = $row;
}

// "Received" = real cash in (sales + every payment collected from a
// customer). "Paid" = real cash out (purchases, expenses, and manual
// Payment Out ledger entries).
$entries = [];
foreach ($transactions as $t) {
    if ($kind === 'received' && $t['type'] === 'sale') {
        $entries[] = ['id' => 'tx' . $t['id'], 'date' => $t['created_at'], 'amount' => (float)$t['amount'], 'label' => 'Sale', 'sub' => $t['party_name'] ?: ($t['note'] ?: '')];
    }
    if ($kind === 'paid' && ($t['type'] === 'purchase' || $t['type'] === 'expense')) {
        $entries[] = ['id' => 'tx' . $t['id'], 'date' => $t['created_at'], 'amount' => (float)$t['amount'], 'label' => $t['type'] === 'purchase' ? 'Purchase' : 'Expense', 'sub' => $t['party_name'] ?: ($t['note'] ?: '')];
    }
}
foreach ($ledgerEntries as $entry) {
    if ($kind === 'received' && $entry['entry_type'] === 'credit') {
        $entries[] = ['id' => 'le' . $entry['id'], 'date' => $entry['created_at'], 'amount' => (float)$entry['amount'], 'label' => 'Payment received', 'sub' => $entry['note'] ?: ''];
    }
    if ($kind === 'paid' && $entry['entry_type'] === 'debit') {
        $entries[] = ['id' => 'le' . $entry['id'], 'date' => $entry['created_at'], 'amount' => (float)$entry['amount'], 'label' => 'Payment out', 'sub' => $entry['note'] ?: ''];
    }
}
usort($entries, fn($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));

$year = (int)date('Y');
$yearEntries = array_values(array_filter($entries, fn($e) => (int)date('Y', strtotime($e['date'])) === $year));
$yearTotal = array_sum(array_column($yearEntries, 'amount'));

$chartCounts = ['day' => 30, 'week' => 12, 'month' => 12];
$chartDatasets = [];
foreach ($chartCounts as $granularity => $count) {
    $buckets = financePeriodBuckets($granularity, $count);
    $data = [];
    foreach ($buckets as $bucket) {
        $value = 0.0;
        foreach ($entries as $e) {
            $ts = strtotime($e['date']);
            if ($ts >= $bucket['start'] && $ts < $bucket['end']) {
                $value += $e['amount'];
            }
        }
        $data[] = ['label' => $bucket['label'], 'value' => $value];
    }
    $chartDatasets[$granularity] = $data;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($meta['title']) ?> — Finance — ALL IN ONE ABROAD</title>
  <?php financeStyles(); ?>
</head>
<body>
  <?php financeTopbar('dashboard'); ?>

  <div class="wrap">
    <div class="page-head">
      <a href="finance.php" class="back">←</a>
      <h1><?= e($meta['title']) ?></h1>
    </div>

    <div style="border-radius:14px;padding:16px 18px;margin-bottom:16px;background:<?= $meta['bg'] ?>;">
      <div style="font-size:12px;font-weight:700;color:<?= $meta['color'] ?>;margin-bottom:4px;"><?= $meta['icon'] ?> <?= $year ?> total</div>
      <div style="font-size:24px;font-weight:800;color:<?= $meta['color'] ?>;"><?= fmtMoney((float)$yearTotal) ?></div>
    </div>

    <div class="panel">
      <div class="panel-body">
        <div class="g-toggle" id="totals-toggle">
          <button type="button" class="g-btn" data-g="day">Day</button>
          <button type="button" class="g-btn" data-g="week">Week</button>
          <button type="button" class="g-btn active" data-g="month">Month</button>
        </div>
        <div id="totals-chart" class="bars-wrap"></div>
      </div>
    </div>

    <h2 style="font-size:14px;font-weight:800;margin:18px 0 10px;"><?= $year ?> entries</h2>
    <?php if (count($yearEntries) === 0): ?>
      <div class="empty">
        <div class="empty-icon">💵</div>
        <p>Nothing here yet.</p>
      </div>
    <?php else: ?>
      <?php foreach ($yearEntries as $e): ?>
        <div class="feed-row">
          <div class="feed-main">
            <div class="feed-title"><?= e($e['label']) ?></div>
            <div class="feed-sub"><?= $e['sub'] ? e($e['sub']) : e(date('M j, Y', strtotime($e['date']))) ?></div>
          </div>
          <div style="font-weight:800;color:<?= $meta['color'] ?>;font-size:13.5px;"><?= fmtMoney($e['amount']) ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php financeChartScript(); ?>
  <script>
    var chartDatasets = <?= json_encode($chartDatasets) ?>;
    var chartColor = <?= json_encode($meta['color']) ?>;
    renderBars('totals-chart', chartDatasets.month, chartColor);
    attachGranularityToggle('totals-toggle', 'totals-chart', chartDatasets, function (id, data) { renderBars(id, data, chartColor); });
  </script>
</body>
</html>

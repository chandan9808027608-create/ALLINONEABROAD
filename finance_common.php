<?php
// Shared helpers, layout, and chart JS for the finance_*.php admin pages.
// Every finance_*.php page does:
//   require_once __DIR__ . '/config.php';
//   require_once __DIR__ . '/finance_common.php';
//   requireFinanceAuth();
//   $conn = connectDb();

function requireFinanceAuth(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['orders_admin'])) {
        header('Location: orders.php');
        exit;
    }
}

function e(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES);
}

function fmtMoney(float $v): string
{
    return 'Rs. ' . number_format($v, 2);
}

/** @return array<int,float> customer_id => running balance (positive = customer owes you) */
function financeCustomerBalances(mysqli $conn): array
{
    $balances = [];
    $res = $conn->query("SELECT customer_id, SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE -amount END) AS balance FROM finance_ledger_entries GROUP BY customer_id");
    while ($row = $res->fetch_assoc()) {
        $balances[(int)$row['customer_id']] = (float)$row['balance'];
    }
    return $balances;
}

/** @return array<int,string> bank_account_id => name */
function financeBankAccountNames(mysqli $conn): array
{
    $map = [];
    $res = $conn->query('SELECT id, name FROM finance_bank_accounts ORDER BY name');
    while ($row = $res->fetch_assoc()) {
        $map[(int)$row['id']] = $row['name'];
    }
    return $map;
}

/** @return array<int,string> category_id => name */
function financeCategoryNames(mysqli $conn): array
{
    $map = [];
    $res = $conn->query('SELECT id, name FROM finance_expense_categories ORDER BY name');
    while ($row = $res->fetch_assoc()) {
        $map[(int)$row['id']] = $row['name'];
    }
    return $map;
}

/**
 * Bucket boundaries for trend/cashflow charts - day (last N days), week
 * (last N weeks), or month (last N months, always ending in December of the
 * current year - matches the original app's TrendChartCard behaviour).
 * @return array<int,array{start:int,end:int,label:string}>
 */
function financePeriodBuckets(string $granularity, int $count): array
{
    $now = new DateTimeImmutable('now');
    $buckets = [];

    if ($granularity === 'day') {
        for ($i = 0; $i < $count; $i++) {
            $start = $now->modify('-' . ($count - 1 - $i) . ' days')->setTime(0, 0, 0);
            $buckets[] = ['start' => $start->getTimestamp(), 'end' => $start->modify('+1 day')->getTimestamp(), 'label' => $start->format('j M')];
        }
        return $buckets;
    }

    if ($granularity === 'week') {
        $dow = (int)$now->format('N'); // Monday = 1 ... Sunday = 7
        $thisWeekStart = $now->setTime(0, 0, 0)->modify('-' . ($dow - 1) . ' days');
        for ($i = 0; $i < $count; $i++) {
            $start = $thisWeekStart->modify('-' . (($count - 1 - $i) * 7) . ' days');
            $buckets[] = ['start' => $start->getTimestamp(), 'end' => $start->modify('+7 days')->getTimestamp(), 'label' => $start->format('j M')];
        }
        return $buckets;
    }

    $count = min($count, 12);
    $year = (int)$now->format('Y');
    for ($i = 0; $i < $count; $i++) {
        $monthNum = 12 - $count + $i + 1;
        $start = DateTimeImmutable::createFromFormat('Y-n-j H:i:s', "$year-$monthNum-1 00:00:00");
        $buckets[] = ['start' => $start->getTimestamp(), 'end' => $start->modify('+1 month')->getTimestamp(), 'label' => $start->format('M')];
    }
    return $buckets;
}

/**
 * Binds params to a prepared statement and executes it. $params is a list of
 * [typeChar, value] pairs, e.g. [['s', $name], ['i', $id]] - avoids manually
 * counting/matching mysqli's positional type-string against the value list,
 * which is easy to get subtly wrong by hand once a query has 8+ params.
 */
function bindExec(mysqli_stmt $stmt, array $params): void
{
    $types = '';
    $values = [];
    foreach ($params as [$type, $value]) {
        $types .= $type;
        $values[] = $value;
    }
    $refs = [$types];
    foreach ($values as $key => $value) {
        $refs[] = &$values[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
}

function financeTopbar(string $active): void
{
    ?>
    <div class="topbar">
      <div class="logo"><span class="logo-top">ALL IN ONE</span><span class="logo-bottom">ABROAD</span></div>
      <div class="topbar-title">Admin</div>
      <a href="orders.php" class="nav-link">Orders</a>
      <a href="products.php" class="nav-link">Products</a>
      <a href="banner.php" class="nav-link">Banner</a>
      <a href="finance.php" class="nav-link active">Finance</a>
      <a href="orders.php?logout=1" class="logout-link">Log out</a>
    </div>
    <div class="finance-subnav">
      <a href="finance.php" class="fs-link<?= $active === 'dashboard' ? ' active' : '' ?>">Dashboard</a>
      <a href="finance_customers.php" class="fs-link<?= $active === 'customers' ? ' active' : '' ?>">Customers</a>
      <a href="finance_transactions.php" class="fs-link<?= $active === 'transactions' ? ' active' : '' ?>">Transactions</a>
      <a href="finance_bank_accounts.php" class="fs-link<?= $active === 'bank' ? ' active' : '' ?>">Bank Accounts</a>
    </div>
    <?php
}

function financeStyles(): void
{
    ?>
    <style>
      * { box-sizing: border-box; margin: 0; padding: 0; }
      body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #111827; background: #f3f4f6; }

      .topbar { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 16px 28px; display: flex; align-items: center; gap: 16px; }
      .logo { line-height: 1; }
      .logo-top { display: block; font-size: 9px; font-weight: 800; letter-spacing: 0.18em; color: #6b7280; text-transform: uppercase; }
      .logo-bottom { display: block; font-size: 17px; font-weight: 900; letter-spacing: 0.04em; color: #f97316; margin-top: 2px; }
      .topbar-title { font-size: 14px; font-weight: 600; color: #6b7280; padding-left: 16px; border-left: 1px solid #e5e7eb; }
      .nav-link { font-size: 13px; font-weight: 600; color: #6b7280; padding: 8px 14px; border-radius: 8px; border: 1.5px solid #e5e7eb; transition: background 0.15s; text-decoration: none; }
      .nav-link:hover { background: #f3f4f6; }
      .nav-link.active { background: #111827; color: #fff; border-color: #111827; }
      .logout-link { margin-left: auto; font-size: 13px; font-weight: 600; color: #6b7280; padding: 8px 14px; border-radius: 8px; border: 1.5px solid #e5e7eb; transition: background 0.15s; text-decoration: none; }
      .logout-link:hover { background: #f3f4f6; }

      .finance-subnav { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 10px 28px; display: flex; gap: 8px; overflow-x: auto; }
      .fs-link { font-size: 13px; font-weight: 600; color: #6b7280; padding: 7px 14px; border-radius: 100px; border: 1.5px solid #e5e7eb; text-decoration: none; white-space: nowrap; transition: background 0.15s; }
      .fs-link:hover { background: #f3f4f6; }
      .fs-link.active { background: #f97316; color: #fff; border-color: #f97316; }

      .wrap { max-width: 1100px; margin: 0 auto; padding: 28px; }
      .wrap.narrow { max-width: 720px; }

      .page-head { display: flex; align-items: center; gap: 10px; margin-bottom: 18px; }
      .page-head a.back { color: #374151; text-decoration: none; font-size: 18px; line-height: 1; padding: 4px; }
      .page-head h1 { font-size: 18px; font-weight: 800; }

      .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
      .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
      .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }

      .tile { background: #fff; border: 1.5px solid #e5e7eb; border-radius: 14px; padding: 16px 18px; text-decoration: none; display: block; transition: border-color 0.15s; }
      .tile:hover { border-color: #d1d5db; }
      .tile-label { font-size: 12px; font-weight: 700; color: #6b7280; margin-bottom: 6px; }
      .tile-value { font-size: 18px; font-weight: 800; color: #111827; }
      .tile.green { background: #ecfdf5; border-color: #a7f3d0; }
      .tile.green .tile-label { color: #047857; }
      .tile.green .tile-value { color: #047857; }
      .tile.red { background: #fef2f2; border-color: #fecaca; }
      .tile.red .tile-label { color: #dc2626; }
      .tile.red .tile-value { color: #dc2626; }
      .tile.blue .tile-value { color: #2563eb; }

      .shortcut { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 16px 8px; background: #fff; border: 1.5px solid #e5e7eb; border-radius: 14px; text-decoration: none; text-align: center; }
      .shortcut:hover { border-color: #d1d5db; }
      .shortcut-icon { width: 44px; height: 44px; border-radius: 50%; background: #eff6ff; display: flex; align-items: center; justify-content: center; font-size: 19px; }
      .shortcut-label { font-size: 12px; font-weight: 600; color: #374151; }

      .panel { background: #fff; border: 1.5px solid #e5e7eb; border-radius: 14px; overflow: hidden; margin-bottom: 20px; }
      .panel-head { padding: 16px 20px; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
      .panel-head h2 { font-size: 15px; font-weight: 800; }
      .panel-body { padding: 20px; }

      .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: 13px; font-weight: 700; padding: 9px 16px; border-radius: 8px; border: 1.5px solid transparent; cursor: pointer; text-decoration: none; font-family: inherit; }
      .btn-primary { background: #f97316; color: #fff; }
      .btn-primary:hover { background: #ea6c0a; }
      .btn-dark { background: #111827; color: #fff; }
      .btn-dark:hover { background: #1f2937; }
      .btn-outline { background: #fff; color: #374151; border-color: #d1d5db; }
      .btn-outline:hover { background: #f9fafb; }
      .btn-danger { background: #fff; color: #dc2626; border-color: #fecaca; }
      .btn-danger:hover { background: #fef2f2; }
      .btn-green { background: #059669; color: #fff; }
      .btn-green:hover { background: #047857; }
      .btn-red { background: #dc2626; color: #fff; }
      .btn-red:hover { background: #b91c1c; }
      .btn-sm { padding: 5px 10px; font-size: 12px; }
      .btn-icon { width: 40px; height: 40px; padding: 0; border-radius: 10px; font-size: 18px; }
      .btn:disabled { opacity: 0.5; cursor: default; }

      label.field-label { display: block; font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 5px; }
      input[type=text], input[type=number], input[type=tel], input[type=date], input[type=search], input[type=password], select, textarea {
        width: 100%; padding: 10px 12px; border: 1.5px solid #e5e7eb; border-radius: 8px; font-size: 13.5px; font-family: inherit; outline: none; transition: border-color 0.2s; background: #fff; color: #111827;
      }
      input:focus, select:focus, textarea:focus { border-color: #f97316; }
      .field { margin-bottom: 14px; }
      .row { display: flex; gap: 10px; }
      .row > .field { flex: 1; margin-bottom: 14px; }
      .hint { font-size: 11.5px; color: #9ca3af; margin-top: 4px; }

      .seg { display: flex; gap: 8px; margin-bottom: 14px; }
      .seg-btn { flex: 1; position: relative; text-align: center; padding: 9px; border-radius: 100px; border: 1.5px solid #e5e7eb; background: #fff; font-size: 12.5px; font-weight: 700; color: #6b7280; cursor: pointer; text-decoration: none; }
      .seg-btn input { position: absolute; opacity: 0; pointer-events: none; }
      .seg-btn.active, .seg-btn:has(input:checked) { background: #f97316; color: #fff; border-color: #f97316; }
      .seg-btn.credit.active, .seg-btn.credit:has(input:checked) { background: #059669; border-color: #059669; }
      .seg-btn.debit.active, .seg-btn.debit:has(input:checked) { background: #dc2626; border-color: #dc2626; }

      table { width: 100%; border-collapse: collapse; }
      th { background: #f9fafb; text-align: left; font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #6b7280; padding: 11px 14px; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
      td { padding: 12px 14px; border-bottom: 1px solid #f3f4f6; font-size: 13px; vertical-align: middle; }
      tbody tr:hover { background: #fafafa; }
      tbody tr:last-child td { border-bottom: none; }
      .table-scroll { overflow-x: auto; }

      .badge { display: inline-block; font-size: 10.5px; font-weight: 700; padding: 3px 9px; border-radius: 100px; color: #fff; white-space: nowrap; }
      .badge.sale { background: #059669; }
      .badge.purchase { background: #2563eb; }
      .badge.expense { background: #dc2626; }
      .badge.credit { background: #059669; }
      .badge.debit { background: #dc2626; }
      .badge.muted { background: #6b7280; }

      .amt-green { color: #059669; font-weight: 800; }
      .amt-red { color: #dc2626; font-weight: 800; }
      .amt-blue { color: #2563eb; font-weight: 800; }

      .empty { text-align: center; padding: 56px 24px; color: #6b7280; }
      .empty-icon { font-size: 34px; margin-bottom: 10px; }

      .day-heading { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: #9ca3af; margin: 18px 0 8px; }
      .day-heading:first-child { margin-top: 0; }

      .feed-row { display: flex; align-items: center; gap: 12px; padding: 13px 16px; border: 1.5px solid #e5e7eb; border-radius: 12px; background: #fff; margin-bottom: 8px; }
      .feed-icon { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 15px; flex: none; }
      .feed-icon.g { background: #ecfdf5; }
      .feed-icon.r { background: #fef2f2; }
      .feed-icon.b { background: #eff6ff; }
      .feed-main { flex: 1; min-width: 0; }
      .feed-title { font-size: 13.5px; font-weight: 700; color: #111827; }
      .feed-sub { font-size: 11.5px; color: #9ca3af; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .feed-amt { text-align: right; }
      .feed-account { font-size: 10px; color: #9ca3af; margin-top: 2px; }
      .feed-del { color: #9ca3af; text-decoration: none; font-size: 15px; padding: 4px; }
      .feed-del:hover { color: #dc2626; }

      .item-row { border: 1.5px solid #e5e7eb; border-radius: 10px; background: #f9fafb; padding: 12px; margin-bottom: 10px; }
      .item-row-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 11.5px; font-weight: 700; color: #9ca3af; }
      .item-row-head a { color: #9ca3af; text-decoration: none; font-size: 15px; }
      .item-amt { background: #f3f4f6; border-radius: 8px; padding: 9px 12px; font-size: 13px; font-weight: 700; color: #374151; }

      .card { background: #fff; border: 1.5px solid #e5e7eb; border-radius: 14px; padding: 18px; margin-bottom: 18px; }
      .balance-card { background: #111827; border-radius: 14px; padding: 20px; margin-bottom: 18px; }
      .balance-card .bc-label { font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #9ca3af; }
      .balance-card .bc-value { font-size: 26px; font-weight: 800; margin-top: 4px; }

      .g-toggle { display: flex; gap: 8px; margin-bottom: 14px; }
      .g-btn { flex: 1; text-align: center; padding: 7px; border-radius: 100px; border: 1.5px solid #e5e7eb; background: #fff; font-size: 12px; font-weight: 700; color: #6b7280; cursor: pointer; }
      .g-btn.active { background: #111827; color: #fff; border-color: #111827; }

      .bars-wrap { }
      .bars-row { display: flex; align-items: flex-end; gap: 6px; height: 130px; }
      .bar-col { flex: 1; display: flex; align-items: flex-end; justify-content: center; height: 100%; cursor: pointer; gap: 3px; }
      .bar-col.net { align-items: center; }
      .bar { width: 100%; border-radius: 4px 4px 0 0; min-height: 2px; transition: opacity 0.1s; }
      .bar-col:hover .bar { opacity: 0.8; }
      .bars-labels { display: flex; gap: 6px; margin-top: 6px; }
      .bars-labels span { flex: 1; text-align: center; font-size: 9.5px; color: #9ca3af; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
      .bar-caption { margin-top: 10px; display: inline-block; background: #111827; color: #fff; font-size: 11.5px; font-weight: 600; padding: 5px 10px; border-radius: 8px; }
      .chart-legend { display: flex; gap: 16px; margin-top: 10px; }
      .chart-legend span { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; color: #6b7280; }
      .dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }

      .suggest-list { border: 1.5px solid #e5e7eb; border-radius: 8px; background: #fff; margin-top: -8px; margin-bottom: 12px; max-height: 180px; overflow-y: auto; }
      .suggest-list div { padding: 8px 12px; border-bottom: 1px solid #f3f4f6; cursor: pointer; font-size: 13px; }
      .suggest-list div:last-child { border-bottom: none; }
      .suggest-list div:hover { background: #f9fafb; }

      @media (max-width: 768px) {
        .grid-2, .grid-3, .grid-4 { grid-template-columns: repeat(2, 1fr); }
        .wrap { padding: 16px; }
        .row { flex-direction: column; gap: 0; }
      }
    </style>
    <?php
}

// Bar-chart / feed-toggling JS shared by finance.php, finance_transactions.php
// and finance_totals.php. Renders into plain divs (no chart library, matching
// this site's existing zero-dependency admin pages).
function financeChartScript(): void
{
    ?>
    <script>
      function fmtRs(v) { return 'Rs. ' + Math.round(v).toLocaleString(); }

      function renderBars(containerId, data, color) {
        var el = document.getElementById(containerId);
        if (!el) return;
        var max = Math.max(1, ...data.map(function (d) { return d.value; }));
        var row = document.createElement('div');
        row.className = 'bars-row';
        data.forEach(function (d, i) {
          var col = document.createElement('div');
          col.className = 'bar-col';
          var bar = document.createElement('div');
          bar.className = 'bar';
          bar.style.height = (d.value > 0 ? Math.max(3, (d.value / max) * 100) : 0) + '%';
          bar.style.background = color;
          col.appendChild(bar);
          col.addEventListener('click', function () { showCaption(containerId, d.label + ': ' + fmtRs(d.value)); });
          row.appendChild(col);
        });
        var labels = document.createElement('div');
        labels.className = 'bars-labels';
        data.forEach(function (d, i) {
          var span = document.createElement('span');
          var show = data.length <= 12 || i % Math.ceil(data.length / 8) === 0 || i === data.length - 1;
          span.textContent = show ? d.label : '';
          labels.appendChild(span);
        });
        el.innerHTML = '';
        el.appendChild(row);
        el.appendChild(labels);
      }

      function renderDualBars(containerId, data) {
        var el = document.getElementById(containerId);
        if (!el) return;
        var max = Math.max(1, ...data.map(function (d) { return Math.max(d.inAmt, d.outAmt); }));
        var row = document.createElement('div');
        row.className = 'bars-row';
        data.forEach(function (d) {
          var col = document.createElement('div');
          col.className = 'bar-col';
          var barIn = document.createElement('div');
          barIn.className = 'bar';
          barIn.style.height = (d.inAmt > 0 ? Math.max(3, (d.inAmt / max) * 100) : 0) + '%';
          barIn.style.background = '#6ee7b7';
          var barOut = document.createElement('div');
          barOut.className = 'bar';
          barOut.style.height = (d.outAmt > 0 ? Math.max(3, (d.outAmt / max) * 100) : 0) + '%';
          barOut.style.background = '#fca5a5';
          col.appendChild(barIn);
          col.appendChild(barOut);
          col.addEventListener('click', function () {
            showCaption(containerId, d.label + ' — In ' + fmtRs(d.inAmt) + ' · Out ' + fmtRs(d.outAmt));
          });
          row.appendChild(col);
        });
        var labels = document.createElement('div');
        labels.className = 'bars-labels';
        data.forEach(function (d, i) {
          var span = document.createElement('span');
          var show = data.length <= 12 || i % Math.ceil(data.length / 8) === 0 || i === data.length - 1;
          span.textContent = show ? d.label : '';
          labels.appendChild(span);
        });
        el.innerHTML = '';
        el.appendChild(row);
        el.appendChild(labels);
      }

      // Diverging chart: positive net shown as a green bar above the mid
      // baseline, negative as a red bar below it.
      function renderNetBars(containerId, data) {
        var el = document.getElementById(containerId);
        if (!el) return;
        var maxAbs = Math.max(1, ...data.map(function (d) { return Math.abs(d.net); }));
        var row = document.createElement('div');
        row.className = 'bars-row';
        row.style.position = 'relative';
        data.forEach(function (d) {
          var col = document.createElement('div');
          col.className = 'bar-col net';
          col.style.flexDirection = 'column';
          var half = document.createElement('div');
          half.style.height = '50%';
          half.style.width = '100%';
          half.style.display = 'flex';
          half.style.alignItems = 'flex-end';
          half.style.justifyContent = 'center';
          var barTop = document.createElement('div');
          barTop.className = 'bar';
          barTop.style.background = '#059669';
          barTop.style.height = (d.net > 0 ? Math.max(3, (Math.abs(d.net) / maxAbs) * 100) : 0) + '%';
          half.appendChild(barTop);
          var half2 = document.createElement('div');
          half2.style.height = '50%';
          half2.style.width = '100%';
          half2.style.display = 'flex';
          half2.style.alignItems = 'flex-start';
          half2.style.justifyContent = 'center';
          var barBot = document.createElement('div');
          barBot.className = 'bar';
          barBot.style.background = '#dc2626';
          barBot.style.borderRadius = '0 0 4px 4px';
          barBot.style.height = (d.net < 0 ? Math.max(3, (Math.abs(d.net) / maxAbs) * 100) : 0) + '%';
          half2.appendChild(barBot);
          col.appendChild(half);
          col.appendChild(half2);
          col.addEventListener('click', function () {
            showCaption(containerId, d.label + ': ' + (d.net >= 0 ? 'Net gain ' : 'Net loss ') + fmtRs(Math.abs(d.net)));
          });
          row.appendChild(col);
        });
        var labels = document.createElement('div');
        labels.className = 'bars-labels';
        data.forEach(function (d, i) {
          var span = document.createElement('span');
          var show = data.length <= 12 || i % Math.ceil(data.length / 8) === 0 || i === data.length - 1;
          span.textContent = show ? d.label : '';
          labels.appendChild(span);
        });
        el.innerHTML = '';
        el.appendChild(row);
        el.appendChild(labels);
      }

      function showCaption(containerId, text) {
        var capId = containerId + '-caption';
        var cap = document.getElementById(capId);
        if (!cap) {
          cap = document.createElement('div');
          cap.id = capId;
          cap.className = 'bar-caption';
          document.getElementById(containerId).insertAdjacentElement('afterend', cap);
        }
        cap.textContent = text;
      }

      // Wires a .g-btn[data-g] toggle row to re-render one of `datasets`
      // (keyed 'day'/'week'/'month') into `containerId` with `renderFn`.
      function attachGranularityToggle(toggleId, containerId, datasets, renderFn, extra) {
        var toggle = document.getElementById(toggleId);
        if (!toggle) return;
        var buttons = toggle.querySelectorAll('.g-btn');
        buttons.forEach(function (btn) {
          btn.addEventListener('click', function () {
            buttons.forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            renderFn(containerId, datasets[btn.dataset.g], extra);
          });
        });
      }
    </script>
    <?php
}

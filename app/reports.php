<?php
require_once __DIR__ . '/layout.php';

$m = trim($_GET['m'] ?? date('Y-m'));
$cat = trim($_GET['cat'] ?? '');

$cats = q("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

$items = q("SELECT i.id, i.code, i.name, c.name AS cat, i.unit, i.rate, i.reorder_level 
            FROM items i 
            JOIN categories c ON c.id = i.category_id " . 
            ($cat !== '' ? "WHERE c.name = ?" : "") . 
            " ORDER BY c.name ASC, i.code ASC", 
            ($cat !== '' ? [$cat] : []))->fetchAll();

// Fetch movements for month $m
$tx = q("SELECT item_id, entry_type, SUM(quantity) AS qty 
         FROM stock_entries 
         WHERE entry_date LIKE ? 
         GROUP BY item_id, entry_type", [$m . '%'])->fetchAll();

$txMap = [];
foreach ($tx as $t) {
    $txMap[$t['item_id']][$t['entry_type']] = (float)$t['qty'];
}

// Current balances
$balRows = q("SELECT id, balance FROM item_balances")->fetchAll();
$balMap = [];
foreach ($balRows as $b) {
    $balMap[$b['id']] = (float)$b['balance'];
}

$totRecv = 0;
$totIss = 0;
$totVal = 0;

$reportRows = [];
foreach ($items as $it) {
    $iid = $it['id'];
    $rin = $txMap[$iid]['IN'] ?? 0;
    $rout = $txMap[$iid]['OUT'] ?? 0;
    $balance = $balMap[$iid] ?? 0;
    $val = $balance * (float)$it['rate'];

    $totRecv += $rin;
    $totIss += $rout;
    $totVal += $val;

    $reportRows[] = [
        'id' => $iid,
        'code' => $it['code'],
        'name' => $it['name'],
        'cat' => $it['cat'],
        'unit' => $it['unit'],
        'rate' => (float)$it['rate'],
        'min' => (float)$it['reorder_level'],
        'rin' => $rin,
        'rout' => $rout,
        'balance' => $balance,
        'val' => $val
    ];
}

$extraHdr = '<button class="btn o noprint" onclick="window.print()">Print Report</button>';
top('Monthly Inventory Report', 'reports.php', $extraHdr);
?>

<div class="box pad noprint" style="margin-bottom:14px">
  <form class="row" method="get">
    <label style="width:180px">
      Select Month:
      <input type="month" name="m" value="<?= e($m) ?>" onchange="this.form.submit()">
    </label>
    <label style="min-width:200px">
      Category:
      <select name="cat" onchange="this.form.submit()">
        <option value="">All Categories</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= e($c['name']) ?>" <?= $cat === $c['name'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div style="align-self:flex-end">
      <button class="btn">View</button>
      <?php if ($cat !== ''): ?>
        <a class="btn o" href="reports.php?m=<?= e($m) ?>">Reset Category</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Summary statistics -->
<div class="stats" style="margin-bottom:16px">
  <div class="stat" style="--c:#17202A">
    <small>Report Month</small>
    <div style="font-size:22px"><?= date('F Y', strtotime($m . '-01')) ?></div>
  </div>
  <div class="stat" style="--c:#0F766E">
    <small>Total Received in Month</small>
    <div><?= qty($totRecv) ?></div>
  </div>
  <div class="stat" style="--c:#1D4ED8">
    <small>Total Issued in Month</small>
    <div><?= qty($totIss) ?></div>
  </div>
  <div class="stat" style="--c:#B45309">
    <small>Total Stock Value</small>
    <div style="font-size:22px">Rs. <?= n($totVal) ?></div>
  </div>
</div>

<!-- Report Table -->
<div class="box">
  <h2>Inventory Statement - <?= date('F Y', strtotime($m . '-01')) ?></h2>
  <table>
    <thead>
      <tr>
        <th>Product ID</th>
        <th>Product Name</th>
        <th>Category</th>
        <th>Unit</th>
        <th class="r">Received (IN)</th>
        <th class="r">Issued (OUT)</th>
        <th class="r">Current Balance</th>
        <th class="r">Rate (Rs.)</th>
        <th class="r">Stock Value (Rs.)</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($reportRows as $r): ?>
        <tr>
          <td><span class="code"><?= e($r['code']) ?></span></td>
          <td><b><?= e($r['name']) ?></b></td>
          <td><?= e($r['cat']) ?></td>
          <td><?= e($r['unit']) ?></td>
          <td class="r" style="color:#0F766E;font-weight:600"><?= $r['rin'] > 0 ? qty($r['rin']) : '-' ?></td>
          <td class="r" style="color:#1D4ED8;font-weight:600"><?= $r['rout'] > 0 ? qty($r['rout']) : '-' ?></td>
          <td class="r"><b><?= qty($r['balance']) ?></b></td>
          <td class="r"><?= n($r['rate']) ?></td>
          <td class="r" style="font-weight:700"><?= n($r['val']) ?></td>
          <td><?= st($r['balance'], $r['min']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($reportRows)): ?>
        <tr><td colspan="10" style="text-align:center;padding:24px;color:var(--mut)">No items found for this report.</td></tr>
      <?php else: ?>
        <tr style="font-weight:700;background:#FAFBFC;border-top:2px solid #C3CAD2">
          <td colspan="4">Total</td>
          <td class="r" style="color:#0F766E;font-size:15px"><?= qty($totRecv) ?></td>
          <td class="r" style="color:#1D4ED8;font-size:15px"><?= qty($totIss) ?></td>
          <td class="r">-</td>
          <td class="r">-</td>
          <td class="r" style="font-size:15px">Rs. <?= n($totVal) ?></td>
          <td></td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
bottom();


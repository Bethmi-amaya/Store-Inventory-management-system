<?php
require_once __DIR__ . '/layout.php';

$itemId = (int)($_GET['item_id'] ?? 0);
$m = trim($_GET['m'] ?? '');
$t = trim($_GET['t'] ?? '');

$items = q("SELECT i.id, i.code, i.name, c.name AS cat, i.unit 
            FROM items i 
            JOIN categories c ON c.id = i.category_id 
            ORDER BY c.name ASC, i.name ASC")->fetchAll();

if ($itemId === 0 && !empty($items)) {
    // Default to the first item if none chosen, or keep 0 for all
    $selectedItem = null;
} else {
    $selectedItem = q("SELECT i.*, c.name AS cat FROM items i JOIN categories c ON c.id = i.category_id WHERE i.id = ?", [$itemId])->fetch();
}

$w = [];
$p = [];

if ($itemId > 0) {
    $w[] = "s.item_id = ?";
    $p[] = $itemId;
}
if ($m !== '') {
    $w[] = "s.entry_date LIKE ?";
    $p[] = $m . '%';
}
if ($t === 'IN' || $t === 'OUT') {
    $w[] = "s.entry_type = ?";
    $p[] = $t;
}

$whereSql = $w ? "WHERE " . implode(' AND ', $w) : "";
$rows = q("SELECT s.*, i.code, i.name AS item_name, i.unit, c.name AS cat_name 
           FROM stock_entries s 
           JOIN items i ON i.id = s.item_id 
           JOIN categories c ON c.id = i.category_id 
           $whereSql 
           ORDER BY s.entry_date ASC, s.id ASC", $p)->fetchAll();

$totalIn = 0;
$totalOut = 0;
foreach ($rows as $r) {
    if ($r['entry_type'] === 'IN') {
        $totalIn += (float)$r['quantity'];
    } else {
        $totalOut += (float)$r['quantity'];
    }
}

$extraHdr = '';
top('Stock In / Out Ledger', 'ledger.php', $extraHdr);
?>

<div style="color:var(--mut);font-size:14px;margin-top:-10px;margin-bottom:12px">
  Track individual item stock movement and full transaction ledger
</div>

<!-- Controls bar (No search bar) -->
<div class="box pad" style="margin-bottom:14px">
  <form class="row" method="get">
    <label style="flex:2;min-width:260px">
      Select Item:
      <select name="item_id" onchange="this.form.submit()">
        <option value="0">-- All Items (Combined Ledger) --</option>
        <?php foreach ($items as $it): ?>
          <option value="<?= $it['id'] ?>" <?= $itemId === (int)$it['id'] ? 'selected' : '' ?>>
            <?= e($it['code']) ?> - <?= e($it['name']) ?> (<?= e($it['cat']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label style="width:160px">
      Month:
      <input type="month" name="m" value="<?= e($m) ?>" onchange="this.form.submit()">
    </label>
    <label style="width:150px">
      Movement Type:
      <select name="t" onchange="this.form.submit()">
        <option value="">All Movements</option>
        <option value="IN" <?= $t === 'IN' ? 'selected' : '' ?>>Received (IN)</option>
        <option value="OUT" <?= $t === 'OUT' ? 'selected' : '' ?>>Issued (OUT)</option>
      </select>
    </label>
    <?php if ($itemId > 0 || $m !== '' || $t !== ''): ?>
      <div style="align-self:flex-end">
        <a class="btn o" href="ledger.php">Reset</a>
      </div>
    <?php endif; ?>
  </form>
</div>

<?php if ($selectedItem): ?>
  <!-- Item detail header banner when specific item is selected -->
  <div style="background:#fff;border:1px solid var(--line);border-radius:12px;padding:14px 18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
    <div>
      <span class="code" style="font-size:15px"><?= e($selectedItem['code']) ?></span>
      <b style="font-size:18px;margin-left:8px"><?= e($selectedItem['name']) ?></b>
      <span style="color:var(--mut);margin-left:8px">(<?= e($selectedItem['cat']) ?>)</span>
    </div>
    <div style="display:flex;gap:12px;align-items:center">
      <span style="font-size:14px;color:var(--mut)">Current Balance:</span>
      <b style="font-size:20px;color:var(--ink)"><?= qty(bal($selectedItem['id'])) ?> <?= e($selectedItem['unit']) ?></b>
      <a class="btn s g" href="item.php?id=<?= $selectedItem['id'] ?>">Manage Item &rarr;</a>
    </div>
  </div>
<?php endif; ?>

<!-- Ledger Table -->
<div class="box">
  <table>
    <thead>
      <tr>
        <th>Date</th>
        <th>Reference</th>
        <?php if ($itemId === 0): ?>
          <th>Item</th>
        <?php endif; ?>
        <th>Source / Section</th>
        <th class="r">Received</th>
        <th class="r">Issued</th>
        <th class="r">Balance</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($itemId > 0): ?>
        <tr>
          <td style="color:var(--mut)">-</td>
          <td><i>Opening balance</i></td>
          <td style="color:var(--mut)">-</td>
          <td class="r">-</td>
          <td class="r">-</td>
          <td class="r"><b>0</b></td>
        </tr>
      <?php endif; ?>

      <?php
      $runningBal = 0;
      foreach ($rows as $r):
          $isRecv = ($r['entry_type'] === 'IN');
          $runningBal += $isRecv ? (float)$r['quantity'] : -(float)$r['quantity'];
          $pillBg = $isRecv ? '#0F766E' : '#1D4ED8';
      ?>
        <tr>
          <td><?= e($r['entry_date']) ?></td>
          <td><b><?= e($r['reference']) ?></b></td>
          <?php if ($itemId === 0): ?>
            <td>
              <span class="code"><?= e($r['code']) ?></span>
              <a href="ledger.php?item_id=<?= $r['item_id'] ?>" style="color:var(--blue);font-weight:600;margin-left:4px">
                <?= e($r['item_name']) ?>
              </a>
            </td>
          <?php endif; ?>
          <td>
            <span class="pill" style="background:<?= $pillBg ?>"><?= e($r['source']) ?></span>
          </td>
          <td class="r" style="color:#0F766E;font-weight:700">
            <?= $isRecv ? qty($r['quantity']) : '' ?>
          </td>
          <td class="r" style="color:#1D4ED8;font-weight:700">
            <?= !$isRecv ? qty($r['quantity']) : '' ?>
          </td>
          <td class="r">
            <b><?= $itemId > 0 ? qty($runningBal) : '-' ?></b>
          </td>
        </tr>
      <?php endforeach; ?>

      <?php if (empty($rows)): ?>
        <tr>
          <td colspan="<?= $itemId === 0 ? '7' : '6' ?>" style="text-align:center;padding:24px;color:var(--mut)">
            No ledger entries found for the selected criteria.
          </td>
        </tr>
      <?php else: ?>
        <tr style="font-weight:700;background:#FAFBFC;border-top:2px solid #C3CAD2">
          <td colspan="<?= $itemId === 0 ? '4' : '3' ?>">Total Movements</td>
          <td class="r" style="color:#0F766E;font-size:15px"><?= qty($totalIn) ?></td>
          <td class="r" style="color:#1D4ED8;font-size:15px"><?= qty($totalOut) ?></td>
          <td class="r" style="font-size:15px"><?= $itemId > 0 ? qty($runningBal) : '' ?></td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
bottom();

<?php
require_once __DIR__ . '/layout.php';

$err = '';
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $date = !empty($_POST['date']) ? $_POST['date'] : date('Y-m-d');
        $ref = trim($_POST['ref'] ?? '');
        $sec = trim($_POST['sec'] ?? '');
        $qtyVal = (float)($_POST['qty'] ?? 0);

        if ($itemId <= 0) throw new Exception('Please select an item.');
        if ($qtyVal <= 0) throw new Exception('Issued quantity must be greater than 0.');
        if ($ref === '') throw new Exception('Issue Note / Requisition reference is required.');
        if ($sec === '') throw new Exception('Section or Department is required.');

        $currentBal = bal($itemId);
        if ($qtyVal > $currentBal) {
            throw new Exception("Insufficient stock! Available balance is only " . qty($currentBal) . ".");
        }

        q("INSERT INTO stock_entries (entry_date, reference, item_id, entry_type, source, quantity) VALUES (?, ?, ?, 'OUT', ?, ?)",
            [$date, $ref, $itemId, $sec, $qtyVal]);

        $msg = 'Stock issued and recorded on note ' . e($ref) . '.';
    } catch (Exception $x) {
        $err = fail($x);
    }
}

$items = q("SELECT i.id, i.code, i.name, c.name AS cat, COALESCE(b.balance, 0) AS balance, i.unit 
            FROM items i 
            JOIN categories c ON c.id = i.category_id 
            LEFT JOIN item_balances b ON b.id = i.id 
            WHERE COALESCE(b.balance, 0) > 0 
            ORDER BY i.name ASC")->fetchAll();

$issues = q("SELECT s.*, i.code, i.name AS item_name, i.unit, c.name AS cat_name 
             FROM stock_entries s 
             JOIN items i ON i.id = s.item_id 
             JOIN categories c ON c.id = i.category_id 
             WHERE s.entry_type = 'OUT' 
             ORDER BY s.entry_date DESC, s.id DESC")->fetchAll();

top('Issue Notes', 'issue_notes.php');

if ($err) echo '<div class="err">' . e($err) . '</div>';
if ($msg) echo '<div class="success">' . e($msg) . '</div>';
?>

<!-- Issue Stock Form -->
<div class="box pad">
  <h2 style="padding:0 0 14px 0;margin:0;border-bottom:0">+ Issue Stock Note (Stock OUT)</h2>
  <form method="post">
    <div class="row">
      <label style="flex:2;min-width:260px">
        Select Item in Stock *
        <select name="item_id" required>
          <option value="">-- Choose Item with Available Stock --</option>
          <?php foreach ($items as $it): ?>
            <option value="<?= $it['id'] ?>">
              <?= e($it['code']) ?> - <?= e($it['name']) ?> (<?= e($it['cat']) ?> | Available: <?= qty($it['balance']) ?> <?= e($it['unit']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label style="width:160px">
        Issue Date *
        <input name="date" type="date" required value="<?= date('Y-m-d') ?>">
      </label>
      <label style="width:180px">
        Issue Note # / Req # *
        <input name="ref" required placeholder="e.g. REQ-0891, 7855685">
      </label>
      <label style="flex:1;min-width:180px">
        Section / Department *
        <select name="sec" required>
          <option value="Engineering">Engineering</option>
          <option value="Finance">Finance</option>
          <option value="Operations">Operations</option>
          <option value="Admin">Admin</option>
        </select>
      </label>
      <label style="width:140px">
        Quantity *
        <input name="qty" type="number" step="any" min="0.01" required placeholder="0">
      </label>
      <div style="align-self:flex-end">
        <button class="btn b" style="min-width:140px">+ Issue Stock</button>
      </div>
    </div>
  </form>
</div>

<!-- Issue Notes Table -->
<div class="box">
  <h2>Stock Issued History (Issue Notes)</h2>
  <table>
    <thead>
      <tr>
        <th>Date</th>
        <th>Issue Note / Ref #</th>
        <th>Product ID</th>
        <th>Product Name</th>
        <th>Category</th>
        <th>Section Issued To</th>
        <th class="r">Quantity Issued</th>
        <th class="r">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($issues as $s): ?>
        <tr>
          <td><?= e($s['entry_date']) ?></td>
          <td><b><?= e($s['reference']) ?></b></td>
          <td><span class="code"><?= e($s['code']) ?></span></td>
          <td><a href="item.php?id=<?= $s['item_id'] ?>" style="color:var(--blue);font-weight:600"><?= e($s['item_name']) ?></a></td>
          <td><?= e($s['cat_name']) ?></td>
          <td><span class="pill" style="background:#1D4ED8"><?= e($s['source']) ?></span></td>
          <td class="r" style="color:#1D4ED8;font-weight:700;font-size:15px">-<?= qty($s['quantity']) ?> <small><?= e($s['unit']) ?></small></td>
          <td class="r">
            <a class="btn s o" href="item.php?id=<?= $s['item_id'] ?>">View Ledger &rarr;</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($issues)): ?>
        <tr><td colspan="8" style="text-align:center;padding:24px;color:var(--mut)">No stock issue notes recorded yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
bottom();


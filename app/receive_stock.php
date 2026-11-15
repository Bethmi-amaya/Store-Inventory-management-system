<?php
require_once __DIR__ . '/layout.php';

$err = '';
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $date = !empty($_POST['date']) ? $_POST['date'] : date('Y-m-d');
        $ref = trim($_POST['ref'] ?? '');
        $src = trim($_POST['src'] ?? '');
        $qtyVal = (float)($_POST['qty'] ?? 0);

        if ($itemId <= 0) throw new Exception('Please select an item.');
        if ($qtyVal <= 0) throw new Exception('Received quantity must be greater than 0.');
        if ($ref === '') throw new Exception('Reference (GRN / Invoice) is required.');
        if ($src === '') throw new Exception('Source (Goods come from) is required.');

        q("INSERT INTO stock_entries (entry_date, reference, item_id, entry_type, source, quantity) VALUES (?, ?, ?, 'IN', ?, ?)",
            [$date, $ref, $itemId, $src, $qtyVal]);

        $msg = 'Stock received and recorded successfully.';
    } catch (Exception $x) {
        $err = fail($x);
    }
}

$items = q("SELECT i.id, i.code, i.name, c.name AS cat, COALESCE(b.balance, 0) AS balance, i.unit 
            FROM items i 
            JOIN categories c ON c.id = i.category_id 
            LEFT JOIN item_balances b ON b.id = i.id 
            ORDER BY i.name ASC")->fetchAll();

$recent = q("SELECT s.*, i.code, i.name AS item_name, i.unit, c.name AS cat_name 
             FROM stock_entries s 
             JOIN items i ON i.id = s.item_id 
             JOIN categories c ON c.id = i.category_id 
             WHERE s.entry_type = 'IN' 
             ORDER BY s.entry_date DESC, s.id DESC 
             LIMIT 50")->fetchAll();

top('Receive Stock', 'receive_stock.php');

if ($err) echo '<div class="err">' . e($err) . '</div>';
if ($msg) echo '<div class="success">' . e($msg) . '</div>';
?>

<!-- Receive Stock Form -->
<div class="box pad">
  <h2 style="padding:0 0 14px 0;margin:0;border-bottom:0">+ Record Goods Receipt (Stock IN)</h2>
  <form method="post">
    <div class="row">
      <label style="flex:2;min-width:260px">
        Select Item *
        <select name="item_id" required>
          <option value="">-- Choose Item --</option>
          <?php foreach ($items as $it): ?>
            <option value="<?= $it['id'] ?>">
              <?= e($it['code']) ?> - <?= e($it['name']) ?> (<?= e($it['cat']) ?> | Current Bal: <?= qty($it['balance']) ?> <?= e($it['unit']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label style="width:160px">
        Receipt Date *
        <input name="date" type="date" required value="<?= date('Y-m-d') ?>">
      </label>
      <label style="width:180px">
        Reference (GRN / Bill) *
        <input name="ref" required placeholder="e.g. GRN-0412">
      </label>
      <label style="flex:1;min-width:180px">
        Source (Goods from) *
        <input name="src" list="srcList" required placeholder="Colombo Port / Shop / Supplier">
        <datalist id="srcList">
          <option value="Colombo Port">
          <option value="Shop">
          <option value="Local Supplier">
          <option value="Central Store">
        </datalist>
      </label>
      <label style="width:140px">
        Quantity *
        <input name="qty" type="number" step="any" min="0.01" required placeholder="0">
      </label>
      <div style="align-self:flex-end">
        <button class="btn g" style="min-width:140px">+ Add to Stock</button>
      </div>
    </div>
  </form>
</div>

<!-- Recent Stock Receipts -->
<div class="box">
  <h2>Recent Stock Receipts (Stock IN)</h2>
  <table>
    <thead>
      <tr>
        <th>Date</th>
        <th>GRN / Reference</th>
        <th>Product ID</th>
        <th>Product Name</th>
        <th>Category</th>
        <th>Source</th>
        <th class="r">Quantity Received</th>
        <th class="r">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td><?= e($r['entry_date']) ?></td>
          <td><b><?= e($r['reference']) ?></b></td>
          <td><span class="code"><?= e($r['code']) ?></span></td>
          <td><a href="item.php?id=<?= $r['item_id'] ?>" style="color:var(--blue);font-weight:700"><?= e($r['item_name']) ?></a></td>
          <td><?= e($r['cat_name']) ?></td>
          <td><span class="pill" style="background:#0F766E"><?= e($r['source']) ?></span></td>
          <td class="r" style="color:#0F766E;font-weight:700;font-size:15px">+<?= qty($r['quantity']) ?> <small><?= e($r['unit']) ?></small></td>
          <td class="r">
            <a class="btn s o" href="item.php?id=<?= $r['item_id'] ?>">View Ledger &rarr;</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($recent)): ?>
        <tr><td colspan="8" style="text-align:center;padding:24px;color:var(--mut)">No stock receipts recorded yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
bottom();


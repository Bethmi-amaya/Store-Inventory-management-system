<?php
require_once __DIR__ . '/layout.php';

$id = (int)($_GET['id'] ?? 0);
$it = q("SELECT i.*, c.name AS cat, c.code AS cat_code 
         FROM items i 
         JOIN categories c ON c.id = i.category_id 
         WHERE i.id = ?", [$id])->fetch();

if (!$it) {
    go('categories.php');
}

$err = '';
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $a = $_POST['a'] ?? '';
    global $pdo;
    if ($pdo === null) {
        $pdo = db();
    }

    try {
        $pdo->beginTransaction();

        if ($a === 'entry') {
            $eid = (int)($_POST['eid'] ?? 0);
            $qn = (float)($_POST['qty'] ?? 0);
            $date = !empty($_POST['date']) ? $_POST['date'] : date('Y-m-d');
            $ref = trim($_POST['ref'] ?? '');
            $type = ($_POST['type'] ?? '') === 'OUT' ? 'OUT' : 'IN';
            $src = trim($_POST['src'] ?? '');

            if ($qn <= 0) throw new Exception('Quantity must be greater than zero.');
            if ($ref === '') throw new Exception('Reference is required.');
            if ($src === '') throw new Exception('Source or Section is required.');

            if ($eid) {
                $stmt = $pdo->prepare("UPDATE stock_entries SET entry_date = ?, reference = ?, entry_type = ?, source = ?, quantity = ? WHERE id = ? AND item_id = ?");
                $stmt->execute([$date, $ref, $type, $src, $qn, $eid, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO stock_entries (entry_date, reference, entry_type, source, quantity, item_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$date, $ref, $type, $src, $qn, $id]);
            }

            if (bal($id) < 0) {
                throw new Exception('Stock balance cannot be negative. Please check the quantity.');
            }
            $msg = $eid ? 'Entry updated successfully.' : 'Stock movement recorded successfully.';
        } elseif ($a === 'delentry') {
            $eid = (int)($_POST['eid'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM stock_entries WHERE id = ? AND item_id = ?");
            $stmt->execute([$eid, $id]);
            if (bal($id) < 0) {
                throw new Exception('Cannot delete this entry because stock balance would become negative.');
            }
            $msg = 'Entry deleted.';
        } elseif ($a === 'edit') {
            $name = trim($_POST['name'] ?? '');
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $unit = trim($_POST['unit'] ?? 'pcs') ?: 'pcs';
            $rack = trim($_POST['rack'] ?? '');
            $rate = (float)($_POST['rate'] ?? 0);
            $min = (float)($_POST['min'] ?? 10);

            if ($name === '' || $code === '') throw new Exception('Product ID and Product Name cannot be empty.');

            $stmt = $pdo->prepare("UPDATE items SET code = ?, name = ?, unit = ?, rack_no = ?, rate = ?, reorder_level = ? WHERE id = ?");
            $stmt->execute([$code, $name, $unit, $rack, $rate, $min, $id]);
            $msg = 'Item details updated.';
        } elseif ($a === 'delitem') {
            $stmt = $pdo->prepare("DELETE FROM stock_entries WHERE item_id = ?");
            $stmt->execute([$id]);
            $stmt2 = $pdo->prepare("DELETE FROM items WHERE id = ?");
            $stmt2->execute([$id]);
            $pdo->commit();
            go('inventory.php');
        }

        $pdo->commit();
        // Refresh item data after commit
        $it = q("SELECT i.*, c.name AS cat, c.code AS cat_code FROM items i JOIN categories c ON c.id = i.category_id WHERE i.id = ?", [$id])->fetch();
    } catch (Exception $x) {
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $err = fail($x);
    }
}

$editEntry = isset($_GET['edit']) ? q("SELECT * FROM stock_entries WHERE id = ? AND item_id = ?", [(int)$_GET['edit'], $id])->fetch() : null;
$entries = q("SELECT * FROM stock_entries WHERE item_id = ? ORDER BY entry_date ASC, id ASC", [$id])->fetchAll();

$totalIn = 0;
$totalOut = 0;
foreach ($entries as $x) {
    if ($x['entry_type'] === 'IN') {
        $totalIn += (float)$x['quantity'];
    } else {
        $totalOut += (float)$x['quantity'];
    }
}
$currentBalance = $totalIn - $totalOut;

$extraHdr = '<a class="btn o" href="inventory.php?cat=' . urlencode($it['cat']) . '">&larr; Back to ' . e($it['cat']) . '</a>';

top($it['name'], 'categories.php', $extraHdr);

if ($err) echo '<div class="err">' . e($err) . '</div>';
if ($msg) echo '<div class="success">' . e($msg) . '</div>';
?>

<!-- Item Overview Header -->
<div style="margin-top:-6px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
  <span class="code" style="font-size:15px;padding:4px 10px"><?= e($it['code']) ?></span>
  <span style="color:var(--mut)">Category:</span>
  <a href="inventory.php?cat=<?= urlencode($it['cat']) ?>" style="color:var(--blue);font-weight:600"><b><?= e($it['cat']) ?></b></a>
  <span style="color:var(--mut)">&middot; Unit: <b><?= e($it['unit']) ?></b></span>
  <span style="color:var(--mut)">&middot; Rack: <b><?= e($it['rack_no'] ?? '-') ?></b></span>
  <span style="color:var(--mut)">&middot; Rate: <b>Rs. <?= n($it['rate'] ?? 0.00) ?></b></span>
</div>

<!-- Stock summary cards -->
<div class="stats" style="margin-top:6px">
  <div class="stat" style="--c:#17202A">
    <small>Stock Balance Now</small>
    <div><?= qty($currentBalance) ?> <span style="font-size:16px;font-weight:400;color:var(--mut)"><?= e($it['unit']) ?></span></div>
  </div>
  <div class="stat" style="--c:#0F766E">
    <small>Total Received (IN)</small>
    <div><?= qty($totalIn) ?></div>
  </div>
  <div class="stat" style="--c:#1D4ED8">
    <small>Total Issued (OUT)</small>
    <div><?= qty($totalOut) ?></div>
  </div>
  <div class="stat" style="--c:#6B7785">
    <small>Reorder Level</small>
    <div><?= qty($it['reorder_level']) ?></div>
  </div>
</div>

<!-- Add or Edit Transaction Entry -->
<div class="box pad">
  <h2 style="padding:0 0 14px 0;margin:0;border-bottom:0"><?= $editEntry ? 'Update Transaction Entry' : '+ Add Stock Movement' ?></h2>
  <form method="post">
    <input type="hidden" name="a" value="entry">
    <input type="hidden" name="eid" value="<?= (int)($editEntry['id'] ?? 0) ?>">
    <div class="row">
      <label style="width:130px">
        Type
        <select name="type">
          <option value="IN" <?= ($editEntry['entry_type'] ?? '') === 'IN' ? 'selected' : '' ?>>Received (IN)</option>
          <option value="OUT" <?= ($editEntry['entry_type'] ?? '') === 'OUT' ? 'selected' : '' ?>>Issued (OUT)</option>
        </select>
      </label>
      <label style="width:160px">
        Date
        <input name="date" type="date" required value="<?= e($editEntry['entry_date'] ?? date('Y-m-d')) ?>">
      </label>
      <label style="flex:1;min-width:180px">
        Reference
        <input name="ref" required placeholder="GRN / Bill / Req #" value="<?= e($editEntry['reference'] ?? '') ?>">
      </label>
      <label style="flex:1;min-width:220px">
        Source / Section
        <input name="src" required placeholder="Colombo Port, Shop, Plumbing, etc." value="<?= e($editEntry['source'] ?? '') ?>">
      </label>
      <label style="width:140px">
        Quantity
        <input name="qty" type="number" step="any" min="0.01" required value="<?= e($editEntry ? qty($editEntry['quantity']) : '') ?>">
      </label>
      <div style="align-self:flex-end">
        <button class="btn g"><?= $editEntry ? 'Update Entry' : '+ Save Entry' ?></button>
        <?php if ($editEntry): ?>
          <a class="btn o" href="item.php?id=<?= $id ?>">Cancel</a>
        <?php endif; ?>
      </div>
    </div>
  </form>
</div>

<!-- Stock In / Out Ledger Table -->
<div class="box">
  <h2>Stock In / Out History</h2>
  <table>
    <thead>
      <tr>
        <th>Date</th>
        <th>Reference</th>
        <th>Source / Section</th>
        <th class="r">Received</th>
        <th class="r">Issued</th>
        <th class="r">Balance</th>
        <th class="r">Action</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td style="color:var(--mut)">-</td>
        <td><i>Opening balance</i></td>
        <td style="color:var(--mut)">-</td>
        <td class="r">-</td>
        <td class="r">-</td>
        <td class="r"><b>0</b></td>
        <td></td>
      </tr>
      <?php
      $runningBal = 0;
      foreach ($entries as $x):
          $isRecv = ($x['entry_type'] === 'IN');
          $runningBal += $isRecv ? (float)$x['quantity'] : -(float)$x['quantity'];
          $pillBg = $isRecv ? '#0F766E' : '#1D4ED8';
      ?>
        <tr>
          <td><?= e($x['entry_date']) ?></td>
          <td><b><?= e($x['reference']) ?></b></td>
          <td><span class="pill" style="background:<?= $pillBg ?>"><?= e($x['source']) ?></span></td>
          <td class="r" style="color:#0F766E;font-weight:700"><?= $isRecv ? qty($x['quantity']) : '' ?></td>
          <td class="r" style="color:#1D4ED8;font-weight:700"><?= !$isRecv ? qty($x['quantity']) : '' ?></td>
          <td class="r"><b><?= qty($runningBal) ?></b></td>
          <td class="r" style="white-space:nowrap">
            <a class="btn s o" href="?id=<?= $id ?>&edit=<?= $x['id'] ?>">Edit</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this transaction entry?')">
              <input type="hidden" name="a" value="delentry">
              <input type="hidden" name="eid" value="<?= $x['id'] ?>">
              <button class="btn s d">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($entries)): ?>
        <tr><td colspan="7" style="text-align:center;padding:20px;color:var(--mut)">No stock movements recorded yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Edit Item Accordion -->
<details class="box">
  <summary class="pad" style="cursor:pointer;font-weight:700;font-size:15px;background:#FAFBFC;border-bottom:1px solid #E8EBEF">
    Edit Product Details
  </summary>
  <form method="post" class="pad">
    <input type="hidden" name="a" value="edit">
    <div class="grid">
      <label>
        Product ID
        <input name="code" required value="<?= e($it['code']) ?>">
      </label>
      <label>
        Product Name
        <input name="name" required value="<?= e($it['name']) ?>">
      </label>
      <label>
        Unit
        <input name="unit" required value="<?= e($it['unit']) ?>">
      </label>
      <label>
        Rack No
        <input name="rack" value="<?= e($it['rack_no'] ?? '') ?>">
      </label>
      <label>
        Rate / Price (Rs.)
        <input name="rate" type="number" step="0.01" min="0" value="<?= e($it['rate'] ?? 0.00) ?>">
      </label>
      <label>
        Reorder Level
        <input name="min" type="number" step="any" min="0" value="<?= e($it['reorder_level']) ?>">
      </label>
    </div>
    <div style="margin-top:16px;display:flex;gap:12px;align-items:center">
      <button class="btn">Save Changes</button>
    </div>
  </form>
  <div class="pad" style="border-top:1px solid #E8EBEF;background:#FFF5F5">
    <form method="post" onsubmit="return confirm('Permanently delete this product and ALL its stock history? This cannot be undone!')">
      <input type="hidden" name="a" value="delitem">
      <button class="btn d">Delete Product</button>
      <span style="color:var(--mut);font-size:13px;margin-left:10px">Permanently remove this item and all transaction history.</span>
    </form>
  </div>
</details>

<?php
bottom();
<?php
require_once __DIR__ . '/layout.php';

$err = '';
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $a = $_POST['a'] ?? '';
    try {
        if ($a === 'del') {
            $oid = (int)($_POST['id'] ?? 0);
            q("DELETE FROM orders WHERE id = ?", [$oid]);
            $msg = 'Order removed.';
        } elseif ($a === 'status') {
            $oid = (int)($_POST['id'] ?? 0);
            $newSt = trim($_POST['status'] ?? '');
            q("UPDATE orders SET status = ? WHERE id = ?", [$newSt, $oid]);
            $msg = 'Order status updated.';
        } else {
            $itemId = (int)($_POST['item_id'] ?? 0);
            $sec = trim($_POST['section_name'] ?? '');
            $qtyVal = (int)($_POST['qty'] ?? 0);
            $type = ($_POST['type'] ?? '') === 'blue' ? 'blue' : 'red';
            $status = trim($_POST['status'] ?? ($type === 'red' ? 'To buy' : 'Issued'));
            $orderDate = !empty($_POST['date']) ? $_POST['date'] : date('Y-m-d');

            if ($itemId <= 0) throw new Exception('Please select an item.');
            if ($qtyVal <= 0) throw new Exception('Quantity must be greater than zero.');
            if ($sec === '') throw new Exception('Section name is required.');

            q("INSERT INTO orders (item_id, section_name, qty, type, status, order_date) VALUES (?, ?, ?, ?, ?, ?)",
                [$itemId, $sec, $qtyVal, $type, $status, $orderDate]);

            $msg = 'Order placed successfully.';
        }
    } catch (Exception $x) {
        $err = fail($x);
    }
}

$items = q("SELECT i.id, i.code, i.name, c.name AS cat, i.unit 
            FROM items i 
            JOIN categories c ON c.id = i.category_id 
            ORDER BY i.name ASC")->fetchAll();

$orders = q("SELECT o.*, i.code, i.name AS item_name, i.unit, c.name AS cat_name 
             FROM orders o 
             JOIN items i ON i.id = o.item_id 
             JOIN categories c ON c.id = i.category_id 
             ORDER BY o.order_date DESC, o.id DESC")->fetchAll();

top('Orders', 'orders.php');

if ($err) echo '<div class="err">' . e($err) . '</div>';
if ($msg) echo '<div class="success">' . e($msg) . '</div>';
?>

<!-- Add Order Form -->
<div class="box pad">
  <h2 style="padding:0 0 14px 0;margin:0;border-bottom:0">+ Create New Order / Requisition</h2>
  <form method="post">
    <div class="row">
      <label style="flex:2;min-width:240px">
        Select Item *
        <select name="item_id" required>
          <option value="">-- Choose Item --</option>
          <?php foreach ($items as $it): ?>
            <option value="<?= $it['id'] ?>">
              <?= e($it['code']) ?> - <?= e($it['name']) ?> (<?= e($it['cat']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label style="flex:1;min-width:180px">
        Section Name *
        <select name="section_name" required>
          <option value="Engineering">Engineering</option>
          <option value="Finance">Finance</option>
          <option value="Operations">Operations</option>
          <option value="Admin">Admin</option>
        </select>
      </label>
      <label style="width:120px">
        Quantity *
        <input name="qty" type="number" min="1" required placeholder="1">
      </label>
      <label style="width:150px">
        Order Type *
        <select name="type">
          <option value="red">Red (To Buy)</option>
          <option value="blue">Blue (Issued)</option>
        </select>
      </label>
      <label style="width:150px">
        Status *
        <select name="status">
          <option value="To buy">To buy</option>
          <option value="Ordered">Ordered</option>
          <option value="Received">Received</option>
          <option value="Issued">Issued</option>
        </select>
      </label>
      <label style="width:150px">
        Date *
        <input name="date" type="date" required value="<?= date('Y-m-d') ?>">
      </label>
      <div style="align-self:flex-end">
        <button class="btn g" style="min-width:130px">+ Save Order</button>
      </div>
    </div>
  </form>
</div>

<!-- Orders Table -->
<div class="box">
  <h2>All Orders (<?= count($orders) ?>)</h2>
  <table>
    <thead>
      <tr>
        <th>Date</th>
        <th>Section</th>
        <th>Product ID</th>
        <th>Product Name</th>
        <th class="r">Quantity</th>
        <th>Type</th>
        <th>Status</th>
        <th class="r">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $o): 
        $isRed = ($o['type'] === 'red');
        $pillBg = $isRed ? '#B42318' : '#1D4ED8';
      ?>
        <tr>
          <td><?= e($o['order_date']) ?></td>
          <td><b><?= e($o['section_name'] ?: 'General') ?></b></td>
          <td><span class="code"><?= e($o['code']) ?></span></td>
          <td><a href="item.php?id=<?= $o['item_id'] ?>" style="color:var(--blue);font-weight:600"><?= e($o['item_name']) ?></a></td>
          <td class="r" style="font-weight:700"><?= (int)$o['qty'] ?> <small><?= e($o['unit']) ?></small></td>
          <td>
            <span class="pill" style="background:<?= $pillBg ?>">
              <?= $isRed ? 'To Buy' : 'Issued' ?>
            </span>
          </td>
          <td>
            <form method="post" style="display:inline">
              <input type="hidden" name="a" value="status">
              <input type="hidden" name="id" value="<?= $o['id'] ?>">
              <select name="status" onchange="this.form.submit()" style="min-height:32px;padding:2px 8px;font-size:13px;border-radius:6px">
                <?php foreach (['To buy', 'Ordered', 'Received', 'Issued'] as $st): ?>
                  <option value="<?= $st ?>" <?= $o['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td class="r">
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this order?')">
              <input type="hidden" name="a" value="del">
              <input type="hidden" name="id" value="<?= $o['id'] ?>">
              <button class="btn s d">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($orders)): ?>
        <tr><td colspan="8" style="text-align:center;padding:24px;color:var(--mut)">No orders found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
bottom();


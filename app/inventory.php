<?php
require_once __DIR__ . '/layout.php';

$err = '';
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $catId = (int)($_POST['cat'] ?? 0);
        $unit = trim($_POST['unit'] ?? 'pcs') ?: 'pcs';
        $rack = trim($_POST['rack'] ?? '');
        $rate = (float)($_POST['rate'] ?? 0);
        $min = (float)($_POST['min'] ?? 10);
        $r = (float)($_POST['recv'] ?? 0);
        $i = (float)($_POST['iss'] ?? 0);

        if ($code === '' || $name === '') {
            throw new Exception('Product ID and Product Name are required.');
        }
        if ($catId <= 0) {
            throw new Exception('Please select a valid Category.');
        }
        if ($i > $r) {
            throw new Exception('Issued quantity cannot exceed received quantity.');
        }

        global $pdo;
        if ($pdo === null) {
            $pdo = db();
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO items (code, name, category_id, unit, rack_no, rate, reorder_level) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$code, $name, $catId, $unit, $rack, $rate, $min]);
        $id = (int)$pdo->lastInsertId();

        $entryDate = !empty($_POST['date']) ? $_POST['date'] : date('Y-m-d');
        $ref = trim($_POST['ref'] ?? '') ?: 'OPENING';
        $src = trim($_POST['src'] ?? '') ?: 'Opening Balance';

        if ($r > 0) {
            $stmtIn = $pdo->prepare("INSERT INTO stock_entries (entry_date, reference, item_id, entry_type, source, quantity) VALUES (?, ?, ?, 'IN', ?, ?)");
            $stmtIn->execute([$entryDate, $ref, $id, $src, $r]);
        }
        if ($i > 0) {
            $stmtOut = $pdo->prepare("INSERT INTO stock_entries (entry_date, reference, item_id, entry_type, source, quantity) VALUES (?, ?, ?, 'OUT', ?, ?)");
            $stmtOut->execute([$entryDate, $ref, $id, trim($_POST['sec'] ?? '') ?: 'Initial Issue', $i]);
        }

        $pdo->commit();
        go("item.php?id=$id");
    } catch (Exception $x) {
        if (isset($pdo) && $pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $err = fail($x);
    }
}

$cat = trim($_GET['cat'] ?? '');
$stt = trim($_GET['st'] ?? '');

$w = [];
$p = [];

if ($cat !== '') {
    $w[] = "category = ?";
    $p[] = $cat;
}
if ($stt === 'low') $w[] = "balance > 0 AND balance <= reorder_level";
if ($stt === 'out') $w[] = "balance <= 0";
if ($stt === 'ok') $w[] = "balance > reorder_level";

$sql = "SELECT * FROM item_balances" . ($w ? " WHERE " . implode(' AND ', $w) : "") . " ORDER BY code ASC";
$rows = q($sql, $p)->fetchAll();

$cats = q("SELECT c.id, c.name, c.code, c.unit, c.default_reorder, (SELECT COUNT(*) FROM items WHERE category_id = c.id) AS cnt 
           FROM categories c ORDER BY c.name ASC")->fetchAll();

$title = $cat ? "Category: " . $cat : "Inventory Items";

$currentCat = $cat !== '' ? q("SELECT * FROM categories WHERE name = ?", [$cat])->fetch() : null;
$extraHeader = '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">';
$extraHeader .= '<a class="btn g" href="#addForm" onclick="document.getElementById(\'addForm\').open=true;">+ Add Item</a>';
if ($currentCat) {
    $extraHeader .= '<button type="button" class="btn o" onclick="openEditCatModal(' . htmlspecialchars(json_encode($currentCat), ENT_QUOTES) . ')">Edit Category</button>';
    $extraHeader .= '<button type="button" class="btn d" onclick="deleteThisCategory(' . (int)$currentCat['id'] . ', \'' . e(addslashes($currentCat['name'])) . '\')">Delete Category</button>';
}
$extraHeader .= '</div>';

top($title, 'categories.php', $extraHeader);

if ($err) echo '<div class="err">' . e($err) . '</div>';
if ($msg) echo '<div class="success">' . e($msg) . '</div>';
?>

<!-- Filter Bar (No search bar) -->
<div class="box pad" style="margin-bottom:14px">
  <form class="row" method="get">
    <label style="min-width:200px">
      Filter by Category
      <select name="cat" onchange="this.form.submit()">
        <option value="">All Categories</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= e($c['name']) ?>" <?= $cat === $c['name'] ? 'selected' : '' ?>><?= e($c['name']) ?> (<?= e($c['code']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label style="width:160px">
      Stock Status
      <select name="st" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <option value="ok" <?= $stt === 'ok' ? 'selected' : '' ?>>In Stock</option>
        <option value="low" <?= $stt === 'low' ? 'selected' : '' ?>>Low Stock</option>
        <option value="out" <?= $stt === 'out' ? 'selected' : '' ?>>Out of Stock</option>
      </select>
    </label>
    <?php if ($cat !== '' || $stt !== ''): ?>
      <div style="align-self:flex-end">
        <a class="btn o" href="inventory.php">Reset Filters</a>
      </div>
    <?php endif; ?>
  </form>
</div>

<!-- Add Item Form with Product ID and Product Name -->
<details class="box" id="addForm" style="margin-bottom:14px">
  <summary class="pad" style="cursor:pointer;font-weight:700;font-size:16px;background:#FAFBFC;border-bottom:1px solid #E8EBEF">
    + Add Item (Product ID & Product Name)
  </summary>
  <form method="post" class="pad">
    <div style="font-weight:700;margin-bottom:12px;color:var(--ink)">1. Item Information</div>
    <div class="grid">
      <label>
        Product ID *
        <input name="code" id="code" required placeholder="e.g. NUT-003, AS07">
      </label>
      <label>
        Product Name *
        <input name="name" id="name" required placeholder="e.g. Canvas Gloves, Nut M8">
      </label>
      <label>
        Category *
        <select name="cat" id="catSel" required>
          <option value="">-- Select Category --</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= $c['id'] ?>" 
                    data-u="<?= e($c['unit']) ?>" 
                    data-m="<?= (int)$c['default_reorder'] ?>" 
                    data-c="<?= e($c['code'] . '-' . str_pad($c['cnt'] + 1, 3, '0', STR_PAD_LEFT)) ?>"
                    <?= $cat === $c['name'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?> (<?= e($c['code']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Unit *
        <input name="unit" id="unit" value="pcs" required placeholder="pcs, L, kg">
      </label>
      <label>
        Rack No
        <input name="rack" placeholder="e.g. A1, Shelf-2">
      </label>
      <label>
        Rate / Price (Rs.)
        <input name="rate" type="number" step="0.01" min="0" value="0.00">
      </label>
      <label>
        Reorder Level *
        <input name="min" id="min" type="number" step="any" min="0" value="10" required>
      </label>
    </div>

    <div style="font-weight:700;margin:18px 0 10px 0;color:var(--ink);border-top:1px solid #E8EBEF;padding-top:14px">
      2. Initial Stock Entry (Optional)
    </div>
    <div class="grid">
      <label>
        Date
        <input name="date" type="date" value="<?= date('Y-m-d') ?>">
      </label>
      <label>
        Reference (GRN / Bill No)
        <input name="ref" placeholder="e.g. GRN-0101">
      </label>
      <label>
        Goods Source
        <input name="src" list="srcList" placeholder="Colombo Port, Shop, Supplier">
        <datalist id="srcList">
          <option value="Colombo Port">
          <option value="Shop">
          <option value="Local Supplier">
        </datalist>
      </label>
      <label>
        Quantity Received
        <input name="recv" type="number" step="any" min="0" value="0">
      </label>
      <label>
        Quantity Issued
        <input name="iss" type="number" step="any" min="0" value="0">
      </label>
      <label>
        Issued To (Section)
        <select name="sec">
          <option value="">-- None / Select Section --</option>
          <option value="Engineering">Engineering</option>
          <option value="Finance">Finance</option>
          <option value="Operations">Operations</option>
          <option value="Admin">Admin</option>
        </select>
      </label>
    </div>

    <div style="margin-top:16px">
      <button class="btn g" style="font-size:15px;padding:0 24px">Save Item</button>
    </div>
  </form>
</details>

<script>
const cSel = document.getElementById('catSel');
if (cSel) {
  function updateFromCat() {
    const o = cSel.selectedOptions[0];
    if (o && o.dataset.c) {
      if (!document.getElementById('code').value) document.getElementById('code').value = o.dataset.c;
      if (o.dataset.u) document.getElementById('unit').value = o.dataset.u;
      if (o.dataset.m) document.getElementById('min').value = o.dataset.m;
    }
  }
  cSel.addEventListener('change', updateFromCat);
  if (cSel.value) updateFromCat();
}
</script>

<div class="box">
  <h2>Goods & Items (<?= count($rows) ?>)</h2>
  <table>
    <thead>
      <tr>
        <th>Product ID</th>
        <th>Product Name</th>
        <th>Category</th>
        <th>Rack</th>
        <th class="r">Received</th>
        <th class="r">Issued</th>
        <th class="r">Stock Balance</th>
        <th class="r">Rate (Rs.)</th>
        <th>Status</th>
        <th class="r">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><span class="code"><?= e($r['code']) ?></span></td>
          <td>
            <a href="item.php?id=<?= $r['id'] ?>" style="color:var(--blue);font-weight:700;text-decoration:none">
              <?= e($r['name']) ?>
            </a>
          </td>
          <td><?= e($r['category']) ?></td>
          <td><?= e($r['rack_no'] ?: '-') ?></td>
          <td class="r"><?= qty($r['total_received']) ?></td>
          <td class="r"><?= qty($r['total_issued']) ?></td>
          <td class="r"><b><?= qty($r['balance']) ?></b> <small><?= e($r['unit']) ?></small></td>
          <td class="r"><?= n($r['rate']) ?></td>
          <td><?= st($r['balance'], $r['reorder_level']) ?></td>
          <td class="r">
            <a class="btn s o" href="item.php?id=<?= $r['id'] ?>">View Ledger &rarr;</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="10" style="text-align:center;padding:24px;color:var(--mut)">No items found in this selection.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Modal: Edit Category Dialog for Inventory View -->
<dialog id="editCatInvModal" style="border:0;border-radius:14px;padding:24px;width:min(440px,94vw);box-shadow:0 12px 36px rgba(0,0,0,0.25)">
  <form id="editCatInvForm" onsubmit="saveEditedCat(event)">
    <input type="hidden" id="editInvCatId" value="0">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h2 style="margin:0;font-size:18px;color:#17202A">Edit Category</h2>
      <button type="button" onclick="document.getElementById('editCatInvModal').close()" style="border:0;background:none;font-size:22px;cursor:pointer;color:var(--mut)">&times;</button>
    </div>
    <label style="margin-bottom:12px">
      Category Name *
      <input id="editInvCatName" required placeholder="Category name">
    </label>
    <label style="margin-bottom:12px">
      Category ID / Code *
      <input id="editInvCatCode" required maxlength="10" placeholder="Code" style="text-transform:uppercase">
    </label>
    <label style="margin-bottom:18px">
      Description (optional)
      <input id="editInvCatDesc" placeholder="Brief description">
    </label>
    <div style="display:flex;justify-content:flex-end;gap:10px">
      <button type="button" class="btn o" onclick="document.getElementById('editCatInvModal').close()">Cancel</button>
      <button type="submit" class="btn g">Save Changes</button>
    </div>
  </form>
</dialog>

<script>
function openEditCatModal(cat) {
  if (!cat) return;
  document.getElementById('editInvCatId').value = cat.id;
  document.getElementById('editInvCatName').value = cat.name;
  document.getElementById('editInvCatCode').value = cat.code;
  document.getElementById('editInvCatDesc').value = cat.description || '';
  document.getElementById('editCatInvModal').showModal();
}

async function saveEditedCat(e) {
  e.preventDefault();
  const id = document.getElementById('editInvCatId').value;
  const name = document.getElementById('editInvCatName').value.trim();
  const code = document.getElementById('editInvCatCode').value.trim().toUpperCase();
  const desc = document.getElementById('editInvCatDesc').value.trim();

  try {
    const fd = new FormData();
    fd.append('a', 'save');
    fd.append('id', id);
    fd.append('name', name);
    fd.append('code', code);
    fd.append('description', desc);

    const res = await fetch('category_action.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) {
      alert('Error: ' + (data.error || 'Failed to save category.'));
      return;
    }
    window.location.href = 'inventory.php?cat=' + encodeURIComponent(name);
  } catch (err) {
    alert('Failed to update category: ' + err.message);
  }
}

async function deleteThisCategory(id, name) {
  if (!confirm(`Are you sure you want to delete category "${name}"?\n\nThis will also permanently delete all items in this category!`)) {
    return;
  }
  try {
    const fd = new FormData();
    fd.append('a', 'del');
    fd.append('id', id);
    const res = await fetch('category_action.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) {
      alert('Error: ' + (data.error || 'Failed to delete category.'));
      return;
    }
    window.location.href = 'categories.php';
  } catch (err) {
    alert('Failed to delete category: ' + err.message);
  }
}
</script>

<?php
bottom();

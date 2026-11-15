<?php
require_once __DIR__ . '/layout.php';

$err = $_SESSION['flash_err'] ?? '';
$msg = $_SESSION['flash_msg'] ?? '';
unset($_SESSION['flash_err'], $_SESSION['flash_msg']);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $a = $_POST['a'] ?? '';

    global $pdo;
    if (!$pdo) $pdo = db();

    try {
        if ($a === 'del') {
            if ($id <= 0) throw new Exception('Invalid category.');

            $pdo->beginTransaction();
            // Delete stock entries for items in this category
            q("DELETE se FROM stock_entries se JOIN items i ON i.id = se.item_id WHERE i.category_id = ?", [$id]);
            // Delete orders for items in this category
            q("DELETE o FROM orders o JOIN items i ON i.id = o.item_id WHERE i.category_id = ?", [$id]);
            // Delete items in this category
            q("DELETE FROM items WHERE category_id = ?", [$id]);
            // Delete category
            q("DELETE FROM categories WHERE id = ?", [$id]);
            $pdo->commit();

            $_SESSION['flash_msg'] = 'Category and associated items were deleted successfully.';
            go('categories.php');
        } elseif ($a === 'edit' || $a === 'add') {
            $name = trim($_POST['name'] ?? '');
            $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['code'] ?? ''));
            $desc = trim($_POST['description'] ?? '');

            if ($name === '' || $code === '') {
                throw new Exception('Category Name and Category ID are required.');
            }

            // Check duplicate name or code
            $dup = q("SELECT id FROM categories WHERE (name = ? OR code = ?) AND id != ?", [$name, $code, $id])->fetch();
            if ($dup) {
                throw new Exception("Another category with name '$name' or code '$code' already exists.");
            }

            if ($id > 0) {
                q("UPDATE categories SET name = ?, code = ?, description = ? WHERE id = ?", [$name, $code, $desc, $id]);
                $_SESSION['flash_msg'] = "Category '$name' updated successfully.";
            } else {
                q("INSERT INTO categories (name, code, description) VALUES (?, ?, ?)", [$name, $code, $desc]);
                $_SESSION['flash_msg'] = "Category '$name' created successfully.";
            }
            go('categories.php');
        }
    } catch (Exception $x) {
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $err = fail($x);
    }
}

$rows = q("SELECT c.*, COUNT(b.id) AS items, COALESCE(SUM(b.balance), 0) AS bal 
           FROM categories c 
           LEFT JOIN item_balances b ON b.category = c.name 
           GROUP BY c.id, c.name, c.code, c.description, c.unit, c.default_reorder, c.color 
           ORDER BY c.name ASC")->fetchAll();

top('Categories', 'categories.php');

if ($err) echo '<div class="err">' . e($err) . '</div>';
if ($msg) echo '<div class="success">' . e($msg) . '</div>';
?>

<!-- Add New Category Form -->
<div class="box pad" style="margin-bottom:16px">
  <h2 style="padding:0 0 14px 0;border-bottom:0;margin:0">+ Add New Category</h2>
  <form method="post">
    <input type="hidden" name="a" value="add">
    <input type="hidden" name="id" value="0">
    <div class="row" style="margin-top:10px">
      <label style="flex:1;min-width:200px">
        Category Name *
        <input name="name" required placeholder="e.g. Paint, Tools, Fasteners">
      </label>
      <label style="width:160px">
        Category ID / Code *
        <input name="code" required maxlength="10" placeholder="e.g. PNT, TLS" style="text-transform:uppercase">
      </label>
      <label style="flex:2;min-width:260px">
        Description (optional)
        <input name="description" placeholder="Brief description of items in this category">
      </label>
      <div style="align-self:flex-end">
        <button class="btn g">+ Add Category</button>
      </div>
    </div>
  </form>
</div>

<!-- Categories Table -->
<div class="box">
  <h2>All Categories (<?= count($rows) ?>)</h2>
  <table>
    <thead>
      <tr>
        <th>Category ID</th>
        <th>Category Name</th>
        <th>Description</th>
        <th class="r">Items</th>
        <th class="r">Stock Balance</th>
        <th class="r">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><span class="code"><?= e($r['code']) ?></span></td>
          <td>
            <a href="inventory.php?cat=<?= urlencode($r['name']) ?>" style="color:var(--blue);font-weight:700;text-decoration:none">
              <?= e($r['name']) ?> &rarr;
            </a>
          </td>
          <td style="color:var(--mut)"><?= e($r['description'] ?: '-') ?></td>
          <td class="r"><?= (int)$r['items'] ?></td>
          <td class="r"><b><?= qty($r['bal']) ?></b> <?= e($r['unit'] ?? 'pcs') ?></td>
          <td class="r" style="white-space:nowrap">
            <button type="button" class="btn s o" onclick='openEditModal(<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
              Edit
            </button>
            <button type="button" class="btn s d" style="margin-left:4px" onclick="confirmDeleteCat(<?= (int)$r['id'] ?>, '<?= e(addslashes($r['name'])) ?>', <?= (int)$r['items'] ?>)">
              Delete
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--mut)">No categories created yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Hidden Delete Form submitted via JavaScript confirmation -->
<form id="deleteCatForm" method="post" style="display:none">
  <input type="hidden" name="a" value="del">
  <input type="hidden" name="id" id="delCatId" value="0">
</form>

<!-- Modal: Edit Category Dialog -->
<dialog id="editCatModal" style="border:0;border-radius:14px;padding:24px;width:min(440px,94vw);box-shadow:0 12px 36px rgba(0,0,0,0.25)">
  <form method="post" id="editCatForm">
    <input type="hidden" name="a" value="edit">
    <input type="hidden" name="id" id="editId" value="0">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h2 style="margin:0;font-size:18px;color:#17202A">Edit Category</h2>
      <button type="button" onclick="document.getElementById('editCatModal').close()" style="border:0;background:none;font-size:22px;cursor:pointer;color:var(--mut)">&times;</button>
    </div>

    <label style="margin-bottom:12px">
      Category Name *
      <input name="name" id="editName" required placeholder="Category name">
    </label>

    <label style="margin-bottom:12px">
      Category ID / Code *
      <input name="code" id="editCode" required maxlength="10" placeholder="Code" style="text-transform:uppercase">
      <span style="font-size:12px;color:var(--mut)">Unique short code (e.g. NUT, PVC, STA)</span>
    </label>

    <label style="margin-bottom:18px">
      Description (optional)
      <input name="description" id="editDesc" placeholder="Brief description">
    </label>

    <div style="display:flex;justify-content:flex-end;gap:10px">
      <button type="button" class="btn o" onclick="document.getElementById('editCatModal').close()">Cancel</button>
      <button type="submit" class="btn g">Save Changes</button>
    </div>
  </form>
</dialog>

<script>
function openEditModal(cat) {
  if (!cat) return;
  document.getElementById('editId').value = cat.id;
  document.getElementById('editName').value = cat.name;
  document.getElementById('editCode').value = cat.code;
  document.getElementById('editDesc').value = cat.description || '';
  document.getElementById('editCatModal').showModal();
}

function confirmDeleteCat(id, name, itemCount) {
  let msg = `Are you sure you want to delete the category "${name}"?`;
  if (itemCount > 0) {
    msg = `Category "${name}" contains ${itemCount} item(s).\n\nDeleting this category will permanently delete all its items and associated stock records!\n\nAre you sure you want to proceed?`;
  }
  if (confirm(msg)) {
    document.getElementById('delCatId').value = id;
    document.getElementById('deleteCatForm').submit();
  }
}
</script>

<?php
bottom();

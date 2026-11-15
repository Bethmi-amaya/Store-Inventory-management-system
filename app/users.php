<?php
require_once __DIR__ . '/layout.php';

// Only Admin can access this page
if (($_SESSION['role'] ?? '') !== 'admin') {
    go('dashboard.php');
}

$err = '';
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['a'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    try {
        if ($userId <= 0) throw new Exception('Invalid user.');

        // Prevent admin from modifying their own critical permissions or deleting self
        if ($userId === (int)($_SESSION['user_id'] ?? 0) && in_array($action, ['reject', 'delete', 'make_user'])) {
            throw new Exception('You cannot alter or delete your own admin account.');
        }

        if ($action === 'approve') {
            $assignedRole = $_POST['role'] ?? 'user';
            if (!in_array($assignedRole, ['user', 'stock_keeper', 'admin'], true)) $assignedRole = 'user';
            q("UPDATE users SET status = 'approved', role = ? WHERE id = ?", [$assignedRole, $userId]);
            $msg = 'User account approved as ' . strtoupper(str_replace('_', ' ', $assignedRole)) . '. The user can now log in.';
        } elseif ($action === 'reject') {
            q("UPDATE users SET status = 'rejected' WHERE id = ?", [$userId]);
            $msg = 'User account rejected.';
        } elseif ($action === 'make_admin') {
            q("UPDATE users SET role = 'admin' WHERE id = ?", [$userId]);
            $msg = 'User role changed to Admin.';
        } elseif ($action === 'make_stockkeeper') {
            q("UPDATE users SET role = 'stock_keeper' WHERE id = ?", [$userId]);
            $msg = 'User role changed to Stock Keeper.';
        } elseif ($action === 'make_user') {
            q("UPDATE users SET role = 'user' WHERE id = ?", [$userId]);
            $msg = 'User role changed to Standard User.';
        } elseif ($action === 'delete') {
            q("DELETE FROM users WHERE id = ?", [$userId]);
            $msg = 'User removed completely.';
        }
    } catch (Exception $e) {
        $err = $e->getMessage();
    }
}

// Fetch pending users
$pendingUsers = q("SELECT * FROM users WHERE status = 'pending' ORDER BY created_at DESC")->fetchAll();

// Fetch approved users
$approvedUsers = q("SELECT * FROM users WHERE status = 'approved' ORDER BY role ASC, full_name ASC")->fetchAll();

// Fetch rejected users
$rejectedUsers = q("SELECT * FROM users WHERE status = 'rejected' ORDER BY created_at DESC")->fetchAll();

top('User Accounts & Approvals', 'users.php');

if ($err) echo '<div class="err">' . e($err) . '</div>';
if ($msg) echo '<div class="success">' . e($msg) . '</div>';
?>

<!-- Section 1: Pending Approvals -->
<div class="box" style="margin-bottom:20px;border-top:4px solid #B45309">
  <div style="padding:14px 18px;display:flex;justify-content:space-between;align-items:center;background:#FFFBEB;border-bottom:1px solid #FDE68A">
    <div>
      <h2 style="padding:0;margin:0;border:0;color:#92400E">Pending User Approvals (<?= count($pendingUsers) ?>)</h2>
      <div style="font-size:13px;color:#B45309;margin-top:2px">New account registrations awaiting your review and approval.</div>
    </div>
    <?php if (count($pendingUsers) > 0): ?>
      <span class="pill" style="background:#B45309"><?= count($pendingUsers) ?> Pending Review</span>
    <?php endif; ?>
  </div>

  <table>
    <thead>
      <tr>
        <th>Full Name</th>
        <th>Username</th>
        <th>Email</th>
        <th>Section</th>
        <th>Registered Date</th>
        <th class="r">Review Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pendingUsers as $u): ?>
        <tr style="background:#FFFDF7">
          <td><b><?= e($u['full_name']) ?></b></td>
          <td><span class="code"><?= e($u['username']) ?></span></td>
          <td><?= e($u['email']) ?></td>
          <td><span class="pill" style="background:#1D4ED8"><?= e($u['section'] ?: 'General') ?></span></td>
          <td><?= e($u['created_at']) ?></td>
          <td class="r" style="white-space:nowrap">
            <form method="post" style="display:inline-flex;align-items:center;gap:4px">
              <input type="hidden" name="a" value="approve">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <select name="role" style="font-size:12px;padding:3px 6px;border-radius:6px;border:1px solid #CBD5E1">
                <option value="user" selected>Standard User</option>
                <option value="stock_keeper">Stock Keeper</option>
                <option value="admin">Admin</option>
              </select>
              <button class="btn s g" style="min-width:76px">&#10003; Approve</button>
            </form>
            <form method="post" style="display:inline;margin-left:4px" onsubmit="return confirm('Reject registration for this user?')">
              <input type="hidden" name="a" value="reject">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button class="btn s d" style="min-width:64px">&times; Reject</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($pendingUsers)): ?>
        <tr>
          <td colspan="6" style="text-align:center;padding:24px;color:var(--mut)">
            &#10003; All registration requests have been reviewed. No pending accounts at this time.
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Section 2: Approved Users -->
<div class="box" style="margin-bottom:20px">
  <h2>Approved & Active Users (<?= count($approvedUsers) ?>)</h2>
  <table>
    <thead>
      <tr>
        <th>Full Name</th>
        <th>Username</th>
        <th>Email</th>
        <th>Section</th>
        <th>Role</th>
        <th>Status</th>
        <th class="r">Management</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($approvedUsers as $u): 
        $isAdmin = ($u['role'] === 'admin');
        $isSelf = ($u['id'] === (int)($_SESSION['user_id'] ?? 0));
      ?>
        <tr>
          <td>
            <b><?= e($u['full_name']) ?></b>
            <?php if ($isSelf): ?>
              <span class="pill" style="background:#0F766E;font-size:11px;margin-left:6px">You</span>
            <?php endif; ?>
          </td>
          <td><span class="code"><?= e($u['username']) ?></span></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e($u['section'] ?: 'General') ?></td>
          <td>
            <?php 
              $roleColor = '#4B5563';
              if ($u['role'] === 'admin') $roleColor = '#0F766E';
              elseif ($u['role'] === 'stock_keeper') $roleColor = '#B45309';
            ?>
            <span class="pill" style="background:<?= $roleColor ?>">
              <?= strtoupper(str_replace('_', ' ', $u['role'])) ?>
            </span>
          </td>
          <td><span class="tag ok">Approved</span></td>
          <td class="r" style="white-space:nowrap">
            <?php if (!$isSelf): ?>
              <?php if ($u['role'] !== 'admin'): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Promote this user to Admin?')">
                  <input type="hidden" name="a" value="make_admin">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button class="btn s o" title="Grant Administrator privileges">Make Admin</button>
                </form>
              <?php endif; ?>
              <?php if ($u['role'] !== 'stock_keeper'): ?>
                <form method="post" style="display:inline;margin-left:3px" onsubmit="return confirm('Assign Stock Keeper role to this user?')">
                  <input type="hidden" name="a" value="make_stockkeeper">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button class="btn s" style="background:#B45309;color:#fff" title="Make Stock Keeper">Make Stock Keeper</button>
                </form>
              <?php endif; ?>
              <?php if ($u['role'] !== 'user'): ?>
                <form method="post" style="display:inline;margin-left:3px" onsubmit="return confirm('Set this user as standard user?')">
                  <input type="hidden" name="a" value="make_user">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button class="btn s o" title="Set Standard User">Make User</button>
                </form>
              <?php endif; ?>

              <form method="post" style="display:inline;margin-left:4px" onsubmit="return confirm('Suspend or delete this user?')">
                <input type="hidden" name="a" value="delete">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button class="btn s d">Delete</button>
              </form>
            <?php else: ?>
              <span style="color:var(--mut);font-size:12px">Current User</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Section 3: Rejected Requests (Collapsible) -->
<?php if (!empty($rejectedUsers)): ?>
  <details class="box">
    <summary class="pad" style="cursor:pointer;font-weight:700;color:#991B1B">
      Rejected Registration Requests (<?= count($rejectedUsers) ?>)
    </summary>
    <table>
      <thead>
        <tr>
          <th>Full Name</th>
          <th>Username</th>
          <th>Email</th>
          <th>Section</th>
          <th>Status</th>
          <th class="r">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rejectedUsers as $u): ?>
          <tr>
            <td><?= e($u['full_name']) ?></td>
            <td><span class="code"><?= e($u['username']) ?></span></td>
            <td><?= e($u['email']) ?></td>
            <td><?= e($u['section'] ?: 'General') ?></td>
            <td><span class="tag out">Rejected</span></td>
            <td class="r">
              <form method="post" style="display:inline">
                <input type="hidden" name="a" value="approve">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button class="btn s g">Re-Approve</button>
              </form>
              <form method="post" style="display:inline;margin-left:4px" onsubmit="return confirm('Permanently delete this record?')">
                <input type="hidden" name="a" value="delete">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button class="btn s d">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </details>
<?php endif; ?>

<?php
bottom();


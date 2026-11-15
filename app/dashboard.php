<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$userName = $_SESSION['user_name'] ?? 'Admin';
$userRole = $_SESSION['role'] ?? 'user';
$isAdmin = ($userRole === 'admin');
$isStockKeeper = ($userRole === 'stock_keeper');

$adminMsg = $_GET['msg'] ?? '';
$adminErr = '';

// Handle Admin direct user approval / rejection requests from Dashboard
if ($isAdmin && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['user_action'])) {
    $uAct = $_POST['user_action'] ?? '';
    $uId = (int)($_POST['target_user_id'] ?? 0);
    $assignRole = $_POST['assign_role'] ?? 'user';
    if (!in_array($assignRole, ['user', 'stock_keeper', 'admin'], true)) {
        $assignRole = 'user';
    }

    if ($uId > 0) {
        try {
            $mConn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if (!$mConn->connect_errno) {
                if ($uAct === 'approve') {
                    $uStmt = $mConn->prepare("UPDATE users SET status = 'approved', role = ? WHERE id = ?");
                    $uStmt->bind_param('si', $assignRole, $uId);
                    if ($uStmt->execute()) {
                        $roleTitle = strtoupper(str_replace('_', ' ', $assignRole));
                        $adminMsg = "User account #$uId was approved successfully as $roleTitle.";
                    }
                    $uStmt->close();
                } elseif ($uAct === 'reject') {
                    $uStmt = $mConn->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
                    $uStmt->bind_param('i', $uId);
                    if ($uStmt->execute()) {
                        $adminMsg = "User account #$uId registration request was rejected.";
                    }
                    $uStmt->close();
                }
                $mConn->close();
            }
        } catch (Throwable $e) {
            $adminErr = $e->getMessage();
        }
    }
}

$pendingCount = 0;
$pendingUsersList = [];
if ($isAdmin) {
    try {
        $pConn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (!$pConn->connect_errno) {
            $res = $pConn->query("SELECT * FROM users WHERE status = 'pending' ORDER BY created_at DESC");
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $pendingUsersList[] = $r;
                }
                $pendingCount = count($pendingUsersList);
            }
            $pConn->close();
        }
    } catch (Throwable $e) {}
}

$initialData = null;
try {
    $mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$mysqli->connect_errno) {
        $cRes = $mysqli->query("SELECT * FROM categories ORDER BY id ASC");
        $cats = [];
        if ($cRes) {
            while ($r = $cRes->fetch_assoc()) {
                $cats[] = [
                    'id' => (int)$r['id'],
                    'name' => $r['name'],
                    'code' => $r['code'],
                    'unit' => $r['unit'],
                    'min' => (int)$r['reorder_level'],
                    'desc' => $r['description'] ?? '',
                    'color' => $r['color'] ?? '#0F766E'
                ];
            }
        }
        $iRes = $mysqli->query("SELECT i.*, c.name AS cat_name FROM items i LEFT JOIN categories c ON i.category_id = c.id ORDER BY i.id ASC");
        $items = [];
        if ($iRes) {
            while ($r = $iRes->fetch_assoc()) {
                $items[] = [
                    'id' => (int)$r['id'],
                    'code' => $r['code'],
                    'name' => $r['name'],
                    'cat' => $r['cat_name'] ?? 'General',
                    'unit' => $r['unit'],
                    'stock' => (int)$r['stock'],
                    'min' => (int)$r['reorder_level']
                ];
            }
        }
        $tRes = $mysqli->query("SELECT * FROM stock_transactions ORDER BY transaction_date ASC, id ASC");
        $tx = [];
        if ($tRes) {
            while ($r = $tRes->fetch_assoc()) {
                $tx[] = [
                    'id' => (int)$r['id'],
                    'd' => $r['transaction_date'],
                    'ref' => $r['reference_no'],
                    'type' => $r['type'],
                    'src' => $r['source_or_section'],
                    'item' => (int)$r['item_id'],
                    'qty' => (int)$r['qty']
                ];
            }
        }
        $oRes = $mysqli->query("SELECT * FROM orders ORDER BY order_date ASC, id ASC");
        $orders = [];
        if ($oRes) {
            while ($r = $oRes->fetch_assoc()) {
                $orders[] = [
                    'id' => (int)$r['id'],
                    'sec' => $r['section_name'],
                    'item' => (int)$r['item_id'],
                    'qty' => (int)$r['qty'],
                    'c' => $r['type'],
                    'st' => $r['status'],
                    'd' => $r['order_date']
                ];
            }
        }
        if (!empty($cats) && !empty($items)) {
            $initialData = [
                'cats' => $cats,
                'items' => $items,
                'tx' => $tx,
                'orders' => $orders
            ];
        }
        $mysqli->close();
    }
} catch (Throwable $e) {}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Stock Book - Stationery & Hardware Management</title>
<style>
:root {
  --ink: #17202A;
  --mut: #52606D;
  --bg: #F4F5F7;
  --line: #DDE1E6;
  --red: #B42318;
  --blue: #1D4ED8;
  --port: #0F766E;
  --shop: #B45309;
  --panel: #ffffff;
  --shadow: 0 18px 42px rgba(23, 32, 42, 0.08);
}
* { box-sizing: border-box; }
body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, sans-serif; color: var(--ink); background: var(--bg); }
button, input, select { font: inherit; }
.app { display: flex; min-height: 100vh; }

nav {
  width: 244px;
  flex-shrink: 0;
  background: #17202A;
  color: #fff;
  padding: 20px 14px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  position: sticky;
  top: 0;
  height: 100vh;
  transition: width .2s;
}
.brand { display: flex; align-items: center; gap: 12px; padding: 4px 8px 18px; }
.logo { width: 40px; height: 40px; border-radius: 10px; background: #F2B705; color: #17202A; display: grid; place-items: center; flex-shrink: 0; }
.brand b { font-size: 18px; display: block; line-height: 1.2; }
.brand small { font-size: 12px; color: #9AA7B4; }
.sec { font-size: 11px; letter-spacing: .1em; color: #7D8B99; padding: 14px 12px 6px; font-weight: 700; }
nav a {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 12px;
  color: #D5DBE1;
  text-decoration: none;
  border-radius: 10px;
  position: relative;
  min-height: 44px;
  transition: background .15s ease;
}
nav a:hover { background: rgba(255,255,255,.08); }
nav a:focus-visible, .collapse:focus-visible { outline: 3px solid #F2B705; outline-offset: 2px; }
nav a.on { background: #fff; color: #17202A; font-weight: 700; }
nav a.on::before {
  content: "";
  position: absolute;
  left: -14px;
  top: 10px;
  bottom: 10px;
  width: 4px;
  border-radius: 0 4px 4px 0;
  background: #F2B705;
}
nav svg { width: 20px; height: 20px; flex-shrink: 0; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.badge { margin-left: auto; min-width: 22px; padding: 1px 7px; border-radius: 999px; font-size: 12px; font-weight: 700; text-align: center; color: #fff; }
.badge:empty { display: none; }
.user {
  margin-top: auto;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 8px;
  border-top: 1px solid rgba(255,255,255,.12);
}
.av { width: 36px; height: 36px; border-radius: 50%; background: #0F766E; display: grid; place-items: center; font-weight: 700; font-size: 13px; flex-shrink: 0; }
.user small { display: block; color: #9AA7B4; font-size: 12px; }
.logout-btn {
  color: #ff9b9b;
  text-decoration: none;
  font-size: 12px;
  margin-left: auto;
  padding: 4px 8px;
  border-radius: 6px;
  background: rgba(255,255,255,0.06);
}
.logout-btn:hover { background: rgba(220, 38, 38, 0.2); color: #fff; }
.collapse {
  background: none;
  border: 1px solid rgba(255,255,255,.2);
  color: #D5DBE1;
  border-radius: 8px;
  min-height: 40px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 8px;
}
.mini nav { width: 76px; }
.mini .lbl, .mini .brand div:not(.logo), .mini .sec, .mini .user div:not(.av), .mini .badge, .mini .logout-btn { display: none; }
.mini nav a { justify-content: center; }
.mini .brand, .mini .user { justify-content: center; }
.mini .collapse svg { transform: rotate(180deg); }

main { flex: 1; max-width: 1160px; padding: 26px 30px; display: flex; flex-direction: column; gap: 18px; }
.top { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
h1 { margin: 0; font-size: 26px; }
.btn {
  min-height: 44px;
  padding: 0 18px;
  border-radius: 8px;
  border: 0;
  color: #fff;
  font-weight: 700;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  transition: transform .15s ease, opacity .15s ease;
}
.btn:hover { transform: translateY(-1px); }
.btn:active { transform: translateY(0); }
.btn:focus-visible, input:focus-visible, select:focus-visible { outline: 3px solid #F2B705; outline-offset: 2px; }

.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; }
.stat, .box { background: #fff; border: 1px solid var(--line); border-radius: 12px; box-shadow: var(--shadow); }
.stat { padding: 14px 16px; border-top: 6px solid var(--c); }
.stat small { color: var(--mut); font-size: 14px; }
.stat div { font-size: 30px; font-weight: 700; color: var(--c); }
.two { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 16px; }
.box h2 { margin: 0; padding: 14px 16px; font-size: 16px; }
.row { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 12px 16px; border-top: 1px solid #E8EBEF; }
.row small { display: block; color: var(--mut); font-size: 13px; }
.pill { display: inline-flex; align-items: center; justify-content: center; color: #fff; border-radius: 999px; padding: 3px 12px; font-size: 13px; font-weight: 700; border: 0; }
button.pill { cursor: pointer; min-height: 32px; }
.empty { padding: 24px 16px; color: var(--mut); border-top: 1px solid #E8EBEF; text-align: center; }
.bar { display: flex; height: 22px; border-radius: 6px; overflow: hidden; background: #E8EBEF; }
table { width: 100%; border-collapse: collapse; font-size: 15px; }
th { text-align: left; color: var(--mut); font-size: 13px; padding: 10px 16px; }
td { padding: 12px 16px; border-top: 1px solid #E8EBEF; }
td.n { text-align: right; font-weight: 700; }

dialog { border: 0; border-radius: 14px; padding: 22px; width: min(380px, 92vw); box-shadow: var(--shadow); }
dialog::backdrop { background: rgba(23,32,42,.5); }
label { display: flex; flex-direction: column; gap: 4px; font-size: 14px; color: var(--mut); margin-bottom: 12px; }
input, select { min-height: 46px; padding: 0 12px; border: 1px solid #C3CAD2; border-radius: 10px; background: #fff; color: var(--ink); }
.acts { display: flex; gap: 10px; justify-content: flex-end; margin-top: 16px; }

.view[hidden] { display: none !important; }
.cgrid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; }
.ccard {
  background: #fff;
  border: 1px solid var(--line);
  border-top: 8px solid var(--c);
  border-radius: 12px;
  padding: 16px;
  text-align: left;
  cursor: pointer;
  font: inherit;
  color: var(--ink);
  transition: transform .15s ease, box-shadow .15s ease;
}
.ccard:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(23,32,42,.12); }
.ccard b { font-size: 19px; display: block; color: var(--c); }
.ccard small { color: var(--mut); font-size: 14px; }

.back {
  background: none;
  border: 0;
  color: var(--blue);
  font-weight: 700;
  cursor: pointer;
  padding: 0;
  min-height: 36px;
  text-align: left;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.back:hover { text-decoration: underline; }

.cat-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  display: inline-block;
  flex-shrink: 0;
}
.clickable-row {
  cursor: pointer;
  transition: background-color .15s ease;
}
.clickable-row:hover {
  background-color: #F1F4F8 !important;
}
.cat-view-btn {
  background: #fff;
  border: 1px solid var(--line);
  color: var(--ink);
  border-radius: 6px;
  padding: 4px 12px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all .15s ease;
}
.cat-view-btn:hover {
  background: var(--ink);
  color: #fff;
  border-color: var(--ink);
}

.sm { min-height: 34px; padding: 0 12px; border-radius: 8px; border: 1px solid #C3CAD2; background: #fff; color: var(--ink); cursor: pointer; font-weight: 700; font-size: 14px; }
.sm.d { color: var(--red); border-color: var(--red); }
.sm:focus-visible { outline: 3px solid #F2B705; outline-offset: 2px; }
#qRes { position: absolute; left: 0; right: 0; top: 50px; z-index: 20; box-shadow: 0 8px 24px rgba(23,32,42,.18); max-height: 360px; overflow-y: auto; }
.qr { display: flex; align-items: center; gap: 12px; width: 100%; padding: 10px 14px; border: 0; border-top: 1px solid #E8EBEF; background: #fff; cursor: pointer; text-align: left; font: inherit; color: var(--ink); min-height: 48px; }
.qr:first-child { border-top: 0; }
.qr:hover, .qr.on { background: #F1F4F8; }
.code { font-family: ui-monospace, Consolas, monospace; font-size: 13px; font-weight: 700; background: #E8EBEF; border-radius: 6px; padding: 2px 8px; white-space: nowrap; }
.hint { font-size: 12px; color: var(--mut); }
.pt { width: 100%; border-collapse: collapse; font-size: 14px; }
.pt th, .pt td { border: 1px solid #17202A; padding: 8px; vertical-align: top; }
.pt th { background: #F1F4F8; font-size: 12px; text-align: center; }
.paper { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 20px; display: flex; flex-direction: column; gap: 14px; }
#nLines input, #nLines select { width: 100%; min-width: 70px; min-height: 42px; }
#nLines td { padding: 6px; border-top: 1px solid #E8EBEF; vertical-align: middle; }
tr[data-n]:hover { background: #F7F8FA; }
.rhead { display: none; }
@media print { .rhead { display: block; } nav, .noprint { display: none !important; } body { background: #fff; } main { padding: 0; max-width: none; } .box, .stat { break-inside: avoid; } }
@media (max-width: 760px) {
  .app, .mini .app { flex-direction: column; }
  nav, .mini nav { width: 100%; height: auto; position: static; flex-direction: row; overflow-x: auto; padding: 10px 12px; gap: 4px; align-items: center; }
  .brand, .sec, .user, .collapse { display: none; }
  nav a, .mini nav a { white-space: nowrap; justify-content: flex-start; padding: 10px 12px; }
  .mini .lbl { display: inline; }
  nav a.on::before { display: none; }
  main { padding: 18px 16px; }
}
</style>
</head>
<body>
<div class="app">
  <nav id="nav" aria-label="Main">
    <div class="brand">
      <div class="logo" style="background:#fff;padding:2px;overflow:hidden;border:1px solid rgba(255,255,255,0.2)">
        <img src="logo.png" alt="SLPA" style="width:100%;height:100%;object-fit:contain;border-radius:8px">
      </div>
      <div><b style="font-size:16px">Ports Authority</b><small style="font-size:11px;color:#A0AEC0">Stationery & Store</small></div>
    </div>
    
    <div class="sec">MAIN</div>
    <a class="on" href="dashboard.php" title="Dashboard">
      <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="8" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="15" width="7" height="6" rx="1"/></svg>
      <span class="lbl">Dashboard</span>
    </a>
    <a href="categories.php" title="Categories">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.2"/></svg>
      <span class="lbl">Categories</span>
      <span class="badge" id="bgCats" style="background:#B45309"></span>
    </a>
    <?php if ($isAdmin): ?>
    <a href="users.php" title="User Approvals">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span class="lbl">User Approvals</span>
      <?php if ($pendingCount > 0): ?>
        <span class="badge" style="background:#B45309"><?php echo $pendingCount; ?></span>
      <?php endif; ?>
    </a>
    <?php endif; ?>

    <div class="sec">STOCK</div>
    <a href="receive_stock.php" title="Receive Stock">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M4 20h16"/></svg>
      <span class="lbl">Receive Stock</span>
    </a>
    <a href="ledger.php" title="Stock Ledger">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
      <span class="lbl">Stock Ledger</span>
    </a>
    <a href="orders.php" title="Orders">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6h15l-1.5 9h-12z"/><path d="M6 6L5 3H2"/><circle cx="9" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/></svg>
      <span class="lbl">Orders</span>
      <span class="badge" id="bgOrd" style="background:#B42318"></span>
    </a>
    <a href="issue_notes.php" title="Issue Notes">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/></svg>
      <span class="lbl">Issue Notes</span>
    </a>
    <a href="reports.php" title="Reports">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>
      <span class="lbl">Reports</span>
    </a>

    <div class="user">
      <div class="av"><?php echo strtoupper(substr($userName, 0, 2)); ?></div>
      <div style="flex:1;min-width:0;overflow:hidden">
        <b style="font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block"><?php echo htmlspecialchars($userName); ?></b>
        <span class="pill" style="font-size:10px;padding:2px 7px;background:<?= $isAdmin ? '#0F766E' : ($isStockKeeper ? '#B45309' : '#4B5563') ?>">
          <?= strtoupper(str_replace('_', ' ', $userRole)) ?>
        </span>
      </div>
      <a class="logout-btn" href="logout.php" title="Sign out">Logout</a>
    </div>
    <button class="collapse" id="bCol" aria-label="Collapse sidebar">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
      <span class="lbl">Collapse</span>
    </button>
  </nav>

  <main>

    <!-- 1. DASHBOARD VIEW -->
    <div class="view" id="v-dash" style="display:flex;flex-direction:column;gap:18px">
      <div class="top">
        <div>
          <h1>Dashboard</h1>
          <div style="color:var(--mut);font-size:14px">
            Welcome back, <b><?php echo htmlspecialchars($userName); ?></b> &bull; 
            <span style="font-weight:700;color:<?= $isAdmin ? '#0F766E' : ($isStockKeeper ? '#B45309' : '#1D4ED8') ?>">
              Role: <?= strtoupper(str_replace('_', ' ', $userRole)) ?>
            </span>
          </div>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
          <?php if ($isAdmin): ?>
            <button class="btn" style="background:#0F766E;font-weight:700" id="bDashAddItem" title="Add new item to inventory">+ Add Item</button>
            <button class="btn" style="background:#1D4ED8;font-weight:700" id="bRecv" title="Receive stock into inventory">+ Receive Stock</button>
            <a href="reports.php" class="btn" style="background:#17202A;text-decoration:none;font-weight:700" title="Generate inventory reports">📊 Generate Reports</a>
            <a href="inventory.php" class="btn" style="background:#4B5563;text-decoration:none" title="View all stock balances and details">📋 View All Stock</a>
            <button class="btn" style="background:#6B7280" id="bOrd">+ New Order</button>
          <?php elseif ($isStockKeeper): ?>
            <button class="btn" style="background:#0F766E;font-weight:700" id="bDashAddItem" title="Add new item to inventory">+ Add Item</button>
            <button class="btn" style="background:#1D4ED8;font-weight:700" id="bRecv" title="Receive stock into inventory">+ Receive Stock</button>
            <a href="inventory.php" class="btn" style="background:#17202A;text-decoration:none;font-weight:700" title="Manage available stock in inventory">📦 Manage Available Stock</a>
            <a href="issue_notes.php" class="btn" style="background:#B45309;text-decoration:none" title="Issue stock to sections">📤 Issue Stock</a>
            <button class="btn" style="background:#4B5563" id="bOrd">+ New Order</button>
          <?php else: ?>
            <button class="btn" style="background:#1D4ED8;font-weight:700" id="bOrd">+ New Requisition Order</button>
            <a href="inventory.php" class="btn" style="background:#17202A;text-decoration:none">📋 View Stock Details</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($adminMsg): ?>
        <div style="background:#DEF7EC;border:1px solid #86EFAC;color:#03543F;padding:12px 16px;border-radius:10px;font-size:14px;font-weight:600">
          &#10003; <?php echo htmlspecialchars($adminMsg); ?>
        </div>
      <?php endif; ?>

      <?php if ($adminErr): ?>
        <div style="background:#FEE2E2;border:1px solid #FCA5A5;color:#991B1B;padding:12px 16px;border-radius:10px;font-size:14px;font-weight:600">
          &times; <?php echo htmlspecialchars($adminErr); ?>
        </div>
      <?php endif; ?>

      <!-- ADMIN DASHBOARD: USER REGISTRATION APPROVAL REQUESTS -->
      <?php if ($isAdmin): ?>
        <div class="box" style="border-top:4px solid #B45309;margin-bottom:4px">
          <div style="padding:14px 18px;display:flex;justify-content:space-between;align-items:center;background:#FFFBEB;border-bottom:1px solid #FDE68A;border-radius:12px 12px 0 0">
            <div>
              <h2 style="padding:0;margin:0;border:0;color:#92400E;display:flex;align-items:center;gap:8px;font-size:16px">
                <span>🛡️ User Registration Approval Requests</span>
                <?php if ($pendingCount > 0): ?>
                  <span class="pill" style="background:#B45309;font-size:11px;padding:2px 8px"><?= $pendingCount ?> Pending</span>
                <?php else: ?>
                  <span class="pill" style="background:#0F766E;font-size:11px;padding:2px 8px">All Cleared</span>
                <?php endif; ?>
              </h2>
              <div style="font-size:13px;color:#B45309;margin-top:4px">
                Review and approve or reject new user registration requests. Only Admin-approved accounts can log in.
              </div>
            </div>
            <?php if (!empty($pendingUsersList)): ?>
              <a href="users.php" style="font-size:13px;color:#92400E;font-weight:700;text-decoration:none">Manage All Users &rarr;</a>
            <?php endif; ?>
          </div>

          <?php if (!empty($pendingUsersList)): ?>
            <div style="overflow-x:auto">
              <table style="margin:0;width:100%">
                <thead>
                  <tr style="background:#FEF3C7">
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Section</th>
                    <th>Registered At</th>
                    <th>Assign Role & Approve</th>
                    <th class="r">Decline</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($pendingUsersList as $pu): ?>
                    <tr style="background:#FFFDF7">
                      <td><b><?= htmlspecialchars($pu['full_name']) ?></b></td>
                      <td><span class="code"><?= htmlspecialchars($pu['username']) ?></span></td>
                      <td><?= htmlspecialchars($pu['email']) ?></td>
                      <td><span class="pill" style="background:#1D4ED8"><?= htmlspecialchars($pu['section'] ?? 'General') ?></span></td>
                      <td><small style="color:var(--mut)"><?= htmlspecialchars($pu['created_at']) ?></small></td>
                      <td>
                        <form method="POST" style="display:flex;align-items:center;gap:6px;margin:0">
                          <input type="hidden" name="user_action" value="approve">
                          <input type="hidden" name="target_user_id" value="<?= $pu['id'] ?>">
                          <select name="assign_role" style="font-size:12.5px;padding:4px 8px;border-radius:6px;border:1px solid #CBD5E1">
                            <option value="user" selected>Standard User</option>
                            <option value="stock_keeper">Stock Keeper</option>
                            <option value="admin">Administrator</option>
                          </select>
                          <button type="submit" class="btn" style="background:#0F766E;padding:4px 10px;font-size:12px;font-weight:700">&#10003; Approve</button>
                        </form>
                      </td>
                      <td class="r">
                        <form method="POST" style="margin:0" onsubmit="return confirm('Reject registration for user <?= htmlspecialchars($pu['username']) ?>?')">
                          <input type="hidden" name="user_action" value="reject">
                          <input type="hidden" name="target_user_id" value="<?= $pu['id'] ?>">
                          <button type="submit" class="btn" style="background:#B42318;padding:4px 10px;font-size:12px;font-weight:700">&times; Reject</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div style="padding:16px 20px;color:var(--mut);font-size:13.5px;display:flex;align-items:center;gap:8px">
              <span style="color:#059669;font-weight:700;font-size:16px">&#10003;</span> All user registration requests have been reviewed. No pending accounts at this time.
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="stats" id="stats"></div>

      <div class="two">
        <div class="box">
          <h2 style="color:var(--red)">Red orders: not in stock, buy outside</h2>
          <div id="red"></div>
        </div>
        <div class="box">
          <h2 style="color:var(--blue)">Blue orders: issued from stock</h2>
          <div id="blue"></div>
        </div>
      </div>

      <div class="two">
        <div class="box" style="padding:16px">
          <b>Goods received this month by source</b>
          <div class="bar" id="src" style="margin:12px 0 8px"></div>
          <div id="srcL" style="display:flex;justify-content:space-between;font-size:14px"></div>
        </div>

        <!-- Dashboard Categories Table (CLICKABLE) -->
        <div class="box" style="overflow-x:auto">
          <div style="padding:14px 16px 8px;display:flex;justify-content:space-between;align-items:center">
            <h2 style="padding:0;font-size:16px">Goods by Category</h2>
            <span style="font-size:12px;color:var(--mut)">Click any category to open &rarr;</span>
          </div>
          <table>
            <thead>
              <tr>
                <th>Category</th>
                <th>Items</th>
                <th style="text-align:right">Stock Balance</th>
                <th style="text-align:right">Low</th>
                <th style="text-align:right">Action</th>
              </tr>
            </thead>
            <tbody id="cats"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- 2. CATEGORIES VIEW & RELEVANT CATEGORY PAGE -->
    <div class="view" id="v-cats" hidden style="display:none;flex-direction:column;gap:18px">
      <!-- All Categories Grid -->
      <div id="catList">
        <div class="top">
          <div>
            <h1>Categories</h1>
            <div style="color:var(--mut);font-size:14px">Click any category below to view and manage its stock items</div>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button class="btn" style="background:var(--ink)" id="bCatAddItem">+ Add item</button>
            <button class="btn" style="background:var(--port)" id="bCat">+ Add category</button>
          </div>
        </div>
        <div class="cgrid" id="cgrid" style="margin-top:18px"></div>
      </div>

      <!-- RELEVANT CATEGORY DETAIL PAGE (Opens when a category is clicked) -->
      <div id="catDet" hidden style="display:flex;flex-direction:column;gap:16px">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <button class="back" id="bBack">&larr; All categories</button>
          <span style="color:var(--mut)">&bull;</span>
          <button class="back" id="bBackDash">&larr; Dashboard</button>
        </div>

        <div class="top">
          <div>
            <h1 id="cdTitle" style="margin:0"></h1>
            <div id="cdDesc" style="color:var(--mut);font-size:14px;margin-top:4px;display:none"></div>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button class="btn" style="background:var(--ink)" id="bItem">+ Add item</button>
            <button class="btn" style="background:#6B7785" id="bECat">Edit category</button>
            <button class="btn" style="background:var(--red)" id="bDCat">Delete category</button>
          </div>
        </div>

        <!-- Category Items Table -->
        <div class="box" style="overflow-x:auto">
          <div style="padding:14px 16px;border-bottom:1px solid #E8EBEF;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
            <div style="font-weight:700">Goods in this category</div>
            <input id="cdSearch" type="search" placeholder="Filter goods in this category..." style="min-height:36px;padding:0 12px;border-radius:8px;font-size:13px;max-width:260px">
          </div>
          <table>
            <thead>
              <tr>
                <th>Product ID</th>
                <th>Product Name</th>
                <th>Unit</th>
                <th style="text-align:right">Stock Balance</th>
                <th style="text-align:right">Reorder Level</th>
                <th>Status</th>
                <th style="text-align:right">Action</th>
              </tr>
            </thead>
            <tbody id="cdRows"></tbody>
          </table>
        </div>
      </div>

      <!-- Item Detail View -->
      <div id="itemDet" hidden style="display:flex;flex-direction:column;gap:16px">
        <button class="back" id="bBackItem"></button>
        <div class="top" style="margin-top:6px">
          <div>
            <h1 id="idTitle"></h1>
            <div id="idSub" style="color:var(--mut);font-size:14px"></div>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button class="btn" style="background:var(--port)" id="bTxIn">+ Received</button>
            <button class="btn" style="background:var(--blue)" id="bTxOut">+ Issued</button>
            <button class="btn" style="background:var(--ink)" id="bEdit">Edit item</button>
            <button class="btn" style="background:var(--red)" id="bDel">Delete item</button>
          </div>
        </div>
        <div class="stats" id="idStats" style="margin-top:14px"></div>
        <div class="box" style="overflow-x:auto;margin-top:14px">
          <h2>Stock in / out history</h2>
          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>Reference</th>
                <th>Source / Section</th>
                <th style="text-align:right">Received</th>
                <th style="text-align:right">Issued</th>
                <th style="text-align:right">Balance</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="idRows"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- 3. STOCK LEDGER VIEW -->
    <div class="view" id="v-ledger" hidden style="display:none;flex-direction:column;gap:18px">
      <div class="top">
        <div>
          <h1>Stock In / Out Ledger</h1>
          <div style="color:var(--mut);font-size:14px">Track individual item stock movement</div>
        </div>
        <label style="margin:0">Select Item:
          <select id="lItem"></select>
        </label>
      </div>
      <div class="box" style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Reference</th>
              <th>Source / Section</th>
              <th style="text-align:right">Received</th>
              <th style="text-align:right">Issued</th>
              <th style="text-align:right">Balance</th>
            </tr>
          </thead>
          <tbody id="lRows"></tbody>
        </table>
      </div>
    </div>

    <!-- 4. ORDERS VIEW -->
    <div class="view" id="v-orders" hidden style="display:none;flex-direction:column;gap:18px">
      <div class="top">
        <h1>Orders</h1>
        <button class="btn" style="background:var(--ink)" onclick="$('bOrd').click()">+ New order</button>
      </div>
      <div class="box" style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Section</th>
              <th>Item</th>
              <th style="text-align:right">Qty</th>
              <th>Type</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="oRows"></tbody>
        </table>
      </div>
    </div>

    <!-- 5. ISSUE NOTES VIEW -->
    <div class="view" id="v-notes" hidden style="display:none;flex-direction:column;gap:18px">
      <div id="nList">
        <div class="top">
          <div>
            <h1>Issue Notes</h1>
            <div style="color:var(--mut);font-size:14px">Requisition and store issue notes</div>
          </div>
          <button class="btn" style="background:var(--ink)" id="bNewNote">+ New Issue Note</button>
        </div>
        <div class="box" style="overflow-x:auto;margin-top:16px">
          <table>
            <thead>
              <tr>
                <th>Serial No</th>
                <th>Date</th>
                <th>Indenting unit</th>
                <th>Type</th>
                <th style="text-align:right">Lines</th>
                <th style="text-align:right">Value (Rs.)</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="nRows"></tbody>
          </table>
        </div>
      </div>

      <div id="nForm" hidden>
        <button class="back" id="bNfBack">&larr; Issue notes</button>
        <h1 style="margin:6px 0 14px">New Issue Note</h1>
        <form id="fNote">
          <div class="box" style="padding:16px;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0 16px">
            <label>Serial No.<input id="nSerial" required></label>
            <label>Date<input id="nDate" type="date" required></label>
            <label>Indenting unit (section)
              <select id="nUnit" required>
                <option value="Engineering">Engineering</option>
                <option value="Finance">Finance</option>
                <option value="Operations">Operations</option>
                <option value="Admin">Admin</option>
              </select>
            </label>
            <label>Stores issue unit<input id="nStore" placeholder="e.g. Main store"></label>
            <fieldset style="grid-column:1/-1;border:0;padding:0;margin:0;display:flex;gap:20px;flex-wrap:wrap">
              <legend style="font-size:14px;color:var(--mut);padding:0 0 6px">Note type</legend>
              <label style="flex-direction:row;align-items:center;gap:8px;color:var(--ink);margin:0">
                <input type="radio" name="nType" value="stock" checked style="min-height:auto"> Stock item (Blue order)
              </label>
              <label style="flex-direction:row;align-items:center;gap:8px;color:var(--ink);margin:0">
                <input type="radio" name="nType" value="nonstock" style="min-height:auto"> Non-stock item (Red order)
              </label>
            </fieldset>
          </div>
          <div id="nHint" style="margin:12px 0;font-size:14px"></div>
          <div class="box" style="overflow-x:auto">
            <table style="min-width:900px">
              <thead>
                <tr>
                  <th>Job No.</th>
                  <th>Qty required</th>
                  <th>Item / Description</th>
                  <th>Code / Rack</th>
                  <th>Qty issued</th>
                  <th style="text-align:right">Balance after</th>
                  <th>Rate (Rs.)</th>
                  <th style="text-align:right">Value (Rs.)</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="nLines"></tbody>
              <tfoot>
                <tr>
                  <td colspan="7" style="text-align:right;font-weight:700;padding:12px 16px">Total (Rs.)</td>
                  <td class="n" id="nTot" style="font-size:18px"></td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
          <div style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap">
            <button type="button" class="btn" style="background:#6B7785" id="bAddLine">+ Add line</button>
            <button type="submit" class="btn" style="background:var(--port)">Save issue note</button>
          </div>
        </form>
      </div>

      <div id="nView" hidden><div id="nPaper"></div></div>
    </div>

    <!-- 6. REPORTS VIEW -->
    <div class="view" id="v-reports" hidden style="display:none;flex-direction:column;gap:18px">
      <div class="top">
        <div>
          <h1>Reports & Analytics</h1>
          <div style="color:var(--mut);font-size:14px">Monthly summaries and exports</div>
        </div>
        <div class="noprint" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
          <label style="margin:0">Month:
            <select id="rpMon"></select>
          </label>
          <button class="btn" style="background:var(--port)" id="bCsv">Export CSV</button>
          <button class="btn" style="background:var(--ink)" id="bPrint">Print / PDF</button>
        </div>
      </div>
      <div class="rhead"><b id="rpTitle"></b></div>
      <div class="stats" id="rpStats"></div>
      <div class="two">
        <div class="box" style="padding:16px">
          <b>Received vs issued by category</b>
          <div id="chCat" style="margin-top:8px"></div>
        </div>
        <div class="box" style="padding:16px">
          <b>Goods issued by section</b>
          <div id="chSec" style="margin-top:8px"></div>
        </div>
      </div>
      <div class="two">
        <div class="box" style="overflow-x:auto">
          <h2>By category</h2>
          <table>
            <thead>
              <tr>
                <th>Category</th>
                <th style="text-align:right">Received</th>
                <th style="text-align:right">Issued</th>
                <th style="text-align:right">Balance now</th>
              </tr>
            </thead>
            <tbody id="rpCat"></tbody>
          </table>
        </div>
        <div class="box" style="overflow-x:auto">
          <h2>Received by source</h2>
          <table>
            <thead>
              <tr>
                <th>Source</th>
                <th style="text-align:right">Receipts</th>
                <th style="text-align:right">Quantity</th>
                <th style="text-align:right">Share</th>
              </tr>
            </thead>
            <tbody id="rpSrc"></tbody>
          </table>
        </div>
      </div>
      <div class="two">
        <div class="box" style="overflow-x:auto">
          <h2>Issued by section</h2>
          <table>
            <thead>
              <tr>
                <th>Section</th>
                <th style="text-align:right">Blue orders</th>
                <th style="text-align:right">Red orders</th>
                <th style="text-align:right">Qty issued</th>
                <th style="text-align:right">Value (Rs.)</th>
              </tr>
            </thead>
            <tbody id="rpSec"></tbody>
          </table>
        </div>
        <div class="box" style="overflow-x:auto">
          <h2 style="color:var(--red)">Reorder list (low stock)</h2>
          <table>
            <thead>
              <tr>
                <th>Item</th>
                <th style="text-align:right">Balance</th>
                <th style="text-align:right">Reorder at</th>
                <th style="text-align:right">Short by</th>
              </tr>
            </thead>
            <tbody id="rpLow"></tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- DIALOGS -->
<dialog id="dCat" style="width:min(400px,94vw)">
  <form method="dialog" id="fCat">
    <h2 style="margin-top:0" id="cTitle">Add category</h2>
    <label>Category name<input id="cName" placeholder="e.g. Paint" required></label>
    <label>Category ID<input id="cCode" maxlength="6" required placeholder="PNT" style="text-transform:uppercase"><span class="hint">Prefix for item IDs (e.g. PNT-001)</span></label>
    <label>Description (optional)<input id="cDesc" placeholder="e.g. Wall and roof paint"></label>
    <div class="acts">
      <button class="btn" style="background:#6B7785" value="x" formnovalidate>Cancel</button>
      <button class="btn" style="background:var(--ink)" id="cOk">Save category</button>
    </div>
  </form>
</dialog>

<dialog id="dTx">
  <form method="dialog" id="fTx">
    <h2 style="margin-top:0" id="txTitle">Stock entry</h2>
    <label>Type<select id="xType"><option value="IN">Received</option><option value="OUT">Issued</option></select></label>
    <label>Date<input id="xDate" type="date" required></label>
    <label>Reference (GRN / bill / issue no)<input id="xRef" required></label>
    <label id="xSrcL">Goods come from<select id="xSrc"><option>Colombo Port</option><option>Shop</option></select></label>
    <label id="xSecL" hidden>Section<input id="xSec"></label>
    <label>Quantity<input id="xQty" type="number" min="1" required></label>
    <div class="acts">
      <button class="btn" style="background:#6B7785" value="x" formnovalidate>Cancel</button>
      <button class="btn" style="background:var(--ink)" id="xOk">Save</button>
    </div>
  </form>
</dialog>

<dialog id="dEdit" style="width:min(440px,94vw);border-radius:16px;padding:24px;border:0;box-shadow:var(--shadow)">
  <form method="dialog" id="fEdit">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h2 style="margin:0;font-size:20px">Edit Product</h2>
      <button type="button" class="sm" onclick="$('dEdit').close()" style="border:0;font-size:20px;cursor:pointer;background:none;color:var(--mut)">&times;</button>
    </div>
    <label style="margin-bottom:14px">Product ID
      <input id="eCode" required style="text-transform:uppercase;font-weight:700" placeholder="e.g. STA-001">
    </label>
    <label style="margin-bottom:14px">Product Name
      <input id="eName" required placeholder="e.g. A4 Paper Ream">
    </label>
    <label style="margin-bottom:14px">Category
      <select id="eCat"></select>
    </label>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 12px;margin-bottom:14px">
      <label style="margin-bottom:0">Unit
        <input id="eUnit" required placeholder="pcs, L, kg">
      </label>
      <label style="margin-bottom:0">Reorder level
        <input id="eMin" type="number" min="0" required>
      </label>
    </div>
    <div class="acts" style="margin-top:20px">
      <button type="button" class="btn" style="background:#6B7785" onclick="$('dEdit').close()">Cancel</button>
      <button type="submit" class="btn" style="background:var(--ink)" id="eOk">Save changes</button>
    </div>
  </form>
</dialog>

<dialog id="dItem" style="width:min(440px,94vw);border-radius:16px;padding:24px;border:0;box-shadow:var(--shadow)">
  <form method="dialog" id="fItem">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h2 style="margin:0;font-size:20px">Add New Item</h2>
      <button type="button" class="sm" onclick="$('dItem').close()" style="border:0;font-size:20px;cursor:pointer;background:none;color:var(--mut)">&times;</button>
    </div>

    <label style="margin-bottom:14px">Product ID
      <input id="iCode" name="product_id" required placeholder="e.g. STA-002, NUT-003" style="text-transform:uppercase;font-weight:700;font-size:15px">
      <span class="hint">Enter unique product ID (e.g. STA-002, NUT-003)</span>
    </label>

    <label style="margin-bottom:14px">Product Name
      <input id="iName" name="product_name" required placeholder="Enter product name (e.g. A4 Paper Ream, Ballpoint Pen)">
      <span class="hint">Full name of the product</span>
    </label>

    <label style="margin-bottom:14px">Category
      <select id="iCat" required></select>
    </label>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 12px;margin-bottom:14px">
      <label style="margin-bottom:0">Unit
        <input id="iUnit" value="pcs" placeholder="pcs, box, L, kg">
      </label>
      <label style="margin-bottom:0">Initial Stock
        <input id="iStock" type="number" min="0" value="0" placeholder="0">
      </label>
    </div>

    <div class="acts" style="margin-top:20px">
      <button type="button" class="btn" style="background:#6B7785" onclick="$('dItem').close()">Cancel</button>
      <button type="submit" class="btn" style="background:var(--ink)" id="iOk">+ Add Item</button>
    </div>
  </form>
</dialog>

<dialog id="dRecv">
  <form method="dialog" id="fRecv">
    <h2 style="margin-top:0">Receive stock</h2>
    <label>Goods come from<select id="rSrc"><option>Colombo Port</option><option>Shop</option></select></label>
    <label>Item<select id="rItem"></select></label>
    <label>Quantity received<input id="rQty" type="number" min="1" required></label>
    <label>Reference (GRN / bill no)<input id="rRef" required></label>
    <div class="acts">
      <button class="btn" style="background:#6B7785" value="x" formnovalidate>Cancel</button>
      <button class="btn" style="background:var(--port)" id="rOk">Save received</button>
    </div>
  </form>
</dialog>

<dialog id="dOrd">
  <form method="dialog" id="fOrd">
    <h2 style="margin-top:0">New order</h2>
    <label>Section
      <select id="oSec" required>
        <option value="Engineering">Engineering</option>
        <option value="Finance">Finance</option>
        <option value="Operations">Operations</option>
        <option value="Admin">Admin</option>
      </select>
    </label>
    <label>Item<select id="oItem"></select></label>
    <label>Quantity<input id="oQty" type="number" min="1" required></label>
    <div id="oHint" style="font-size:14px;margin-bottom:12px"></div>
    <div class="acts">
      <button class="btn" style="background:#6B7785" value="x" formnovalidate>Cancel</button>
      <button class="btn" style="background:var(--ink)" id="oOk">Send order</button>
    </div>
  </form>
</dialog>

<script>
const KEY = 'stockbook.v1';
const DB_SEED = <?php echo json_encode($initialData); ?>;

const defaultSeed = {
  items: [
    { id: 1, code: 'NUT-001', name: 'Nut M8', cat: 'Nuts', unit: 'pcs', stock: 350, min: 200 },
    { id: 2, code: 'NUT-002', name: 'Bolt M10', cat: 'Nuts', unit: 'pcs', stock: 60, min: 100 },
    { id: 3, code: 'PVC-001', name: 'PVC Pipe 1/2"', cat: 'PVC', unit: 'pcs', stock: 85, min: 30 },
    { id: 4, code: 'PVC-002', name: 'PVC Elbow 2"', cat: 'PVC', unit: 'pcs', stock: 0, min: 20 },
    { id: 5, code: 'OIL-001', name: 'Oil 15W-40', cat: 'Oil', unit: 'L', stock: 40, min: 20 },
    { id: 6, code: 'OIL-002', name: 'Oil 20W-50', cat: 'Oil', unit: 'L', stock: 0, min: 20 },
    { id: 7, code: 'STA-001', name: 'A4 Paper Ream', cat: 'Stationery', unit: 'pcs', stock: 9, min: 20 }
  ],
  cats: [
    { name: 'Nuts', code: 'NUT', unit: 'pcs', min: 100, desc: 'Fasteners and nuts', color: '#1D4ED8' },
    { name: 'PVC', code: 'PVC', unit: 'pcs', min: 20, desc: 'Pipes and fittings', color: '#0F766E' },
    { name: 'Oil', code: 'OIL', unit: 'L', min: 20, desc: 'Lubricants and oils', color: '#B45309' },
    { name: 'Stationery', code: 'STA', unit: 'pcs', min: 20, desc: 'Office and printing supplies', color: '#BE123C' }
  ],
  tx: [
    { id: 1, d: '2026-11-04', ref: 'GRN-0412', type: 'IN', src: 'Colombo Port', item: 1, qty: 200 },
    { id: 2, d: '2026-11-06', ref: 'GRN-0419', type: 'IN', src: 'Shop', item: 3, qty: 100 }
  ],
  orders: [
    { id: 1, sec: 'Workshop', item: 6, qty: 20, c: 'red', st: 'To buy', d: '2026-11-07' },
    { id: 2, sec: 'Plumbing', item: 4, qty: 30, c: 'red', st: 'To buy', d: '2026-11-07' },
    { id: 3, sec: 'Plumbing', item: 1, qty: 60, c: 'blue', st: 'Issued', d: '2026-11-07' }
  ],
  notes: []
};

let S;
try {
  S = JSON.parse(localStorage.getItem(KEY));
} catch (e) {}

if (!S) {
  S = DB_SEED ? structuredClone(DB_SEED) : structuredClone(defaultSeed);
}

const PAL = ['#0F766E', '#B45309', '#1D4ED8', '#6D28D9', '#BE123C'];
if (!S.cats || !S.cats.length) {
  S.cats = (DB_SEED && DB_SEED.cats) || structuredClone(defaultSeed.cats);
}
S.cats.forEach(c => {
  if (!c.code) c.code = (c.name.replace(/[^A-Za-z]/g, '').slice(0, 3).toUpperCase() || 'CAT');
  if (c.min == null) c.min = 10;
  if (c.desc == null) c.desc = '';
  if (!c.color) c.color = PAL[Math.floor(Math.random() * PAL.length)];
});
if (!S.notes) S.notes = [];

let curCat = null, curItem = null, editTx = null, view = 'dash';
const save = () => { try { localStorage.setItem(KEY, JSON.stringify(S)); } catch (e) {} };
const $ = id => document.getElementById(id);
const today = () => new Date().toISOString().slice(0, 10);
const item = id => S.items.find(i => i.id == id);
const catColor = n => (S.cats.find(c => c.name == n) || { color: '#17202A' }).color;
const esc = v => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');

function stat(c, l, v) {
  return `<div class="stat" style="--c:${c}"><small>${l}</small><div>${v}</div></div>`;
}

function ordRow(o) {
  const col = o.c == 'red' ? 'var(--red)' : 'var(--blue)';
  const act = (o.c == 'red' && o.st == 'To buy')
    ? `<button class="pill" style="background:${col}" data-b="${o.id}">Mark bought</button>`
    : `<span class="pill" style="background:${col}">${o.st}</span>`;
  return `<div class="row"><div><b>${esc(oName(o))} x ${o.qty}</b><small>For: ${esc(o.sec)}</small></div>${act}</div>`;
}

function ensureIds() {
  let m = Math.max(0, ...S.tx.map(t => t.id || 0));
  S.tx.forEach(t => { if (!t.id) t.id = ++m; });
}
function ensureCodes() {
  S.items.forEach(i => { if (!i.code) i.code = nextCode(i.cat); });
}

function render() {
  ensureIds();
  ensureCodes();
  const m = today().slice(0, 7);
  const rec = S.tx.filter(t => t.type == 'IN' && t.d.startsWith(m));
  const rToday = S.tx.filter(t => t.type == 'IN' && t.d == today()).reduce((a, t) => a + t.qty, 0);
  const red = S.orders.filter(o => o.c == 'red' && o.st == 'To buy').length;
  const blue = S.orders.filter(o => o.c == 'blue' && o.d == today()).length;

  $('stats').innerHTML =
    stat('#17202A', 'Total goods', S.items.length) +
    stat('#0F766E', 'Received today', rToday) +
    stat('#B42318', 'Red orders pending', red) +
    stat('#1D4ED8', 'Blue orders today', blue);

  const r = S.orders.filter(o => o.c == 'red');
  const b = S.orders.filter(o => o.c == 'blue');
  $('red').innerHTML = r.length ? r.map(ordRow).join('') : '<div class="empty">No red orders. Everything asked for was in stock.</div>';
  $('blue').innerHTML = b.length ? b.map(ordRow).join('') : '<div class="empty">No blue orders yet.</div>';

  const port = rec.filter(t => t.src == 'Colombo Port').reduce((a, t) => a + t.qty, 0);
  const shop = rec.filter(t => t.src == 'Shop').reduce((a, t) => a + t.qty, 0);
  const tot = port + shop;
  const p = tot ? Math.round(port / tot * 100) : 0;

  $('src').innerHTML = tot
    ? `<div style="width:${p}%;background:var(--port)"></div><div style="width:${100-p}%;background:var(--shop)"></div>`
    : '';
  $('srcL').innerHTML = tot
    ? `<span>Colombo Port ${p}%</span><span>Shops ${100-p}%</span>`
    : '<span>No goods received this month yet.</span>';

  // Categories table on the dashboard
  const cats = {};
  S.cats.forEach(c => { cats[c.name] = { n: 0, s: 0, l: 0 }; });
  S.items.forEach(i => {
    const c = cats[i.cat] || (cats[i.cat] = { n: 0, s: 0, l: 0 });
    c.n++;
    c.s += i.stock;
    if (i.stock <= i.min) c.l++;
  });

  // Render clickable categories table
  $('cats').innerHTML = Object.entries(cats).map(([k, c]) => {
    const catObj = S.cats.find(x => x.name == k) || { color: catColor(k), code: '' };
    return `
      <tr class="clickable-row" data-c="${esc(k)}" title="Click to open ${esc(k)} category page">
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            <span class="cat-dot" style="background:${catObj.color || catColor(k)}"></span>
            <div>
              <b style="color:${catObj.color || catColor(k)};font-size:15px">${esc(k)}</b>
              ${catObj.code ? `<span class="code" style="margin-left:6px;font-size:11px">${esc(catObj.code)}</span>` : ''}
            </div>
          </div>
        </td>
        <td>${c.n} items</td>
        <td class="n">${c.s}</td>
        <td class="n">${c.l > 0 ? `<span class="pill" style="background:var(--red);padding:2px 8px;font-size:12px">${c.l} LOW</span>` : '<span style="color:var(--mut)">0</span>'}</td>
        <td style="text-align:right">
          <button class="cat-view-btn" data-c="${esc(k)}" type="button">Open &rarr;</button>
        </td>
      </tr>
    `;
  }).join('');

  const opts = S.items.map(i => `<option value="${i.id}">${i.code} - ${esc(i.name)} (${esc(i.cat)})</option>`).join('');
  $('rItem').innerHTML = opts;
  $('oItem').innerHTML = opts;
  const lv = $('lItem').value;
  $('lItem').innerHTML = opts;
  if (lv) $('lItem').value = lv;

  hint();
  renderCats();
  renderItem();
  renderLedger();
  renderOrders();
  renderReports();
  renderNotes();
  save();
}

/**
 * Opens the specific category detail page
 */
function openCategory(catName) {
  window.location.href = 'inventory.php?cat=' + encodeURIComponent(catName);
}

// Click on dashboard category table opens that category!
$('cats').onclick = e => {
  const row = e.target.closest('[data-c]');
  if (!row) return;
  openCategory(row.dataset.c);
};

// Categories view grid cards click
$('cgrid').onclick = e => {
  const b = e.target.closest('.ccard');
  if (!b) return;
  openCategory(b.dataset.c);
};

function renderCats() {
  $('bgCats').textContent = S.items.filter(i => i.stock <= i.min).length || '';
  $('bgOrd').textContent = S.orders.filter(o => o.c == 'red' && o.st == 'To buy').length || '';

  $('cgrid').innerHTML = S.cats.map(c => {
    const its = S.items.filter(i => i.cat == c.name);
    return `
      <button class="ccard" style="--c:${c.color}" data-c="${esc(c.name)}">
        <b>${esc(c.name)}</b>
        <small>${esc(c.code)} &middot; ${its.length} items &middot; balance ${its.reduce((a,i)=>a+i.stock,0)}</small>
        ${c.desc ? `<small style="display:block;margin-top:4px">${esc(c.desc)}</small>` : ''}
        <div style="margin-top:10px;font-size:13px;font-weight:700;color:${c.color}">View goods &rarr;</div>
      </button>
    `;
  }).join('');

  if (curCat) {
    const c = S.cats.find(c => c.name == curCat) || { name: curCat, color: '#17202A', code: '', desc: '', unit: 'pcs', min: 10 };
    $('cdTitle').textContent = curCat;
    $('cdTitle').style.color = c.color;
    if ($('cdDesc')) {
      $('cdDesc').textContent = c.desc || '';
      $('cdDesc').style.display = c.desc ? 'block' : 'none';
    }

    const allIts = S.items.filter(i => i.cat == curCat);
    const filterVal = ($('cdSearch') ? $('cdSearch').value.trim().toLowerCase() : '');
    const its = filterVal ? allIts.filter(i => (i.code + ' ' + i.name).toLowerCase().includes(filterVal)) : allIts;

    $('cdRows').innerHTML = its.length ? its.map(i => `
      <tr data-i="${i.id}" class="clickable-row">
        <td><span class="code">${esc(i.code)}</span></td>
        <td>
          <button class="back" data-i="${i.id}" style="font-weight:700;font-size:15px">${esc(i.name)}</button>
          ${i.rack ? `<div class="hint">Rack: ${esc(i.rack)}</div>` : ''}
        </td>
        <td>${esc(i.unit)}</td>
        <td class="n" style="font-size:16px;${i.stock <= i.min ? 'color:var(--red);' : ''}">${i.stock}</td>
        <td class="n">${i.min}</td>
        <td>${i.stock <= i.min ? '<span class="pill" style="background:var(--red)">LOW</span>' : '<span class="pill" style="background:var(--port)">OK</span>'}</td>
        <td style="text-align:right">
          <button class="sm" data-i="${i.id}" type="button">Ledger &rarr;</button>
        </td>
      </tr>
    `).join('') : `<tr><td colspan="7" style="color:var(--mut);text-align:center;padding:24px">${filterVal ? 'No items match your filter.' : 'No goods yet in this category. Click <b>+ Add item</b> to add goods.'}</td></tr>`;
  }
}

// Category search filter input
$('cdSearch').oninput = renderCats;

// Navigation helpers inside Category Detail
$('bBack').onclick = () => { curCat = null; curItem = null; go('cats'); };
$('bBackDash').onclick = () => { curCat = null; curItem = null; go('dash'); };

function hint() {
  const i = item($('oItem').value);
  const q = +$('oQty').value || 0;
  if (!i) return;
  $('oHint').innerHTML = q > i.stock
    ? `<b style="color:var(--red)">RED order</b>: only ${i.stock} in stock. Stock keeper buys outside.`
    : `<b style="color:var(--blue)">BLUE order</b>: ${i.stock} in stock. Issued from stock.`;
}

$('red').onclick = e => {
  const id = e.target.dataset.b;
  if (!id) return;
  const o = S.orders.find(o => o.id == id);
  if (o) o.st = 'Bought';
  render();
};

$('bRecv').onclick = () => $('dRecv').showModal();
$('bOrd').onclick = () => { $('oQty').value = ''; hint(); $('dOrd').showModal(); };
$('oItem').onchange = hint;
$('oQty').oninput = hint;

$('fRecv').onsubmit = async e => {
  if (e.submitter && e.submitter.id != 'rOk') return;
  const itemId = $('rItem').value;
  const q = +$('rQty').value;
  const src = $('rSrc').value;
  const ref = $('rRef').value;
  const i = item(itemId);
  if (!i || q <= 0) return;

  try {
    const fd = new FormData();
    fd.append('a', 'receive');
    fd.append('item_id', itemId);
    fd.append('qty', q);
    fd.append('src', src);
    fd.append('ref', ref);
    await fetch('item_action.php', { method: 'POST', body: fd });
  } catch (err) {}

  i.stock += q;
  S.tx.push({ id: Math.max(0, ...S.tx.map(t=>t.id||0))+1, d: today(), ref: ref, type: 'IN', src: src, item: i.id, qty: q });
  $('fRecv').reset();
  render();
};

$('fOrd').onsubmit = e => {
  if (e.submitter.id != 'oOk') return;
  const i = item($('oItem').value), q = +$('oQty').value;
  const id = Math.max(0, ...S.orders.map(o => o.id)) + 1;
  if (q > i.stock) {
    S.orders.push({ id, sec: $('oSec').value, item: i.id, qty: q, c: 'red', st: 'To buy', d: today() });
  } else {
    i.stock -= q;
    S.tx.push({ id: Math.max(0, ...S.tx.map(t=>t.id||0))+1, d: today(), ref: 'ISS-' + id, type: 'OUT', src: $('oSec').value, item: i.id, qty: q });
    S.orders.push({ id, sec: $('oSec').value, item: i.id, qty: q, c: 'blue', st: 'Issued', d: today() });
  }
  render();
};

const pre = c => (c.replace(/[^A-Za-z]/g, '').slice(0, 3).toUpperCase() || 'ITM');
function nextCode(cat) {
  const cc = S.cats.find(c => c.name == cat);
  const p = cc && cc.code ? cc.code : pre(cat);
  let n = S.items.filter(i => i.code && i.code.startsWith(p + '-')).length + 1, c;
  do { c = p + '-' + String(n++).padStart(3, '0'); } while (S.items.some(i => i.code == c));
  return c;
}

const eff = t => t.type == 'IN' ? t.qty : -t.qty;

function renderItem() {
  if (!curItem) return;
  const it = item(curItem);
  if (!it) { curItem = null; return; }
  $('bBackItem').textContent = '\u2190 ' + it.cat;
  $('idTitle').textContent = it.name;
  $('idTitle').style.color = catColor(it.cat);
  $('idSub').textContent = 'ID: ' + it.code + ' \u00b7 Category: ' + it.cat + ' \u00b7 Unit: ' + it.unit + (it.rack ? ' \u00b7 Rack: ' + it.rack : '') + ' \u00b7 Rate: Rs. ' + money(it.rate || 0);

  const rows = S.tx.filter(t => t.item == it.id).sort((a, b) => a.d.localeCompare(b.d) || a.id - b.id);
  $('idStats').innerHTML =
    stat('#17202A', 'Balance now', it.stock) +
    stat('#0F766E', 'Total received', sum(rows.filter(t => t.type == 'IN').map(t => t.qty))) +
    stat('#1D4ED8', 'Total issued', sum(rows.filter(t => t.type == 'OUT').map(t => t.qty))) +
    stat(it.stock <= it.min ? '#B42318' : '#52606D', 'Reorder at', it.min);

  let bal = it.stock - sum(rows.map(eff));
  let h = `<tr><td>-</td><td>Opening balance</td><td>-</td><td></td><td></td><td class="n">${bal}</td><td></td></tr>`;
  rows.forEach(t => {
    bal += eff(t);
    h += `<tr><td>${t.d}</td><td>${t.ref}</td><td>${t.type == 'IN' ? `<span class="pill" style="background:${t.src == 'Colombo Port' ? 'var(--port)' : 'var(--shop)'}">${t.src}</span>` : `<span class="pill" style="background:var(--blue)">Blue</span> ${t.src}`}</td><td class="n" style="color:var(--port)">${t.type == 'IN' ? t.qty : ''}</td><td class="n" style="color:var(--blue)">${t.type == 'OUT' ? t.qty : ''}</td><td class="n">${bal}</td><td style="white-space:nowrap"><button class="sm" data-e="${t.id}">Update</button> <button class="sm d" data-x="${t.id}">Delete</button></td></tr>`;
  });
  $('idRows').innerHTML = h;
}

function renderLedger() {
  const id = +$('lItem').value;
  if (!id) return;
  const it = item(id);
  if (!it) return;
  const rows = S.tx.filter(t => t.item == id).sort((a, b) => a.d.localeCompare(b.d));
  let bal = it.stock - rows.reduce((a, t) => a + (t.type == 'IN' ? t.qty : -t.qty), 0);
  let h = `<tr><td>-</td><td>Opening balance</td><td>-</td><td></td><td></td><td class="n">${bal}</td></tr>`;
  rows.forEach(t => {
    bal += t.type == 'IN' ? t.qty : -t.qty;
    h += `<tr><td>${t.d}</td><td>${t.ref}</td><td>${t.type == 'IN' ? `<span class="pill" style="background:${t.src == 'Colombo Port' ? 'var(--port)' : 'var(--shop)'}">${t.src}</span>` : `<span class="pill" style="background:var(--blue)">Blue</span> ${t.src}`}</td><td class="n" style="color:var(--port)">${t.type == 'IN' ? t.qty : ''}</td><td class="n" style="color:var(--blue)">${t.type == 'OUT' ? t.qty : ''}</td><td class="n">${bal}</td></tr>`;
  });
  $('lRows').innerHTML = h;
}

function renderOrders() {
  $('oRows').innerHTML = [...S.orders].reverse().map(o => `
    <tr>
      <td>${o.d}</td>
      <td>${esc(o.sec)}</td>
      <td><b>${esc(oName(o))}</b></td>
      <td class="n">${o.qty}</td>
      <td><span class="pill" style="background:${o.c == 'red' ? 'var(--red)' : 'var(--blue)'}">${o.c.toUpperCase()}</span></td>
      <td>${o.st}</td>
    </tr>
  `).join('');
}

const sum = a => a.reduce((x, y) => x + y, 0);
function months() {
  const m = new Set([today().slice(0, 7)]);
  S.tx.forEach(t => m.add(t.d.slice(0, 7)));
  S.orders.forEach(o => m.add(o.d.slice(0, 7)));
  return [...m].sort().reverse();
}

function rpData() {
  const m = $('rpMon').value;
  return { m, tx: S.tx.filter(t => t.d.startsWith(m)), od: S.orders.filter(o => o.d.startsWith(m)) };
}

function chart(el, rows, cols, names, label) {
  const W = 520, H = 270, L = 44, B = 48, T = 26, R = 10, pw = W - L - R, ph = H - T - B;
  const mx = Math.max(0, ...rows.flatMap(r => r.v));
  if (!rows.length || !mx) {
    el.innerHTML = '<div style="color:var(--mut);padding:30px 0;text-align:center">No data for this month yet.</div>';
    return;
  }
  const step = Math.pow(10, Math.floor(Math.log10(mx)));
  let top = Math.ceil(mx / step) * step;
  if (top / step < 2) top = step * 2;
  const y = v => T + ph - (v / top) * ph, gw = pw / rows.length, n = cols.length, bw = Math.min(40, (gw * .7) / n);
  let g = '';
  for (let i = 0; i <= 4; i++) {
    const v = top / 4 * i;
    g += `<line x1="${L}" x2="${W - R}" y1="${y(v)}" y2="${y(v)}" stroke="#E1E5EA"/><text x="${L - 6}" y="${y(v) + 4}" text-anchor="end" font-size="11" fill="#52606D">${Math.round(v)}</text>`;
  }
  rows.forEach((r, k) => {
    const cx = L + gw * k + gw / 2, x0 = cx - (bw * n) / 2;
    r.v.forEach((v, j) => {
      const h = (v / top) * ph;
      g += `<rect x="${x0 + j * bw}" y="${y(v)}" width="${bw - 3}" height="${h}" rx="3" fill="${cols[j]}"/>`;
      if (v) g += `<text x="${x0 + j * bw + (bw - 3) / 2}" y="${y(v) - 5}" text-anchor="middle" font-size="11" font-weight="700" fill="#17202A">${v}</text>`;
    });
    const t = r.l.length > 12 ? r.l.slice(0, 11) + '.' : r.l;
    g += `<text x="${cx}" y="${H - B + 18}" text-anchor="middle" font-size="12" fill="#17202A">${t}</text>`;
  });
  const lg = names.map((nm, j) => `<span style="display:inline-flex;align-items:center;gap:6px;margin-right:14px"><i style="width:12px;height:12px;border-radius:3px;background:${cols[j]};display:inline-block"></i>${nm}</span>`).join('');
  el.innerHTML = `<svg viewBox="0 0 ${W} ${H}" role="img" aria-label="${label}" style="width:100%;height:auto;display:block">${g}</svg><div style="font-size:13px;margin-top:4px">${lg}</div>`;
}

function renderReports() {
  const sel = $('rpMon'), cur = sel.value;
  sel.innerHTML = months().map(m => `<option>${m}</option>`).join('');
  if (cur && months().includes(cur)) sel.value = cur;
  const { m, tx, od } = rpData();
  const rec = tx.filter(t => t.type == 'IN'), iss = tx.filter(t => t.type == 'OUT');
  $('rpTitle').textContent = 'Stock report: ' + m;
  $('rpStats').innerHTML =
    stat('#0F766E', 'Goods received', sum(rec.map(t => t.qty))) +
    stat('#1D4ED8', 'Goods issued', sum(iss.map(t => t.qty))) +
    stat('#1D4ED8', 'Blue orders', od.filter(o => o.c == 'blue').length) +
    stat('#B42318', 'Red orders', od.filter(o => o.c == 'red').length);

  $('rpCat').innerHTML = S.cats.map(c => {
    const ids = S.items.filter(i => i.cat == c.name).map(i => i.id);
    return `
      <tr class="clickable-row" data-c="${esc(c.name)}" title="Click to view category">
        <td><b style="color:${c.color}">${esc(c.name)}</b></td>
        <td class="n">${sum(rec.filter(t => ids.includes(t.item)).map(t => t.qty))}</td>
        <td class="n">${sum(iss.filter(t => ids.includes(t.item)).map(t => t.qty))}</td>
        <td class="n">${sum(S.items.filter(i => i.cat == c.name).map(i => i.stock))}</td>
      </tr>
    `;
  }).join('');

  const tot = sum(rec.map(t => t.qty));
  $('rpSrc').innerHTML = ['Colombo Port', 'Shop'].map(k => {
    const r = rec.filter(t => t.src == k);
    const q = sum(r.map(t => t.qty));
    return `<tr><td><span class="pill" style="background:${k == 'Shop' ? 'var(--shop)' : 'var(--port)'}">${k == 'Shop' ? 'Shops' : k}</span></td><td class="n">${r.length}</td><td class="n">${q}</td><td class="n">${tot ? Math.round(q / tot * 100) : 0}%</td></tr>`;
  }).join('');

  const secs = [...new Set(od.map(o => o.sec))];
  $('rpSec').innerHTML = secs.length ? secs.map(x => `
    <tr>
      <td><b>${esc(x)}</b></td>
      <td class="n">${od.filter(o => o.sec == x && o.c == 'blue').length}</td>
      <td class="n">${od.filter(o => o.sec == x && o.c == 'red').length}</td>
      <td class="n">${sum(iss.filter(t => t.src == x).map(t => t.qty))}</td>
      <td class="n">${money(sum(S.notes.filter(n => n.date.startsWith(m) && n.unit == x).map(noteTotal)))}</td>
    </tr>
  `).join('') : '<tr><td colspan="5" style="color:var(--mut)">No orders this month.</td></tr>';

  chart($('chCat'), S.cats.map(c => {
    const ids = S.items.filter(i => i.cat == c.name).map(i => i.id);
    return { l: c.name, v: [sum(rec.filter(t => ids.includes(t.item)).map(t => t.qty)), sum(iss.filter(t => ids.includes(t.item)).map(t => t.qty))] };
  }), ['#0F766E', '#1D4ED8'], ['Received', 'Issued'], 'Bar chart of goods received and issued by category');

  chart($('chSec'), secs.map(x => ({ l: x, v: [sum(iss.filter(t => t.src == x).map(t => t.qty))] })), ['#1D4ED8'], ['Qty issued'], 'Bar chart of goods issued by section');

  const low = S.items.filter(i => i.stock <= i.min);
  $('rpLow').innerHTML = low.length ? low.map(i => `<tr><td><b>${esc(i.name)}</b></td><td class="n">${i.stock}</td><td class="n">${i.min}</td><td class="n" style="color:var(--red)">${i.min - i.stock}</td></tr>`).join('') : '<tr><td colspan="4" style="color:var(--mut)">Nothing to reorder.</td></tr>';
}

// Click on Category in Reports also opens that category!
$('rpCat').onclick = e => {
  const row = e.target.closest('[data-c]');
  if (!row) return;
  openCategory(row.dataset.c);
};

$('rpMon').onchange = renderReports;
$('bPrint').onclick = () => window.print();
$('bCsv').onclick = () => {
  const { m, tx } = rpData();
  const rows = [['ID', 'Category', 'Item', 'Unit', 'Received', 'Issued', 'Balance now']];
  S.items.forEach(i => {
    const t = tx.filter(x => x.item == i.id);
    rows.push([i.code, i.cat, i.name, i.unit, sum(t.filter(x => x.type == 'IN').map(x => x.qty)), sum(t.filter(x => x.type == 'OUT').map(x => x.qty)), i.stock]);
  });
  const csv = rows.map(r => r.map(v => '"' + String(v).replace(/"/g, '""') + '"').join(',')).join('\n');
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
  a.download = 'stock-report-' + m + '.csv';
  a.click();
};

const money = n => (+n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
function oName(o) { const i = item(o.item); return i ? i.name : (o.desc || 'Item'); }
const noteTotal = n => sum(n.lines.map(l => l.issued * l.rate));

let nMode = 'list', curNote = null, nl = [], nType = 'stock';
const blankLine = () => ({ job: '', req: '', item: '', desc: '', issued: '', rate: '' });
function nShow(m) {
  nMode = m;
  $('nList').hidden = m != 'list';
  $('nForm').hidden = m != 'form';
  $('nView').hidden = m != 'view';
}
function nextSerial() { return Math.max(50280, ...S.notes.map(n => +n.serial || 0)) + 1; }
function openNoteForm() {
  nType = 'stock';
  nl = [blankLine()];
  $('nSerial').value = nextSerial();
  $('nDate').value = today();
  $('nUnit').value = '';
  $('nStore').value = '';
  document.querySelector('input[name=nType][value=stock]').checked = true;
  renderLines();
  nShow('form');
}

function renderLines() {
  const st = nType == 'stock';
  $('nHint').innerHTML = st
    ? '<b style="color:var(--blue)">Stock item (Blue order):</b> issued from stock.'
    : '<b style="color:var(--red)">Non-stock item (Red order):</b> not in stock. Stock keeper buys outside.';
  const opts = '<option value="">Select item</option>' + S.items.map(i => `<option value="${i.id}">${i.code} - ${esc(i.name)} (bal ${i.stock})</option>`).join('');
  $('nLines').innerHTML = nl.map((l, k) => {
    const it = item(l.item);
    const ic = st
      ? `<select data-k="${k}" data-f="item" aria-label="Item">${opts.replace('value="' + l.item + '"', 'value="' + l.item + '" selected')}</select>`
      : `<input data-k="${k}" data-f="desc" value="${esc(l.desc)}" placeholder="Description of article" aria-label="Description">`;
    return `<tr><td><input data-k="${k}" data-f="job" value="${esc(l.job)}" aria-label="Job no"></td><td><input type="number" min="0" step="any" data-k="${k}" data-f="req" value="${esc(l.req)}" aria-label="Quantity required"></td><td>${ic}</td><td>${it && st ? `<span class="code">${it.code}</span><div class="hint">Rack: ${esc(it.rack || '-')}</div>` : '-'}</td><td><input type="number" min="0" step="any" data-k="${k}" data-f="issued" value="${esc(l.issued)}" aria-label="Quantity issued"></td><td class="n" id="nb${k}"></td><td><input type="number" min="0" step="0.01" data-k="${k}" data-f="rate" value="${esc(l.rate)}" aria-label="Rate"></td><td class="n" id="nv${k}"></td><td><button type="button" class="sm d" data-rm="${k}" aria-label="Remove line">X</button></td></tr>`;
  }).join('');
  updateCalc();
}

function updateCalc() {
  let tot = 0;
  const used = {};
  nl.forEach((l, k) => {
    const q = +l.issued || 0, r = +l.rate || 0;
    tot += q * r;
    $('nv' + k).textContent = money(q * r);
    const it = item(l.item);
    if (nType == 'stock' && it) {
      used[it.id] = (used[it.id] || 0) + q;
      const b = it.stock - used[it.id];
      $('nb' + k).innerHTML = b < 0 ? `<span style="color:var(--red)">${b}</span>` : b;
    } else $('nb' + k).textContent = '-';
  });
  $('nTot').textContent = money(tot);
}

$('nLines').oninput = e => {
  const t = e.target, k = t.dataset.k, f = t.dataset.f;
  if (k == null || !f) return;
  nl[k][f] = t.value;
  if (f == 'issued' || f == 'rate') updateCalc();
};
$('nLines').onchange = e => {
  const t = e.target;
  if (t.dataset.f == 'item') {
    const l = nl[t.dataset.k], it = item(t.value);
    l.item = t.value;
    l.desc = it ? it.name : '';
    l.rate = it && it.rate ? it.rate : '';
    renderLines();
  }
};
$('nLines').onclick = e => {
  const b = e.target.closest('[data-rm]');
  if (!b) return;
  nl.splice(+b.dataset.rm, 1);
  if (!nl.length) nl.push(blankLine());
  renderLines();
};
$('bAddLine').onclick = () => { nl.push(blankLine()); renderLines(); };
document.querySelectorAll('input[name=nType]').forEach(r => r.onchange = () => {
  nType = r.value;
  nl = nl.map(l => ({ ...l, item: '', desc: '', rate: '' }));
  renderLines();
});
$('bNewNote').onclick = openNoteForm;
$('bNfBack').onclick = () => nShow('list');
$('nRows').onclick = e => {
  const r = e.target.closest('[data-n]');
  if (!r) return;
  curNote = +r.dataset.n;
  nShow('view');
  renderNotes();
};
$('fNote').onsubmit = e => {
  e.preventDefault();
  const serial = $('nSerial').value.trim(), date = $('nDate').value, unit = $('nUnit').value.trim();
  if (!serial || !date || !unit) { alert('Please fill Serial No, Date and Indenting unit.'); return; }
  if (S.notes.some(n => n.serial == serial)) { alert('Serial No ' + serial + ' already exists.'); return; }
  const lines = nl.filter(l => (+l.issued || 0) > 0 || l.item || (l.desc && l.desc.trim()));
  if (!lines.length) { alert('Add at least one line.'); return; }
  const used = {};
  for (const l of lines) {
    const q = +l.issued || 0;
    if (q <= 0) { alert('Enter the quantity for every line.'); return; }
    if (nType == 'stock') {
      const it = item(l.item);
      if (!it) { alert('Select an item on every line.'); return; }
      used[it.id] = (used[it.id] || 0) + q;
      if (used[it.id] > it.stock) { alert(it.name + ': only ' + it.stock + ' in stock.'); return; }
    } else if (!(l.desc && l.desc.trim())) { alert('Enter a description on every line.'); return; }
  }
  const id = Math.max(0, ...S.notes.map(n => n.id)) + 1;
  const note = {
    id, serial, date, unit,
    store: $('nStore').value.trim(),
    kind: nType,
    status: nType == 'stock' ? 'Issued' : 'To buy',
    lines: lines.map(l => {
      const it = item(l.item);
      return { job: l.job, req: +l.req || 0, item: it && nType == 'stock' ? it.id : null, desc: it && nType == 'stock' ? it.name : l.desc.trim(), issued: +l.issued, rate: +l.rate || 0 };
    })
  };
  S.notes.push(note);
  let oid = Math.max(0, ...S.orders.map(o => o.id));
  note.lines.forEach(l => {
    oid++;
    if (nType == 'stock') {
      const it = item(l.item);
      it.stock -= l.issued;
      l.bal = it.stock;
      S.tx.push({ id: Math.max(0, ...S.tx.map(t=>t.id||0))+1, d: date, ref: 'ISS-' + serial, type: 'OUT', src: unit, item: it.id, qty: l.issued, note: id });
      S.orders.push({ id: oid, sec: unit, item: it.id, qty: l.issued, c: 'blue', st: 'Issued', d: date, note: id });
    } else {
      S.orders.push({ id: oid, sec: unit, item: null, desc: l.desc, qty: l.issued, c: 'red', st: 'To buy', d: date, note: id });
    }
  });
  curNote = id;
  nShow('view');
  render();
};

function renderNotes() {
  $('nRows').innerHTML = S.notes.length ? [...S.notes].reverse().map(n => `
    <tr data-n="${n.id}" style="cursor:pointer">
      <td><b>${esc(n.serial)}</b></td>
      <td>${n.date}</td>
      <td>${esc(n.unit)}</td>
      <td><span class="pill" style="background:${n.kind == 'stock' ? 'var(--blue)' : 'var(--red)'}">${n.kind == 'stock' ? 'BLUE - Stock' : 'RED - Non-stock'}</span></td>
      <td class="n">${n.lines.length}</td>
      <td class="n">${money(noteTotal(n))}</td>
      <td>${n.status}</td>
    </tr>
  `).join('') : '<tr><td colspan="7" style="color:var(--mut)">No issue notes yet. Click + New Issue Note.</td></tr>';

  const n = S.notes.find(x => x.id == curNote);
  if (!n) { $('nPaper').innerHTML = ''; return; }
  const st = n.kind == 'stock';
  const rows = n.lines.map(l => {
    const it = item(l.item);
    return `<tr><td>${esc(l.job)}</td><td style="text-align:right">${l.req || ''}</td><td>${it ? esc(it.code) : ''}</td><td>${it ? esc(it.rack || '') : ''}</td><td>${esc(l.desc)}</td><td style="text-align:right">${l.issued}</td><td style="text-align:right">${st && l.bal != null ? l.bal : ''}</td><td style="text-align:right">${money(l.rate)}</td><td style="text-align:right">${money(l.issued * l.rate)}</td></tr>`;
  }).join('');
  const acts = !st ? (n.status == 'To buy' ? '<button class="btn" style="background:var(--red)" data-act="bought">Mark bought</button>' : n.status == 'Bought' ? '<button class="btn" style="background:var(--port)" data-act="deliver">Mark delivered</button>' : '') : '';
  $('nPaper').innerHTML = `
    <div class="noprint" style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px">
      <button class="back" data-act="back">&larr; Issue notes</button>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <span class="pill" style="background:${st ? 'var(--blue)' : 'var(--red)'}">${n.status}</span>
        ${acts}
        <button class="btn" style="background:var(--ink)" data-act="print">Print</button>
        <button class="btn" style="background:#fff;color:var(--red);border:2px solid var(--red)" data-act="del">Delete note</button>
      </div>
    </div>
    <div class="paper">
      <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <div>
          <div style="font-size:20px;font-weight:700">STORES REQUISITION / ISSUE NOTE</div>
          <div style="margin-top:6px">Stock item [${st ? 'X' : ' '}] &nbsp; Non-stock item [${st ? ' ' : 'X'}]</div>
        </div>
        <div style="text-align:right">
          Serial No. <b style="font-size:22px">${esc(n.serial)}</b>
          <div>Date: <b>${n.date}</b></div>
        </div>
      </div>
      <div>Indenting unit: <b>${esc(n.unit)}</b> &nbsp;&nbsp; Stores issue unit: <b>${esc(n.store || '-')}</b></div>
      <div style="overflow-x:auto">
        <table class="pt">
          <thead>
            <tr><th>Job No.</th><th>Qty req.</th><th>Code</th><th>Rack</th><th>Description</th><th>Qty issued</th><th>Balance</th><th>Rate</th><th>Value</th></tr>
          </thead>
          <tbody>${rows}<tr><td colspan="8" style="text-align:right;font-weight:700">Total (Rs.)</td><td style="text-align:right;font-weight:700">${money(noteTotal(n))}</td></tr></tbody>
        </table>
      </div>
    </div>
  `;
}

$('nPaper').onclick = e => {
  const b = e.target.closest('[data-act]');
  if (!b) return;
  const n = S.notes.find(x => x.id == curNote), a = b.dataset.act;
  if (a == 'back') { curNote = null; nShow('list'); renderNotes(); return; }
  if (a == 'print') { window.print(); return; }
  if (a == 'bought') {
    const bill = prompt('Bill / invoice no (optional):', '');
    if (bill === null) return;
    n.status = 'Bought';
    n.bill = bill;
    S.orders.filter(o => o.note == n.id).forEach(o => o.st = 'Bought');
    render();
    return;
  }
  if (a == 'deliver') {
    n.status = 'Delivered';
    S.orders.filter(o => o.note == n.id).forEach(o => o.st = 'Delivered');
    render();
    return;
  }
  if (a == 'del') {
    if (!confirm('Delete issue note ' + n.serial + '?')) return;
    if (n.kind == 'stock') n.lines.forEach(l => { const it = item(l.item); if (it) it.stock += l.issued; });
    S.tx = S.tx.filter(t => t.note != n.id);
    S.orders = S.orders.filter(o => o.note != n.id);
    S.notes = S.notes.filter(x => x.id != n.id);
    curNote = null;
    nShow('list');
    render();
  }
};

/**
 * Switch top-level views
 */
function go(v) {
  if (v == 'recv') { $('dRecv').showModal(); return; }
  view = v;
  if (v != 'cats') { curCat = null; curItem = null; }
  if (v != 'notes') { nMode = 'list'; curNote = null; }

  document.querySelectorAll('.view').forEach(e => {
    const on = e.id == 'v-' + v;
    e.hidden = !on;
    e.style.display = on ? 'flex' : 'none';
  });

  document.querySelectorAll('#nav a').forEach(a => a.classList.toggle('on', a.dataset.v == v));

  $('catList').hidden = !!curCat;
  $('catDet').hidden = !curCat || !!curItem;
  $('itemDet').hidden = !curItem;

  renderCats();
  renderItem();
  renderLedger();
  renderOrders();
  renderReports();
  renderNotes();
  nShow(nMode);
}

$('nav').onclick = e => {
  const a = e.target.closest('a[data-v]');
  if (!a) return;
  e.preventDefault();
  go(a.dataset.v);
};

let editCat = null, codeTouched = false;
function openCat(c) {
  editCat = c ? c.name : null;
  codeTouched = !!c;
  $('fCat').reset();
  $('cName').value = c ? c.name : '';
  $('cCode').value = c ? c.code : '';
  $('cDesc').value = c ? c.desc : '';
  $('cTitle').textContent = c ? 'Edit category' : 'Add category';
  $('cOk').textContent = c ? 'Save changes' : 'Save category';
  $('dCat').showModal();
}
$('bCat').onclick = () => openCat(null);
$('bECat').onclick = () => openCat(S.cats.find(c => c.name == curCat));

function autoCatCode(name) {
  let base = pre(name).slice(0, 4) || 'CAT', c = base, i = 2;
  while (S.cats.some(x => x.code == c && x.name != editCat)) {
    c = base.slice(0, i < 10 ? 4 : 3) + i;
    i++;
  }
  return c;
}
$('cName').oninput = () => { if (!codeTouched) $('cCode').value = autoCatCode($('cName').value); };
$('cCode').oninput = () => { codeTouched = true; $('cCode').value = $('cCode').value.replace(/[^A-Za-z0-9]/g, '').toUpperCase(); };

$('bDCat').onclick = async () => {
  const catObj = S.cats.find(c => c.name == curCat);
  const its = S.items.filter(i => i.cat == curCat);
  const msg = its.length
    ? 'Delete category "' + curCat + '" together with its ' + its.length + ' goods? This cannot be undone.'
    : 'Delete category "' + curCat + '"?';
  if (!confirm(msg)) return;

  try {
    const fd = new FormData();
    fd.append('a', 'del');
    fd.append('id', catObj ? catObj.id : 0);
    fd.append('name', curCat);
    const res = await fetch('category_action.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) {
      alert('Error deleting category: ' + (data.error || 'Unknown error'));
      return;
    }
    window.location.href = 'categories.php';
  } catch (err) {
    alert('Failed to delete category: ' + err.message);
  }
};

function openAddItem(cat) {
  const modal = $('dItem');
  if (!modal) return;

  const targetCat = cat || curCat || (S.cats.length ? S.cats[0].name : 'Stationery');
  $('iCat').innerHTML = S.cats.map(c => `<option value="${esc(c.name)}">${esc(c.name)}</option>`).join('');
  $('iCat').value = targetCat;

  const c = S.cats.find(c => c.name == targetCat) || { unit: 'pcs', min: 10 };
  $('iUnit').value = c.unit || 'pcs';
  $('iCode').value = nextCode(targetCat);
  $('iName').value = '';
  if ($('iStock')) $('iStock').value = 0;

  if (typeof modal.showModal === 'function') {
    modal.showModal();
  } else {
    modal.setAttribute('open', '');
  }
  setTimeout(() => {
    try { $('iName').focus(); } catch (e) {}
  }, 50);
}

$('iCat').onchange = () => {
  const chosen = $('iCat').value;
  const c = S.cats.find(x => x.name == chosen);
  if (c) {
    $('iUnit').value = c.unit || 'pcs';
    $('iCode').value = nextCode(chosen);
  }
};

$('bItem').onclick = () => openAddItem(curCat);
if ($('bDashAddItem')) $('bDashAddItem').onclick = () => openAddItem(null);
if ($('bCatAddItem')) $('bCatAddItem').onclick = () => openAddItem(null);

$('fCat').onsubmit = async e => {
  if (e.submitter && e.submitter.id != 'cOk') return;
  e.preventDefault();
  const n = $('cName').value.trim(), cd = $('cCode').value.trim().toUpperCase();
  const desc = $('cDesc').value.trim();
  const catObj = editCat ? S.cats.find(c => c.name == editCat) : null;

  try {
    const fd = new FormData();
    fd.append('a', 'save');
    fd.append('id', catObj ? catObj.id : 0);
    fd.append('original_name', editCat || '');
    fd.append('name', n);
    fd.append('code', cd);
    fd.append('description', desc);

    const res = await fetch('category_action.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) {
      alert('Error saving category: ' + (data.error || 'Unknown error'));
      return;
    }
    $('dCat').close();
    window.location.href = 'inventory.php?cat=' + encodeURIComponent(n);
  } catch (err) {
    alert('Failed to save category: ' + err.message);
  }
};

$('fItem').onsubmit = async e => {
  if (e.submitter && e.submitter.id != 'iOk') return;
  e.preventDefault();

  const cd = $('iCode').value.trim().toUpperCase();
  const nm = $('iName').value.trim();
  const cat = $('iCat').value;
  const unit = ($('iUnit') ? $('iUnit').value.trim() : '') || 'pcs';
  const stock = ($('iStock') ? +$('iStock').value : 0) || 0;

  if (!cd) {
    alert('Please enter a Product ID.');
    $('iCode').focus();
    return;
  }
  if (!nm) {
    alert('Please enter a Product Name.');
    $('iName').focus();
    return;
  }

  try {
    const fd = new FormData();
    fd.append('a', 'add');
    fd.append('code', cd);
    fd.append('name', nm);
    fd.append('cat', cat);
    fd.append('unit', unit);
    fd.append('stock', stock);
    fd.append('min', 10);

    const res = await fetch('item_action.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) {
      alert('Error saving item: ' + (data.error || 'Failed to add item'));
      return;
    }

    const nid = (data.item && data.item.id) ? data.item.id : (Math.max(0, ...S.items.map(i => i.id || 0)) + 1);
    const c = S.cats.find(x => x.name == cat) || { min: 10 };
    S.items.push({
      id: nid,
      code: cd,
      name: nm,
      cat: cat,
      unit: unit,
      stock: stock,
      min: c.min || 10,
      rack: '',
      rate: 0
    });

    if (stock > 0) {
      const tid = Math.max(0, ...S.tx.map(t => t.id || 0)) + 1;
      S.tx.push({
        id: tid,
        d: today(),
        ref: 'INIT-' + cd,
        type: 'IN',
        src: 'Initial Stock',
        item: nid,
        qty: stock
      });
    }

    $('dItem').close();
    $('fItem').reset();
    render();
    alert('Item "' + nm + '" (' + cd + ') was saved successfully!');
    openCategory(cat);
  } catch (err) {
    alert('Failed to save item: ' + err.message);
  }
};

$('lItem').onchange = renderLedger;

// Search autocomplete (optional)
let qSel = 0, qList = [];
function qRender() {
  if (!$('q')) return;
  const v = $('q').value.trim().toLowerCase();
  if (!v) { if ($('qRes')) $('qRes').hidden = true; return; }
  qList = S.items.filter(i => (i.code + ' ' + i.name + ' ' + i.cat).toLowerCase().includes(v)).slice(0, 8);
  qSel = 0;
  if ($('qRes')) {
    $('qRes').innerHTML = qList.length ? qList.map((i, k) => `
      <button class="qr${k ? '' : ' on'}" data-q="${i.id}">
        <span class="code">${esc(i.code)}</span>
        <span style="flex:1"><b>${esc(i.name)}</b><small style="display:block;color:var(--mut)">${esc(i.cat)}</small></span>
        <b>${i.stock} ${esc(i.unit)}</b>
        ${i.stock <= i.min ? '<span class="pill" style="background:var(--red);margin-left:8px">LOW</span>' : ''}
      </button>
    `).join('') : '<div class="empty">No goods found for "' + esc(v) + '"</div>';
    $('qRes').hidden = false;
  }
}

function qOpen(id) {
  const it = item(id);
  if (!it) return;
  window.location.href = 'item.php?id=' + it.id;
}

if ($('q')) {
  $('q').oninput = qRender;
  $('q').onfocus = qRender;
  $('q').onkeydown = e => {
    if (!$('qRes')) return;
    const b = [...$('qRes').querySelectorAll('.qr')];
    if (e.key == 'Escape') { $('q').value = ''; $('qRes').hidden = true; }
    else if (e.key == 'Enter' && qList[qSel]) { qOpen(qList[qSel].id); }
    else if ((e.key == 'ArrowDown' || e.key == 'ArrowUp') && b.length) {
      e.preventDefault();
      qSel = (qSel + (e.key == 'ArrowDown' ? 1 : -1) + b.length) % b.length;
      b.forEach((x, k) => x.classList.toggle('on', k == qSel));
    }
  };
}
if ($('qRes')) {
  $('qRes').onclick = e => {
    const b = e.target.closest('[data-q]');
    if (b) qOpen(+b.dataset.q);
  };
}
document.addEventListener('click', e => {
  if (!e.target.closest('#q,#qRes') && $('qRes')) $('qRes').hidden = true;
});

// Category items table row click -> open item detail
$('cdRows').onclick = e => {
  const r = e.target.closest('[data-i]');
  if (!r) return;
  curItem = +r.dataset.i;
  go('cats');
};
$('bBackItem').onclick = () => { curItem = null; go('cats'); };

function txForm() {
  const t = $('xType').value == 'IN';
  $('xSrcL').hidden = !t;
  $('xSecL').hidden = t;
  $('xSec').required = !t;
}
$('xType').onchange = txForm;

function openTx(t) {
  editTx = t ? t.id : null;
  $('txTitle').textContent = t ? 'Update entry' : 'New entry';
  $('xType').value = t ? t.type : 'IN';
  $('xDate').value = t ? t.d : today();
  $('xRef').value = t ? t.ref : '';
  $('xQty').value = t ? t.qty : '';
  $('xSrc').value = t && t.type == 'IN' ? t.src : 'Colombo Port';
  $('xSec').value = t && t.type == 'OUT' ? t.src : '';
  txForm();
  $('dTx').showModal();
}
$('bTxIn').onclick = () => { openTx(null); $('xType').value = 'IN'; txForm(); };
$('bTxOut').onclick = () => { openTx(null); $('xType').value = 'OUT'; txForm(); };

$('fTx').onsubmit = e => {
  if (e.submitter.id != 'xOk') return;
  const it = item(curItem), type = $('xType').value, q = +$('xQty').value;
  const nt = { id: editTx, d: $('xDate').value, ref: $('xRef').value, type, src: type == 'IN' ? $('xSrc').value : $('xSec').value, item: it.id, qty: q };
  const old = editTx ? S.tx.find(t => t.id == editTx) : null;
  const ns = it.stock - (old ? eff(old) : 0) + eff(nt);
  if (ns < 0) {
    e.preventDefault();
    alert('This would make the balance negative (' + ns + '). Check the quantity.');
    return;
  }
  it.stock = ns;
  if (old) Object.assign(old, nt);
  else { nt.id = Math.max(0, ...S.tx.map(t => t.id || 0)) + 1; S.tx.push(nt); }
  render();
};

$('idRows').onclick = e => {
  const b = e.target.closest('button');
  if (!b) return;
  if (b.dataset.e) { openTx(S.tx.find(t => t.id == b.dataset.e)); return; }
  if (b.dataset.x) {
    const t = S.tx.find(t => t.id == b.dataset.x), it = item(t.item), ns = it.stock - eff(t);
    if (ns < 0) { alert('Cannot delete: balance would become negative (' + ns + ').'); return; }
    if (!confirm('Delete entry ' + t.ref + '?')) return;
    it.stock = ns;
    S.tx = S.tx.filter(x => x.id != t.id);
    render();
  }
};

$('bEdit').onclick = () => {
  const it = item(curItem);
  $('eCode').value = it.code;
  $('eRack').value = it.rack || '';
  $('eRate').value = it.rate || 0;
  $('eName').value = it.name;
  $('eUnit').value = it.unit;
  $('eMin').value = it.min;
  $('eCat').innerHTML = S.cats.map(c => `<option>${esc(c.name)}</option>`).join('');
  $('eCat').value = it.cat;
  $('dEdit').showModal();
};

$('fEdit').onsubmit = e => {
  if (e.submitter.id != 'eOk') return;
  const it = item(curItem);
  const cd = $('eCode').value.trim().toUpperCase();
  if (S.items.some(i => i.code == cd && i.id != it.id)) {
    e.preventDefault();
    alert('Item ID ' + cd + ' already exists. Use a different ID.');
    return;
  }
  it.code = cd;
  it.rack = $('eRack').value.trim();
  it.rate = +$('eRate').value || 0;
  it.name = $('eName').value;
  it.unit = $('eUnit').value;
  it.min = +$('eMin').value;
  it.cat = $('eCat').value;
  curCat = it.cat;
  render();
};

$('bDel').onclick = () => {
  const it = item(curItem);
  if (!confirm('Delete "' + it.name + '" and all its entries?')) return;
  S.tx = S.tx.filter(t => t.item != it.id);
  S.orders = S.orders.filter(o => o.item != it.id);
  S.items = S.items.filter(i => i.id != it.id);
  curItem = null;
  render();
  openCategory(curCat);
};

$('bCol').onclick = () => {
  const m = document.querySelector('.app').classList.toggle('mini');
  try { localStorage.setItem(KEY + '.mini', m ? '1' : ''); } catch (e) {}
};
try { if (localStorage.getItem(KEY + '.mini')) document.querySelector('.app').classList.add('mini'); } catch (e) {}

// Initial load
render();
go('dash');
</script>
</body>
</html>

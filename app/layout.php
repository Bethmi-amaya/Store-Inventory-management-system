<?php
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login page if not authenticated
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

if (!function_exists('top')) {
function top($title, $active = '', $extraHeader = '') {
    $userName = $_SESSION['user_name'] ?? 'Admin';
    $userRole = $_SESSION['role'] ?? 'user';
    $catBadge = 0;
    $ordBadge = 0;
    $pendingUsers = 0;
    $isAdmin = ($userRole === 'admin');
    $isStockKeeper = ($userRole === 'stock_keeper');

    try {
        $catBadge = (int)q("SELECT COUNT(*) FROM categories")->fetchColumn();
        $ordBadge = (int)q("SELECT COUNT(*) FROM orders WHERE type='red' AND status='To buy'")->fetchColumn();
        if ($isAdmin) {
            $pendingUsers = (int)q("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
        }
    } catch (Throwable $e) {}

    $navMain = [
        'dashboard.php' => ['label' => 'Dashboard', 'icon' => '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="8" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="15" width="7" height="6" rx="1"/></svg>'],
        'categories.php' => ['label' => 'Categories', 'badge' => $catBadge, 'badge_color' => '#B45309', 'icon' => '<svg viewBox="0 0 24 24"><path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.2"/></svg>']
    ];

    if ($isAdmin) {
        $navMain['users.php'] = [
            'label' => 'User Approvals',
            'badge' => $pendingUsers,
            'badge_color' => '#B45309',
            'icon' => '<svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'
        ];
    }

    $navStock = [
        'receive_stock.php' => ['label' => 'Receive Stock', 'icon' => '<svg viewBox="0 0 24 24"><path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M4 20h16"/></svg>'],
        'ledger.php' => ['label' => 'Stock Ledger', 'icon' => '<svg viewBox="0 0 24 24"><path d="M6 3h12a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>'],
        'orders.php' => ['label' => 'Orders', 'badge' => $ordBadge, 'badge_color' => '#B42318', 'icon' => '<svg viewBox="0 0 24 24"><path d="M6 6h15l-1.5 9h-12z"/><path d="M6 6L5 3H2"/><circle cx="9" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/></svg>'],
        'issue_notes.php' => ['label' => 'Issue Notes', 'icon' => '<svg viewBox="0 0 24 24"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/></svg>'],
        'reports.php' => ['label' => 'Reports', 'icon' => '<svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>']
    ];
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> - Stock Book</title>
  <link rel="stylesheet" href="app.css">
</head>
<body>
<div class="app">
  <nav aria-label="Main Navigation">
    <div class="brand">
      <div class="logo" style="background:#fff;padding:2px;overflow:hidden;border:1px solid rgba(255,255,255,0.2)">
        <img src="logo.png" alt="SLPA" style="width:100%;height:100%;object-fit:contain;border-radius:8px">
      </div>
      <div>
        <b style="font-size:16px">Ports Authority</b>
        <small style="font-size:11px;color:#A0AEC0">Stationery & Store</small>
      </div>
    </div>

    <div class="sec">MAIN</div>
    <?php foreach ($navMain as $url => $item): ?>
      <a href="<?= $url ?>" class="<?= $url === $active ? 'on' : '' ?>">
        <?= $item['icon'] ?>
        <span class="lbl"><?= $item['label'] ?></span>
        <?php if (!empty($item['badge'])): ?>
          <span class="badge" style="background:<?= $item['badge_color'] ?? '#0F766E' ?>"><?= $item['badge'] ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>

    <div class="sec">STOCK</div>
    <?php foreach ($navStock as $url => $item): ?>
      <a href="<?= $url ?>" class="<?= $url === $active ? 'on' : '' ?>">
        <?= $item['icon'] ?>
        <span class="lbl"><?= $item['label'] ?></span>
        <?php if (!empty($item['badge'])): ?>
          <span class="badge" style="background:<?= $item['badge_color'] ?? '#0F766E' ?>"><?= $item['badge'] ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>

    <div class="user">
      <div class="av"><?= strtoupper(substr($userName, 0, 2)) ?></div>
      <div style="flex:1;min-width:0;overflow:hidden">
        <b style="font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block"><?= e($userName) ?></b>
        <span class="pill" style="font-size:10px;padding:2px 7px;background:<?= $isAdmin ? '#0F766E' : ($isStockKeeper ? '#B45309' : '#4B5563') ?>">
          <?= strtoupper(str_replace('_', ' ', $userRole)) ?>
        </span>
      </div>
      <a class="logout-btn" href="logout.php" title="Sign out">Logout</a>
    </div>
  </nav>

  <main>
    <div class="top">
      <h1><?= e($title) ?></h1>
      <?= $extraHeader ?>
    </div>
<?php
}
}

if (!function_exists('bottom')) {
function bottom() {
    echo "\n  </main>\n</div>\n</body>\n</html>";
}
}

<?php
require_once __DIR__ . '/config.php';

function db() {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    return $pdo;
}

// Global PDO instance
try {
    $pdo = db();
} catch (Throwable $e) {
    $pdo = null;
}

function q($sql, $params = []) {
    global $pdo;
    if ($pdo === null) {
        $pdo = db();
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function go($url) {
    header('Location: ' . $url);
    exit;
}

function e($value) {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function fail($error) {
    if ($error instanceof Exception) {
        return $error->getMessage();
    }
    return 'An unexpected error occurred.';
}

function n($value) {
    return number_format((float) $value, 2);
}

function qty($value) {
    $num = (float) $value;
    if (floor($num) == $num) {
        return number_format($num, 0);
    }
    return number_format($num, 2);
}

function st($balance, $reorder) {
    $balance = (float) $balance;
    $reorder = (float) $reorder;

    if ($balance <= 0) {
        return '<span class="tag out">Out of stock</span>';
    }
    if ($balance <= $reorder) {
        return '<span class="tag low">Low</span>';
    }
    return '<span class="tag ok">OK</span>';
}

function bal($itemId) {
    $row = q("SELECT COALESCE(SUM(CASE WHEN entry_type = 'IN' THEN quantity ELSE -quantity END), 0) AS bal FROM stock_entries WHERE item_id = ?", [(int) $itemId])->fetch();
    return (float) ($row['bal'] ?? 0);
}

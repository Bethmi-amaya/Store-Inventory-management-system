<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/helpers.php';

// Check auth
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$a = $_POST['a'] ?? $_GET['a'] ?? '';

try {
    global $pdo;
    if (!$pdo) $pdo = db();

    if ($a === 'add') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $catName = trim($_POST['cat'] ?? '');
        $catId = (int)($_POST['cat_id'] ?? 0);
        $unit = trim($_POST['unit'] ?? 'pcs') ?: 'pcs';
        $stock = (float)($_POST['stock'] ?? 0);
        $min = (float)($_POST['min'] ?? 10);
        $rack = trim($_POST['rack'] ?? '');
        $rate = (float)($_POST['rate'] ?? 0);

        if ($code === '' || $name === '') {
            throw new Exception('Product ID and Product Name are required.');
        }

        if ($catId <= 0 && $catName !== '') {
            $cRow = q("SELECT id FROM categories WHERE name = ? OR code = ? LIMIT 1", [$catName, $catName])->fetch();
            if ($cRow) $catId = (int)$cRow['id'];
        }

        if ($catId <= 0) {
            // Default to first category if none matched
            $cFirst = q("SELECT id FROM categories ORDER BY id ASC LIMIT 1")->fetch();
            if ($cFirst) $catId = (int)$cFirst['id'];
            else throw new Exception('Please select a valid Category.');
        }

        $dup = q("SELECT id FROM items WHERE code = ?", [$code])->fetch();
        if ($dup) {
            throw new Exception("Product ID '$code' already exists.");
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO items (code, name, category_id, unit, stock, reorder_level, rack_no, rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$code, $name, $catId, $unit, $stock, $min, $rack, $rate]);
        $itemId = (int)$pdo->lastInsertId();

        if ($stock > 0) {
            $stmtIn = $pdo->prepare("INSERT INTO stock_entries (item_id, entry_date, reference, entry_type, source, quantity) VALUES (?, CURDATE(), ?, 'IN', 'Initial Stock', ?)");
            $stmtIn->execute([$itemId, 'INIT-' . $code, $stock]);
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'item' => [
                'id' => $itemId,
                'code' => $code,
                'name' => $name,
                'cat' => $catName,
                'cat_id' => $catId,
                'unit' => $unit,
                'stock' => $stock,
                'min' => $min
            ]
        ]);
        exit;
    } elseif ($a === 'receive') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $qty = (float)($_POST['qty'] ?? 0);
        $src = trim($_POST['src'] ?? 'Main Store') ?: 'Main Store';
        $ref = trim($_POST['ref'] ?? '') ?: ('GRN-' . date('md') . '-' . rand(100, 999));
        $date = !empty($_POST['date']) ? $_POST['date'] : date('Y-m-d');

        if ($itemId <= 0 || $qty <= 0) {
            throw new Exception('Invalid item or quantity.');
        }

        $pdo->beginTransaction();

        $stmtIn = $pdo->prepare("INSERT INTO stock_entries (item_id, entry_date, reference, entry_type, source, quantity) VALUES (?, ?, ?, 'IN', ?, ?)");
        $stmtIn->execute([$itemId, $date, $ref, $src, $qty]);

        q("UPDATE items SET stock = stock + ? WHERE id = ?", [$qty, $itemId]);

        $pdo->commit();

        echo json_encode(['success' => true]);
        exit;
    }

    throw new Exception('Unknown action.');
} catch (Exception $e) {
    if (isset($pdo) && $pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

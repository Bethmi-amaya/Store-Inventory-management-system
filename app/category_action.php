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

    if ($a === 'del') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');

        if ($id <= 0 && $name !== '') {
            $catRow = q("SELECT id FROM categories WHERE name = ? LIMIT 1", [$name])->fetch();
            if ($catRow) $id = (int)$catRow['id'];
        }

        if ($id <= 0) {
            throw new Exception('Invalid category ID.');
        }

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

        $_SESSION['flash_msg'] = 'Category and all associated items deleted successfully.';
        echo json_encode(['success' => true]);
        exit;
    } elseif ($a === 'save' || $a === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['code'] ?? ''));
        $desc = trim($_POST['description'] ?? '');
        $originalName = trim($_POST['original_name'] ?? '');

        if ($id <= 0 && $originalName !== '') {
            $catRow = q("SELECT id FROM categories WHERE name = ? LIMIT 1", [$originalName])->fetch();
            if ($catRow) $id = (int)$catRow['id'];
        }

        if ($name === '' || $code === '') {
            throw new Exception('Category Name and Category ID are required.');
        }

        // Check if name is taken by another category
        $check = q("SELECT id FROM categories WHERE (name = ? OR code = ?) AND id != ?", [$name, $code, $id])->fetch();
        if ($check) {
            throw new Exception("A category with the name '$name' or code '$code' already exists.");
        }

        if ($id > 0) {
            q("UPDATE categories SET name = ?, code = ?, description = ? WHERE id = ?", [$name, $code, $desc, $id]);
            $_SESSION['flash_msg'] = "Category '$name' updated successfully.";
        } else {
            q("INSERT INTO categories (name, code, description) VALUES (?, ?, ?)", [$name, $code, $desc]);
            $id = (int)$pdo->lastInsertId();
            $_SESSION['flash_msg'] = "Category '$name' created successfully.";
        }

        echo json_encode(['success' => true, 'id' => $id, 'name' => $name, 'code' => $code]);
        exit;
    } else {
        throw new Exception('Unknown action.');
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$inputUser = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');
$remember = !empty($_POST['remember']);
$loginType = trim($_POST['login_type'] ?? 'user');

if ($inputUser === '' || $password === '') {
    header('Location: index.php?error=invalid');
    exit;
}

$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($db->connect_error) {
    $_SESSION['login_error'] = 'Database connection failed.';
    header('Location: index.php?error=invalid');
    exit;
}

// Find user by either username or email
$stmt = $db->prepare('SELECT id, full_name, username, email, section, role, status, password_hash FROM users WHERE username = ? OR email = ? LIMIT 1');
$stmt->bind_param('ss', $inputUser, $inputUser);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !password_verify($password, $user['password_hash'])) {
    $stmt->close();
    $db->close();
    header('Location: index.php?error=invalid');
    exit;
}

// Check account approval status
if ($user['status'] === 'pending') {
    $stmt->close();
    $db->close();
    header('Location: index.php?error=pending');
    exit;
}

if ($user['status'] === 'rejected') {
    $stmt->close();
    $db->close();
    header('Location: index.php?error=rejected');
    exit;
}

// If specific login role was selected, verify role compatibility
if ($loginType === 'admin' && $user['role'] !== 'admin') {
    $stmt->close();
    $db->close();
    header('Location: index.php?error=not_admin');
    exit;
}
if ($loginType === 'stock_keeper' && !in_array($user['role'], ['stock_keeper', 'admin'], true)) {
    $stmt->close();
    $db->close();
    header('Location: index.php?error=not_stockkeeper');
    exit;
}

// User is approved: log in!
$_SESSION['logged_in'] = true;
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['user_name'] = $user['full_name'];
$_SESSION['username'] = $user['username'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = $user['role'];
$_SESSION['section'] = $user['section'] ?? 'General';

$stmt->close();
$db->close();
header('Location: dashboard.php');
exit;

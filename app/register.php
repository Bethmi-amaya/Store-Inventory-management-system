<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/helpers.php';

// If already logged in, redirect directly to dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$err = '';
$fullName = '';
$username = '';
$email = '';
$section = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = strtolower(trim($_POST['username'] ?? ''));
    $email = strtolower(trim($_POST['email'] ?? ''));
    $section = trim($_POST['section'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    try {
        if ($fullName === '' || $username === '' || $email === '' || $password === '') {
            throw new Exception('Please fill in all required fields.');
        }

        // Validate username format (letters, numbers, underscore, 3-30 chars)
        if (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
            throw new Exception('Username must be 3-30 characters long and can only contain letters, numbers, and underscores.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address.');
        }

        if (strlen($password) < 6) {
            throw new Exception('Password must be at least 6 characters long.');
        }

        if ($password !== $confirmPassword) {
            throw new Exception('Passwords do not match. Please verify your password.');
        }

        // Check if username already exists
        $userCheck = q("SELECT id FROM users WHERE username = ? LIMIT 1", [$username])->fetch();
        if ($userCheck) {
            throw new Exception("The username '$username' is already taken. Please choose another username.");
        }

        // Check if email already exists
        $emailCheck = q("SELECT id FROM users WHERE email = ? LIMIT 1", [$email])->fetch();
        if ($emailCheck) {
            throw new Exception("An account with the email '$email' already exists. Please log in or use another email.");
        }

        $pwHash = password_hash($password, PASSWORD_DEFAULT);

        // Insert new user with status = 'pending' and role = 'user'
        q("INSERT INTO users (full_name, username, email, section, role, status, password_hash) VALUES (?, ?, ?, ?, 'user', 'pending', ?)",
            [$fullName, $username, $email, $section ?: 'General', $pwHash]);

        // Redirect to login page with registered flag
        header('Location: index.php?registered=1');
        exit;
    } catch (Exception $e) {
        $err = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Ports Authority | Account Registration</title>
    <link rel="stylesheet" href="styles.css" />
    <style>
      .reg-card {
        max-width: 540px;
        width: 100%;
        background: rgba(255, 255, 255, 0.94);
        border: 1px solid rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(12px);
        border-radius: 24px;
        box-shadow: 0 20px 50px rgba(15, 118, 110, 0.15);
        padding: 34px 36px;
      }
      .alert-error {
        background: #FEE2E2;
        color: #991B1B;
        border: 1px solid #FCA5A5;
        padding: 12px 16px;
        border-radius: 10px;
        font-size: 14px;
        margin-bottom: 18px;
      }
      .info-notice {
        background: #EFF6FF;
        color: #1E40AF;
        border-left: 4px solid #3B82F6;
        padding: 12px 14px;
        border-radius: 6px;
        font-size: 13px;
        line-height: 1.45;
        margin-bottom: 20px;
      }
      .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
      }
      .input-group select {
        width: 100%;
        border: 1px solid var(--line);
        background: rgba(255, 255, 255, 0.95);
        border-radius: 14px;
        padding: 14px 16px;
        font: inherit;
        color: var(--ink);
      }
      .input-group select:focus {
        outline: none;
        border-color: #0F766E;
      }
      .password-wrap {
        position: relative;
        display: block;
      }
      .password-wrap input {
        width: 100%;
        padding-right: 42px;
      }
      .toggle-password {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        border: none;
        background: transparent;
        cursor: pointer;
        color: #52606D;
        font-size: 15px;
        width: 28px;
        height: 28px;
        border-radius: 6px;
      }
      .toggle-password:hover {
        background: rgba(15, 118, 110, 0.08);
        color: #0F766E;
      }
      @media (max-width: 580px) {
        .form-grid {
          grid-template-columns: 1fr;
        }
      }
    </style>
  </head>
  <body>
    <div class="page-shell" style="max-width:540px">
      <div class="reg-card">
        <div style="text-align:center;margin-bottom:20px">
          <div style="font-size:15px;font-weight:800;letter-spacing:0.18em;text-transform:uppercase;color:#0F766E;margin-bottom:10px">
            Ports Authority
          </div>
          <div style="display:flex;justify-content:center;margin-bottom:10px">
            <div style="width:80px;height:80px;border-radius:50%;background:#ffffff;box-shadow:0 6px 16px rgba(15,118,110,0.18);padding:5px;display:grid;place-items:center;border:2px solid #DDE1E6">
              <img src="logo.png" alt="Ports Authority" style="width:100%;height:100%;object-fit:contain;border-radius:50%">
            </div>
          </div>
          <h2 style="font-size:22px;font-weight:700;margin:0 0 4px 0;color:#17202A">Create New Account</h2>
          <p style="margin:0;font-size:13.5px;color:#52606D">Register for Store Inventory System</p>
        </div>

        <div class="info-notice">
          <strong>Approval Required:</strong> After registration, your account will remain <b>pending</b> until an Admin reviews and approves it. You can log in once approved.
        </div>

        <?php if ($err): ?>
          <div class="alert-error">
            <strong>Error:</strong> <?= htmlspecialchars($err) ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
          <label class="input-group" style="margin-bottom:14px">
            <span>Full Name *</span>
            <input
              type="text"
              name="full_name"
              placeholder="e.g. Sunil Perera"
              value="<?= htmlspecialchars($fullName) ?>"
              required
            />
          </label>

          <div class="form-grid" style="margin-bottom:14px">
            <label class="input-group">
              <span>Username *</span>
              <input
                type="text"
                name="username"
                placeholder="e.g. sunil_p"
                value="<?= htmlspecialchars($username) ?>"
                autocomplete="username"
                required
              />
            </label>

            <label class="input-group">
              <span>Section / Department</span>
              <select name="section">
                <option value="Engineering" <?= $section === 'Engineering' ? 'selected' : '' ?>>Engineering</option>
                <option value="Finance" <?= $section === 'Finance' ? 'selected' : '' ?>>Finance</option>
                <option value="Operations" <?= $section === 'Operations' ? 'selected' : '' ?>>Operations</option>
                <option value="Admin" <?= $section === 'Admin' ? 'selected' : '' ?>>Admin</option>
              </select>
            </label>
          </div>

          <label class="input-group" style="margin-bottom:14px">
            <span>Email Address *</span>
            <input
              type="email"
              name="email"
              placeholder="e.g. sunil@slpa.lk"
              value="<?= htmlspecialchars($email) ?>"
              autocomplete="email"
              required
            />
          </label>

          <div class="form-grid" style="margin-bottom:20px">
            <label class="input-group">
              <span>Password *</span>
              <div class="password-wrap">
                <input
                  type="password"
                  name="password"
                  id="password"
                  placeholder="Minimum 6 characters"
                  autocomplete="new-password"
                  required
                />
                <button type="button" class="toggle-password" data-target="password" aria-label="Show password">👁</button>
              </div>
            </label>

            <label class="input-group">
              <span>Confirm Password *</span>
              <div class="password-wrap">
                <input
                  type="password"
                  name="confirm_password"
                  id="confirm_password"
                  placeholder="Re-enter password"
                  autocomplete="new-password"
                  required
                />
                <button type="button" class="toggle-password" data-target="confirm_password" aria-label="Show password">👁</button>
              </div>
            </label>
          </div>

          <button type="submit" class="submit-btn" style="background:#0F766E;width:100%">
            Submit Registration &rarr;
          </button>
        </form>

        <div style="text-align:center;margin-top:20px;padding-top:16px;border-top:1px solid #E8EBEF;font-size:14px;color:var(--muted)">
          Already have an account? <a href="index.php" style="color:#0F766E;font-weight:700;text-decoration:none">Back to Login &rarr;</a>
        </div>
      </div>
    </div>

    <script>
      document.querySelectorAll('.toggle-password').forEach((button) => {
        button.addEventListener('click', () => {
          const targetId = button.dataset.target;
          const input = document.getElementById(targetId);
          if (!input) return;

          const isHidden = input.type === 'password';
          input.type = isHidden ? 'text' : 'password';
          button.textContent = isHidden ? '🙈' : '👁';
          button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
      });
    </script>
  </body>
</html>


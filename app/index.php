<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to dashboard if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$errType = $_GET['error'] ?? '';
$isRegistered = isset($_GET['registered']) && $_GET['registered'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login - Sri Lanka Ports Authority | Stock Book</title>
  <style>
    :root {
      --primary: #1C6B7D;
      --primary-dark: #14505E;
      --primary-hover: #0F404B;
      --teal-bg: #1B5D6E;
      --ink: #1E293B;
      --muted: #64748B;
      --line: #E2E8F0;
      --card-bg: #FFFFFF;
      --shadow-lg: 0 24px 48px -12px rgba(15, 23, 42, 0.25);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      min-height: 100vh;
      display: flex;
      color: var(--ink);
      background: #174E5D;
      overflow-x: hidden;
    }

    .login-container {
      display: flex;
      width: 100vw;
      min-height: 100vh;
    }

    /* LEFT SIDE: SCENIC INSTITUTIONAL PORTAL */
    .scenic-side {
      flex: 1.15;
      position: relative;
      background: linear-gradient(180deg, #64B5F6 0%, #90CAF9 35%, #81C784 75%, #4CAF50 100%);
      background-image: 
        radial-gradient(circle at 80% 20%, rgba(255,255,255,0.4) 0%, transparent 40%),
        linear-gradient(to bottom, rgba(20,80,95,0.15), rgba(20,80,95,0.45)),
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 800'%3E%3Cdefs%3E%3ClinearGradient id='sky' x1='0' y1='0' x2='0' y2='1'%3E%3Cstop offset='0%25' stop-color='%234AA3DF'/%3E%3Cstop offset='50%25' stop-color='%2372C1ED'/%3E%3Cstop offset='100%25' stop-color='%23BDE1F7'/%3E%3C/linearGradient%3E%3ClinearGradient id='sea' x1='0' y1='0' x2='0' y2='1'%3E%3Cstop offset='0%25' stop-color='%231976D2'/%3E%3Cstop offset='100%25' stop-color='%230D47A1'/%3E%3C/linearGradient%3E%3ClinearGradient id='bldg' x1='0' y1='0' x2='0' y2='1'%3E%3Cstop offset='0%25' stop-color='%23F8FAFC'/%3E%3Cstop offset='100%25' stop-color='%23CBD5E1'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='1200' height='550' fill='url(%23sky)'/%3E%3Ccircle cx='1000' cy='120' r='60' fill='%23FFF' opacity='0.7'/%3E%3Cpath d='M0 450 Q300 420 600 450 T1200 440 L1200 550 L0 550 Z' fill='%2381C784' opacity='0.7'/%3E%3C!-- Institution Building --%3E%3Crect x='380' y='320' width='440' height='230' rx='8' fill='url(%23bldg)' stroke='%2394A3B8' stroke-width='2'/%3E%3Cpath d='M360 320 L600 230 L840 320 Z' fill='%23C0392B'/%3E%3Crect x='410' y='360' width='40' height='60' rx='4' fill='%230284C7'/%3E%3Crect x='480' y='360' width='40' height='60' rx='4' fill='%230284C7'/%3E%3Crect x='580' y='360' width='40' height='60' rx='4' fill='%230284C7'/%3E%3Crect x='650' y='360' width='40' height='60' rx='4' fill='%230284C7'/%3E%3Crect x='720' y='360' width='40' height='60' rx='4' fill='%230284C7'/%3E%3Crect x='550' y='460' width='100' height='90' fill='%23334155' rx='4'/%3E%3C!-- Entrance Gate Wall --%3E%3Crect x='100' y='440' width='450' height='170' rx='6' fill='%23FFFFFF' stroke='%23CBD5E1' stroke-width='2'/%3E%3C!-- Palm Trees --%3E%3Cpath d='M220 520 Q240 350 250 250' stroke='%23795548' stroke-width='14' fill='none' stroke-linecap='round'/%3E%3Cpath d='M250 250 Q180 220 120 270 M250 250 Q220 180 170 170 M250 250 Q280 160 330 180 M250 250 Q330 220 370 270 M250 250 Q250 150 270 110' stroke='%232E7D32' stroke-width='8' fill='none' stroke-linecap='round'/%3E%3Crect x='0' y='550' width='1200' height='250' fill='%232E7D32'/%3E%3C/svg%3E");
      background-size: cover;
      background-position: center;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 40px 48px;
    }

    /* Institution Top Header (Emblem + Tri-lingual title) */
    .inst-header {
      display: flex;
      align-items: center;
      gap: 18px;
      background: rgba(255, 255, 255, 0.88);
      backdrop-filter: blur(10px);
      padding: 14px 20px;
      border-radius: 16px;
      border: 1px solid rgba(255, 255, 255, 0.9);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
      max-width: 580px;
    }

    .inst-crest {
      width: 64px;
      height: 64px;
      flex-shrink: 0;
      background: #FFFFFF;
      border-radius: 12px;
      padding: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      border: 1px solid #E2E8F0;
    }

    .inst-crest img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    .inst-text h3 {
      font-size: 17px;
      font-weight: 700;
      color: #0F172A;
      line-height: 1.25;
      font-family: system-ui, sans-serif;
    }

    .inst-text .ta {
      font-size: 14.5px;
      color: #1E293B;
      font-weight: 600;
      line-height: 1.25;
    }

    .inst-text .en {
      font-size: 16px;
      color: var(--primary-dark);
      font-weight: 800;
      letter-spacing: 0.02em;
      text-transform: uppercase;
      margin-top: 2px;
    }

    .inst-text .motto {
      font-size: 12px;
      font-style: italic;
      color: #64748B;
      margin-top: 4px;
    }

    /* Bottom Feature Badges Bar (from photo) */
    .feature-strip {
      display: flex;
      align-items: center;
      justify-content: space-around;
      background: rgba(17, 36, 49, 0.88);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.15);
      border-radius: 999px;
      padding: 12px 24px;
      color: #FFFFFF;
      box-shadow: 0 14px 34px rgba(0, 0, 0, 0.25);
      max-width: 680px;
    }

    .feature-item {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 12.5px;
      font-weight: 600;
      letter-spacing: 0.01em;
      color: #E2E8F0;
    }

    .feature-item svg {
      width: 18px;
      height: 18px;
      fill: none;
      stroke: #38BDF8;
      stroke-width: 2;
      stroke-linecap: round;
      stroke-linejoin: round;
    }

    /* RIGHT SIDE: CLEAN TEAL MODERN LOGIN CARD */
    .card-side {
      flex: 0.95;
      background: linear-gradient(135deg, #1C6B7D 0%, #175463 60%, #103E49 100%);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 36px 28px;
      position: relative;
    }

    .login-card {
      width: 100%;
      max-width: 440px;
      background: var(--card-bg);
      border-radius: 24px;
      padding: 36px 34px;
      box-shadow: var(--shadow-lg), 0 0 0 1px rgba(255, 255, 255, 0.2);
      display: flex;
      flex-direction: column;
      position: relative;
      z-index: 2;
    }

    /* Card Top Avatar Icon */
    .card-icon {
      width: 64px;
      height: 64px;
      background: #155564;
      border-radius: 50%;
      margin: 0 auto 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #FFFFFF;
      box-shadow: 0 8px 16px rgba(21, 85, 100, 0.28);
    }

    .card-icon svg {
      width: 32px;
      height: 32px;
      fill: none;
      stroke: currentColor;
      stroke-width: 2;
      stroke-linecap: round;
      stroke-linejoin: round;
    }

    .card-header {
      text-align: center;
      margin-bottom: 22px;
    }

    .card-header h2 {
      font-size: 23px;
      font-weight: 700;
      color: #0F172A;
      letter-spacing: -0.01em;
    }

    .card-header p {
      font-size: 13px;
      color: var(--muted);
      margin-top: 4px;
    }

    /* Alerts */
    .alert {
      padding: 10px 14px;
      border-radius: 10px;
      font-size: 13px;
      line-height: 1.4;
      margin-bottom: 16px;
    }
    .alert-error {
      background: #FEF2F2;
      color: #991B1B;
      border: 1px solid #FCA5A5;
    }
    .alert-warning {
      background: #FFFBEB;
      color: #92400E;
      border: 1px solid #FDE68A;
    }
    .alert-success {
      background: #F0FDF4;
      color: #166534;
      border: 1px solid #86EFAC;
    }

    /* User Type Segmented Control */
    .role-selection {
      margin-bottom: 18px;
    }

    .role-label {
      font-size: 12.5px;
      font-weight: 700;
      color: #475569;
      display: flex;
      align-items: center;
      gap: 6px;
      margin-bottom: 8px;
    }

    .role-tabs {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      background: #F1F5F9;
      padding: 4px;
      border-radius: 12px;
      gap: 4px;
      border: 1px solid #E2E8F0;
    }

    .role-tab {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      padding: 9px 4px;
      font-size: 13px;
      font-weight: 600;
      color: #64748B;
      background: transparent;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.15s ease;
    }

    .role-tab.active {
      background: #155564;
      color: #FFFFFF;
      box-shadow: 0 4px 10px rgba(21, 85, 100, 0.25);
    }

    /* Input Fields with Icons */
    .form-group {
      margin-bottom: 15px;
    }

    .input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    .input-icon {
      position: absolute;
      left: 14px;
      width: 18px;
      height: 18px;
      color: #94A3B8;
      pointer-events: none;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .input-wrapper input {
      width: 100%;
      height: 46px;
      padding: 0 44px 0 42px;
      border: 1px solid #CBD5E1;
      border-radius: 10px;
      font-size: 14px;
      color: #0F172A;
      background: #FAFAFA;
      transition: all 0.15s ease;
    }

    .input-wrapper input:focus {
      outline: none;
      border-color: #1C6B7D;
      background: #FFFFFF;
      box-shadow: 0 0 0 3px rgba(28, 107, 125, 0.14);
    }

    .input-wrapper input::placeholder {
      color: #94A3B8;
    }

    .toggle-pass {
      position: absolute;
      right: 12px;
      background: none;
      border: none;
      color: #94A3B8;
      cursor: pointer;
      padding: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .toggle-pass:hover {
      color: #1C6B7D;
    }

    /* Meta Row: Remember & Forgot */
    .meta-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin: 12px 0 20px;
      font-size: 13px;
    }

    .remember-me {
      display: flex;
      align-items: center;
      gap: 7px;
      color: #475569;
      cursor: pointer;
    }

    .remember-me input {
      accent-color: #155564;
      width: 15px;
      height: 15px;
      cursor: pointer;
    }

    .forgot-link {
      color: #155564;
      font-weight: 600;
      text-decoration: none;
    }

    .forgot-link:hover {
      text-decoration: underline;
    }

    /* Main Submit Button (➔ Login) */
    .btn-login {
      width: 100%;
      height: 48px;
      background: #155564;
      color: #FFFFFF;
      border: none;
      border-radius: 10px;
      font-size: 15px;
      font-weight: 700;
      letter-spacing: 0.02em;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      box-shadow: 0 8px 18px rgba(21, 85, 100, 0.28);
      transition: all 0.15s ease;
    }

    .btn-login:hover {
      background: #0F404B;
      transform: translateY(-1px);
      box-shadow: 0 10px 22px rgba(21, 85, 100, 0.35);
    }

    .btn-login:active {
      transform: translateY(0);
    }

    /* Divider & Secondary Register Action */
    .register-section {
      margin-top: 22px;
      text-align: center;
      border-top: 1px solid #E2E8F0;
      padding-top: 18px;
    }

    .register-section small {
      font-size: 12.5px;
      color: #64748B;
      display: block;
      margin-bottom: 10px;
    }

    .btn-register {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      height: 42px;
      border: 1px solid #CBD5E1;
      border-radius: 10px;
      background: #FFFFFF;
      color: #334155;
      font-size: 13.5px;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.15s ease;
    }

    .btn-register:hover {
      background: #F8FAFC;
      border-color: #94A3B8;
      color: #0F172A;
    }

    /* Right Bottom Footer */
    .card-footer {
      margin-top: 24px;
      font-size: 12px;
      color: rgba(255, 255, 255, 0.7);
      text-align: center;
      letter-spacing: 0.02em;
    }

    @media (max-width: 960px) {
      .login-container {
        flex-direction: column;
      }
      .scenic-side {
        flex: none;
        padding: 32px 24px;
        min-height: 280px;
      }
      .feature-strip {
        display: none;
      }
      .card-side {
        flex: 1;
        padding: 24px 16px 40px;
      }
    }
  </style>
</head>
<body>

<div class="login-container">
  
  <!-- LEFT SIDE: SCENIC INSTITUTIONAL PORTAL (From user photo) -->
  <div class="scenic-side">
    <div class="inst-header">
      <div class="inst-crest">
        <img src="logo.png" alt="Sri Lanka Ports Authority Emblem" onerror="this.style.display='none'">
      </div>
      <div class="inst-text">
        <h3>ශ්‍රී ලංකා වරාය අධිකාරිය</h3>
        <div class="ta">இலங்கை துறைமுக அதிகாரசபை</div>
        <div class="en">Sri Lanka Ports Authority</div>
        <div class="motto">"Efficient Administration for a Better Tomorrow"</div>
      </div>
    </div>

    <!-- Feature strip at bottom left (from user photo) -->
    <div class="feature-strip">
      <div class="feature-item">
        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <span>Secure Access</span>
      </div>
      <div class="feature-item">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        <span>Stock Management</span>
      </div>
      <div class="feature-item">
        <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
        <span>Organized Records</span>
      </div>
      <div class="feature-item">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span>For a Better Service</span>
      </div>
    </div>
  </div>

  <!-- RIGHT SIDE: MODERN LOGIN CARD (From user photo) -->
  <div class="card-side">
    <div class="login-card">
      
      <!-- Top circle avatar -->
      <div class="card-icon">
        <svg viewBox="0 0 24 24"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2z"/></svg>
      </div>

      <div class="card-header">
        <h2>Login to Stock Book System</h2>
        <p>Ports Authority Stationery & Store Management</p>
      </div>

      <!-- Notification Alerts -->
      <?php if ($isRegistered): ?>
        <div class="alert alert-success">
          <strong>Registration Submitted!</strong><br>
          Your account is created and pending <b>Admin approval</b>. You will be able to log in once reviewed.
        </div>
      <?php elseif ($errType === 'pending'): ?>
        <div class="alert alert-warning">
          <strong>Account Approval Pending</strong><br>
          Your registration is waiting for Administrator review. Only Admin-approved users can log in.
        </div>
      <?php elseif ($errType === 'rejected'): ?>
        <div class="alert alert-error">
          <strong>Registration Rejected</strong><br>
          Your account request was declined. Please contact your department administrator.
        </div>
      <?php elseif ($errType === 'not_admin'): ?>
        <div class="alert alert-error">
          <strong>Admin Privileges Required</strong><br>
          This account does not have Administrator rights.
        </div>
      <?php elseif ($errType === 'not_stockkeeper'): ?>
        <div class="alert alert-error">
          <strong>Stock Keeper Role Required</strong><br>
          This account is not designated as Stock Keeper.
        </div>
      <?php elseif ($errType === 'invalid'): ?>
        <div class="alert alert-error">
          <strong>Invalid Username or Password</strong><br>
          Please check your credentials and try again.
        </div>
      <?php endif; ?>

      <!-- Login Form -->
      <form id="fLogin" method="POST" action="login.php">
        <input type="hidden" name="login_type" id="loginType" value="user">

        <!-- User Type Tabs (User / Stock Keeper / Admin) -->
        <div class="role-selection">
          <div class="role-label">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span>User Type:</span>
          </div>
          <div class="role-tabs">
            <button type="button" class="role-tab active" data-type="user">
              👤 User
            </button>
            <button type="button" class="role-tab" data-type="stock_keeper">
              📦 Stock Keeper
            </button>
            <button type="button" class="role-tab" data-type="admin">
              🛡️ Admin
            </button>
          </div>
        </div>

        <!-- Username Input with User Icon -->
        <div class="form-group">
          <div class="input-wrapper">
            <div class="input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <input 
              type="text" 
              name="username" 
              id="username" 
              placeholder="Enter your username or email" 
              required 
              autocomplete="username"
            />
          </div>
        </div>

        <!-- Password Input with Lock Icon & Toggle -->
        <div class="form-group">
          <div class="input-wrapper">
            <div class="input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
            <input 
              type="password" 
              name="password" 
              id="password" 
              placeholder="Enter your password" 
              required 
              autocomplete="current-password"
            />
            <button type="button" class="toggle-pass" id="btnTogglePass" title="Show/Hide Password" aria-label="Toggle password visibility">
              <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <!-- Remember Me & Forgot Password -->
        <div class="meta-row">
          <label class="remember-me">
            <input type="checkbox" name="remember" value="1">
            <span>Remember me</span>
          </label>
          <a href="javascript:void(0)" onclick="alert('Please contact your System Administrator to reset your password.')" class="forgot-link">Forgot Password?</a>
        </div>

        <!-- Action Button (➔ Login) -->
        <button type="submit" class="btn-login" id="btnLogin">
          <span>&rarr;</span> Login
        </button>

      </form>

      <!-- Secondary Option: Register -->
      <div class="register-section">
        <small>Don't have an account?</small>
        <a href="register.php" class="btn-register">
          <span>👤+</span> Register New Account
        </a>
      </div>

    </div>

    <!-- Bottom Footer (From photo) -->
    <div class="card-footer">
      Southern Provincial Council &bull; Sri Lanka Ports Authority &bull; Archive Management System
    </div>
  </div>

</div>

<script>
  // Role selector buttons
  const tabs = document.querySelectorAll('.role-tab');
  const typeInput = document.getElementById('loginType');
  const userField = document.getElementById('username');

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      const role = tab.dataset.type;
      typeInput.value = role;

      if (role === 'admin' && !userField.value) {
        userField.placeholder = "Enter admin username (e.g. admin)";
      } else if (role === 'stock_keeper' && !userField.value) {
        userField.placeholder = "Enter stock keeper username (e.g. stockkeeper)";
      } else if (!userField.value) {
        userField.placeholder = "Enter your username or email";
      }
    });
  });

  // Show / Hide Password toggle
  const passField = document.getElementById('password');
  const btnToggle = document.getElementById('btnTogglePass');
  const eyeIcon = document.getElementById('eyeIcon');

  btnToggle.addEventListener('click', () => {
    const isPass = passField.type === 'password';
    passField.type = isPass ? 'text' : 'password';
    eyeIcon.innerHTML = isPass 
      ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
      : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
  });
</script>

</body>
</html>

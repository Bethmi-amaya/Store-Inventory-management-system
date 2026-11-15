<?php
require_once __DIR__ . '/config.php';

// Set error reporting to catch mysqli exceptions
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // 1. Connect to MySQL server
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS);

    // 2. Create database if it does not exist
    $mysqli->query('CREATE DATABASE IF NOT EXISTS ' . DB_NAME . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $mysqli->select_db(DB_NAME);

    // 3. Read setup_db.sql
    $sqlFile = __DIR__ . '/setup_db.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception('File setup_db.sql not found at ' . $sqlFile);
    }

    $sql = file_get_contents($sqlFile);
    if ($sql === false || trim($sql) === '') {
        throw new Exception('setup_db.sql is empty or could not be read.');
    }

    // 4. Execute all queries
    if ($mysqli->multi_query($sql)) {
        do {
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
        } while ($mysqli->more_results() && $mysqli->next_result());
    }

    $mysqli->close();

    // 5. Output confirmation HTML
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <title>Database Setup Successful</title>
      <link rel="stylesheet" href="styles.css">
      <style>
        .setup-box {
          max-width: 520px;
          margin: 60px auto;
          background: #ffffff;
          padding: 32px;
          border-radius: 12px;
          box-shadow: 0 10px 30px rgba(0,0,0,0.08);
          font-family: system-ui, sans-serif;
          color: #17202A;
        }
        .setup-box h2 { color: #0F766E; margin-top: 0; }
        .cred-card {
          background: #F4F5F7;
          border-left: 4px solid #0F766E;
          padding: 14px 16px;
          border-radius: 6px;
          margin: 20px 0;
          font-size: 14px;
        }
        .cred-card p { margin: 6px 0; }
        .go-btn {
          display: inline-block;
          background: #0F766E;
          color: #fff;
          padding: 12px 24px;
          border-radius: 8px;
          text-decoration: none;
          font-weight: 600;
        }
        .go-btn:hover { background: #115E59; }
      </style>
    </head>
    <body style="background: #F4F5F7;">
      <div class="setup-box">
        <h2>Database Setup Successful!</h2>
        <p>Database <strong><?= htmlspecialchars(DB_NAME) ?></strong> and all required tables have been created and initialized.</p>
        <div class="cred-card">
          <strong>Default Admin Account:</strong>
          <p><strong>Email:</strong> admin@stationerypro.com</p>
          <p><strong>Password:</strong> admin123</p>
        </div>
        <a href="index.html" class="go-btn">Go to Login Page &rarr;</a>
      </div>
    </body>
    </html>
    <?php
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo '<div style="font-family:sans-serif; padding:24px; color:#B42318; background:#FEE2E2; border-radius:8px; max-width:600px; margin:40px auto;">';
    echo '<h3>Database Setup Error</h3>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><small>Tip: Ensure MySQL is running in your XAMPP Control Panel and credentials in <code>config.php</code> are correct.</small></p>';
    echo '</div>';
} catch (Exception $e) {
    http_response_code(500);
    echo '<div style="font-family:sans-serif; padding:24px; color:#B42318; background:#FEE2E2; border-radius:8px; max-width:600px; margin:40px auto;">';
    echo '<h3>Setup Error</h3>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div>';
}


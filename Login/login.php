<?php
/**
 * login.php — Authenticates users against the Users table.
 * On success: stores user info in $_SESSION and redirects to index.php.
 */

require_once __DIR__ . '/config.php';

// If already logged in, go to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: ' . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        try {
            // Fetch user + role + depot in one query
            $stmt = $pdo->prepare(
                'SELECT u.id, u.username, u.password_hash, u.is_active,
                        u.depot_id, u.role_id,
                        r.role_name,
                        d.depot_name, d.city
                 FROM Users u
                 JOIN Roles r ON u.role_id = r.id
                 JOIN Depot d ON u.depot_id = d.id
                 WHERE u.username = ?'
            );
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'Invalid username or password.';
            } elseif (!$user['is_active']) {
                $error = 'This account has been deactivated. Contact an administrator.';
            } elseif (!password_verify($password, $user['password_hash'])) {
                $error = 'Invalid username or password.';
            } else {
                // ── Login successful — populate session ──
                session_regenerate_id(true);   // prevent session fixation
                $_SESSION['user_id']   = (int) $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['role_id']   = (int) $user['role_id'];
                $_SESSION['role_name'] = $user['role_name'];
                $_SESSION['depot_id']  = (int) $user['depot_id'];
                $_SESSION['depot_name']= $user['depot_name'];
                $_SESSION['city']      = $user['city'];

                // Update last_login_at
                $upd = $pdo->prepare('UPDATE Users SET last_login_at = NOW() WHERE id = ?');
                $upd->execute([$user['id']]);

                // Redirect to dashboard
                header('Location: ' . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/index.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'A database error occurred. Please try again later.';
            // In development: error_log($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Fleet — Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #1a237e 0%, #0d47a1 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            width: 400px;
            max-width: 90vw;
        }
        .login-card h1 {
            text-align: center;
            color: #1a237e;
            margin-bottom: 8px;
            font-size: 1.8rem;
        }
        .login-card .subtitle {
            text-align: center;
            color: #757575;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #424242;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #1a73e8;
        }
        .btn-login {
            width: 100%;
            padding: 13px;
            background: #1a73e8;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-login:hover { background: #1557b0; }
        .alert {
            background: #fce4e4;
            border-left: 4px solid #ea4335;
            color: #c62828;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .test-hint {
            margin-top: 24px;
            padding: 14px;
            background: #f5f7fa;
            border-radius: 8px;
            font-size: 0.8rem;
            color: #757575;
        }
        .test-hint strong { color: #424242; }
        .test-hint code {
            background: #e8eaf6;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>🚛 Smart Fleet</h1>
        <p class="subtitle">Fleet Management System</p>

        <?php if ($error): ?>
            <div class="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn-login">Sign In</button>
        </form>

        <div class="test-hint">
            <strong>Test accounts</strong> (all share the same password):<br>
            <code>hanoi.admin</code> · <code>hanoi.fleet_safety</code> · <code>hanoi.workshop</code><br>
            <code>danang.admin</code> · <code>hcm.admin</code> · <code>cantho.admin</code><br>
            <small>If you forgot the password, run <code>generate_hash.php</code> to reset it.</small>
        </div>
    </div>
</body>
</html>
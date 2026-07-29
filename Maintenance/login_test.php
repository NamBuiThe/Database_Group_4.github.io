<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db_functions.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, go straight to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: ' . base_url() . '/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $user = executeQuery(
            'SELECT id, username, password_hash, role_name, depot_id FROM Users WHERE username = ?',
            [$username],
            false
        );

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['depot_id']  = $user['depot_id'];

            header('Location: ' . base_url() . '/index.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - SmartFleet</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <div style="max-width: 400px; margin: 80px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h1 style="text-align: center; color: #333;">SmartFleet Login</h1>

        <?php if (!empty($error)): ?>
            <div style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Username:</label>
                <input type="text" name="username" required style="width: 100%; padding: 8px; box-sizing: border-box;">
            </div>
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Password:</label>
                <input type="password" name="password" required style="width: 100%; padding: 8px; box-sizing: border-box;">
            </div>
            <button type="submit" style="width: 100%; padding: 10px; background-color: #008CBA; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">Log In</button>
        </form>
    </div>
</body>
</html>

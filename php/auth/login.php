<?php
require_once '../config.php';

// ---------------------- Initialize ----------------------
$message = '';
$role = 'user'; // default role

// ---------------------- Handle POST Login ----------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $message = '<div class="alert alert-danger">Email and password are required.</div>';
    } else {
        // Fetch user
        $stmt = $conn->prepare("SELECT id, name, password, user_type FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $role = $user['user_type']; // admin or user

            // ---------------------- Start session safely ----------------------
            if (session_status() === PHP_SESSION_NONE) {
                session_name("SESSION_" . $role);
                session_start();
            }

            $loginSuccess = false;
            if ($role === 'admin' && $password === $user['password']) {
                $loginSuccess = true;
            } elseif ($role !== 'admin' && password_verify($password, $user['password'])) {
                $loginSuccess = true;
            }

            if ($loginSuccess) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_type'] = $role;

                header("Location: " . ($role === 'admin' ? "admin.php" : "../dashboard.php"));
                exit();
            } else {
                $message = '<div class="alert alert-danger">Invalid email or password.</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Invalid email or password.</div>';
        }
        $stmt->close();
    }
} else {
    // ---------------------- GET Request: Check Session ----------------------
    if (session_status() === PHP_SESSION_NONE) session_start();

    if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
        header("Location: " . ($_SESSION['user_type'] === 'admin' ? "admin.php" : "../dashboard.php"));
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Stock Platform</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea, #764ba2);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}
.login-card { border-radius: 20px; box-shadow: 0 15px 30px rgba(0,0,0,0.2); overflow: hidden; }
.login-card .card-body { padding: 40px; }
.login-card h2 { font-weight: 700; margin-bottom: 30px; color: #333; text-align: center; }
.form-control { border-radius: 10px; padding: 15px; }
.btn-primary { border-radius: 10px; padding: 12px; font-weight: bold; background: #667eea; border: none; }
.btn-primary:hover { background: #5a67d8; }
.card-footer { background: none; text-align: center; padding: 20px 0; color: #333; }
.card-footer a { color: #764ba2; font-weight: 500; text-decoration: none; }
.card-footer a:hover { text-decoration: underline; }
.alert { border-radius: 10px; }
</style>
<script>
window.onload = function() {
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
};
</script>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card login-card">
                <div class="card-body">
                    <h2>Login</h2>
                    <?php if (!empty($message)) echo $message; ?>
                    <form method="POST" autocomplete="off">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" name="password" id="password" class="form-control" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Login</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer">
                    <span>Don't have an account? <a href="register.php">Register</a></span>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>

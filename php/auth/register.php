<?php
session_start();
require_once '../config.php';

// ---------------------- Prevent Browser Caching ----------------------
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// ---------------------- Redirect if already logged in ----------------------
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'admin') {
        header("Location: admin.php"); // admin dashboard
        exit();
    } else {
        header("Location: ../dashboard.php"); // user dashboard
        exit();
    }
}

$message = '';

// ---------------------- Handle Registration ----------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($name) || empty($email) || empty($password)) {
        $message = '<div class="alert alert-danger">All fields are required.</div>';
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $name)) {
        $message = '<div class="alert alert-danger">Name should contain only letters and spaces.</div>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '<div class="alert alert-danger">Invalid email format.</div>';
    } elseif (!preg_match("/^(?=.*[A-Za-z])(?=.*\d).{6,}$/", $password)) {
        $message = '<div class="alert alert-danger">Password must be at least 6 characters and include at least one letter and one number.</div>';
    } else {
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $message = '<div class="alert alert-danger">An account with this email already exists.</div>';
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $stmt_insert = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $stmt_insert->bind_param("sss", $name, $email, $hashed_password);

            if ($stmt_insert->execute()) {
                $_SESSION['success_message'] = "Registration successful! You can now log in.";
                header("Location: login.php");
                exit();
            } else {
                $message = '<div class="alert alert-danger">An error occurred. Please try again later.</div>';
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register - Stock Platform</title>
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
.register-card {
    border-radius: 20px;
    box-shadow: 0 15px 30px rgba(0,0,0,0.2);
    overflow: hidden;
}
.register-card .card-body {
    padding: 40px;
}
.register-card h2 {
    font-weight: 700;
    margin-bottom: 30px;
    color: #333;
    text-align: center;
}
.form-control { border-radius: 10px; padding: 15px; }
.btn-primary { border-radius: 10px; padding: 12px; font-weight: bold; background: #667eea; border: none; }
.btn-primary:hover { background: #5a67d8; }
.card-footer { background: none; text-align: center; padding: 20px 0; }
.card-footer a { color: #764ba2; font-weight: 500; text-decoration: none; }
.card-footer a:hover { text-decoration: underline; }
.alert { border-radius: 10px; }
.icon { font-size: 50px; color: #667eea; display: block; margin: 0 auto 20px; }
</style>
<script>
// Prevent back button after login
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
            <div class="card register-card">
                <div class="card-body">
                    <i class="fas fa-user-plus icon"></i>
                    <h2>Stock Platform Registration</h2>
                    <?php if (!empty($message)) echo $message; ?>
                    <form method="POST" autocomplete="off">
                        <div class="mb-3">
                            <label for="name" class="form-label"><i class="fas fa-user me-2"></i>Full Name</label>
                            <input type="text" name="name" id="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label"><i class="fas fa-envelope me-2"></i>Email</label>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label"><i class="fas fa-lock me-2"></i>Password</label>
                            <input type="password" name="password" id="password" class="form-control" required>
                            <small class="text-muted">At least 6 characters, letters & numbers.</small>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Register</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer">
                    Already have an account? <a href="login.php">Login here</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>

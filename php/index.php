<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Stock Prediction Platform</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet" />

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css" />

    <style>
        body {
            background-color: #f5f6fa;
            color: #2f3640;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .hero-section {
            background-color: #ffffff;
            padding: 4rem 2rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 3rem;
        }
        .btn-primary {
            background-color: #1e3a8a;
            border-color: #1e3a8a;
        }
        .btn-primary:hover {
            background-color: #162c61;
            border-color: #162c61;
        }
        .navbar-brand {
            font-weight: 600;
            letter-spacing: 0.05rem;
        }
        main.container {
            flex: 1;
        }
        footer {
            background-color: #1e1e2f;
            color: #cfd4dc;
            padding: 1rem 0;
            text-align: center;
            font-size: 0.9rem;
            margin-top: auto;
        }
        footer a {
            color: #cfd4dc;
            text-decoration: none;
        }
        footer a:hover {
            text-decoration: underline;
        }
        .feature-card {
            background-color: #ffffff;
            border-radius: 0.5rem;
            padding: 2rem 1rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .feature-icon {
            font-size: 2.5rem;
            color: #1e3a8a;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="#">Stock Platform</a>
        <div class="ms-auto">
            <a href="auth/login.php" class="btn btn-outline-light me-2">Login</a>
            <a href="auth/register.php" class="btn btn-primary">Register</a>
        </div>
    </div>
</nav>

<main class="container mt-5">
    <section class="hero-section text-center">
        <h1 class="display-5 fw-bold mb-3">Welcome to the Stock Prediction Platform</h1>
        <p class="lead mb-4 mx-auto" style="max-width: 700px;">
            Gain insights into the NEPSE market with intelligent stock price predictions powered by advanced machine learning models.
            Perfect for investors, analysts, and enthusiasts to make informed decisions.
        </p>
        <div>
            <a href="auth/register.php" class="btn btn-primary btn-lg me-3">Get Started <i class="bi bi-arrow-right-circle"></i></a>
            <a href="auth/login.php" class="btn btn-outline-secondary btn-lg">Login <i class="bi bi-box-arrow-in-right"></i></a>
        </div>
    </section>

    <section class="mt-5">
        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="feature-card">
                    <i class="bi bi-graph-up-arrow feature-icon"></i>
                    <h4 class="mb-3">Accurate Predictions</h4>
                    <p>Leverage machine learning algorithms trained on historical stock data for reliable forecasts.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <i class="bi bi-speedometer2 feature-icon"></i>
                    <h4 class="mb-3">User-Friendly Dashboard</h4>
                    <p>Track predictions, review history, and visualize stock trends with an intuitive interface.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <i class="bi bi-shield-lock feature-icon"></i>
                    <h4 class="mb-3">Secure & Private</h4>
                    <p>Your account and data are safe with us. We use best practices for top-notch security.</p>
                </div>
            </div>
        </div>
    </section>
</main>

<footer>
    &copy; <?= date('Y') ?> Stock Prediction Platform. Developed by Shiva Khatiwada. |
    <a href="privacy.php">Privacy Policy</a> | 
    <a href="terms.php">Terms of Service</a>
</footer>

</body>
</html>

<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Terms of Service - Stock Prediction Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        body {
            background-color: #f8f9fa;
            color: #343a40;
            display: flex;
            min-height: 100vh;
            flex-direction: column;
        }
        main.container {
            flex: 1;
        }
        footer {
            background-color: #343a40;
            color: #adb5bd;
            padding: 1rem 0;
            text-align: center;
            font-size: 0.9rem;
            margin-top: auto;
        }
        footer a {
            color: #adb5bd;
            text-decoration: none;
        }
        footer a:hover {
            text-decoration: underline;
        }
        h1, h2 {
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="index.php">Stock Platform</a>
        <div class="ms-auto">
            <a href="auth/login.php" class="btn btn-outline-light me-2">Login</a>
            <a href="auth/register.php" class="btn btn-primary">Register</a>
        </div>
    </div>
</nav>

<main class="container mt-5 mb-5">
    <h1>Terms of Service</h1>
    <p>Last updated: <?= date('F j, Y') ?></p>

    <p>Welcome to Stock Prediction Platform. By accessing or using our service, you agree to be bound by the following terms and conditions:</p>

    <h2>Use of Service</h2>
    <ul>
        <li>You agree to use the platform only for lawful purposes.</li>
        <li>You acknowledge that stock price predictions are based on models and are not guaranteed financial advice.</li>
        <li>We do not accept liability for any financial decisions made based on the information provided.</li>
    </ul>

    <h2>Account Responsibility</h2>
    <p>You are responsible for maintaining the confidentiality of your account credentials and for all activities under your account.</p>

    <h2>Intellectual Property</h2>
    <p>All content, trademarks, and software on this platform are the property of Stock Prediction Platform and are protected by applicable laws.</p>

    <h2>Modifications</h2>
    <p>We reserve the right to modify or terminate the service at any time without prior notice.</p>

    <h2>Governing Law</h2>
    <p>These terms are governed by the laws of the jurisdiction where the platform operates.</p>

    <h2>Contact Us</h2>
    <p>If you have questions about these terms, please contact us at <a href="mailto:support@stockplatform.com">support@stockplatform.com</a>.</p>
</main>

<footer>
    &copy; <?= date('Y') ?> Stock Prediction Platform. All rights reserved. | 
    <a href="privacy.php">Privacy Policy</a> | 
    <a href="terms.php">Terms of Service</a>
</footer>

</body>
</html>

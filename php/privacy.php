<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Privacy Policy - Stock Prediction Platform</title>
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
    <h1>Privacy Policy</h1>
    <p>Last updated: <?= date('F j, Y') ?></p>

    <p>At Stock Prediction Platform, your privacy is important to us. This policy explains how we collect, use, and protect your information.</p>

    <h2>Information We Collect</h2>
    <ul>
        <li>Personal information you provide during registration (name, email, password).</li>
        <li>Stock prediction data associated with your account.</li>
        <li>Usage data including login activity and preferences.</li>
    </ul>

    <h2>How We Use Your Information</h2>
    <ul>
        <li>To provide and maintain our services.</li>
        <li>To personalize your experience on the platform.</li>
        <li>To communicate important updates and notices.</li>
        <li>To improve our machine learning models and platform features.</li>
    </ul>

    <h2>Data Security</h2>
    <p>We implement reasonable security measures to protect your information from unauthorized access, alteration, or disclosure.</p>

    <h2>Third-Party Services</h2>
    <p>We do not sell or share your personal information with third parties except as required to provide the service or comply with legal obligations.</p>

    <h2>Your Rights</h2>
    <p>You may update your account information or request deletion by contacting our support team.</p>

    <h2>Changes to This Policy</h2>
    <p>We may update this privacy policy from time to time. We will notify users by posting the new policy on this page.</p>

    <p>If you have any questions about this policy, please contact us at <a href="mailto:support@stockplatform.com">support@stockplatform.com</a>.</p>
</main>

<footer>
    &copy; <?= date('Y') ?> Stock Prediction Platform. All rights reserved. | 
    <a href="privacy.php">Privacy Policy</a> | 
    <a href="terms.php">Terms of Service</a>
</footer>

</body>
</html>

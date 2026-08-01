<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ticker'])) {
    $ticker = preg_replace('/[^A-Za-z0-9_-]/', '', $_POST['ticker']);
    $flask_url = "http://localhost:5000/admin/train/$ticker";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $flask_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $respData = json_decode($response, true);
        $_SESSION['action_msg'] = $respData['message'] ?? "Model trained for $ticker.";
    } else {
        $_SESSION['action_msg'] = "Failed to request model training for $ticker.";
    }
} else {
    $_SESSION['action_msg'] = "Invalid request.";
}

header("Location: admin.php");
exit;

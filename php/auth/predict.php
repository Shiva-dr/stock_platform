<?php
// Function to call Flask API and get prediction
function get_prediction_from_flask($ticker) {
    $url = "http://localhost:5000/predict";  // Change if Flask runs elsewhere

    $data = json_encode(['ticker' => $ticker]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return ['error' => "cURL error: $error_msg"];
    }
    curl_close($ch);

    return json_decode($response, true);
}

$prediction = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticker = strtoupper(trim($_POST['ticker']));
    if ($ticker) {
        $result = get_prediction_from_flask($ticker);
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
                // normalize response to support multi-model API
                $prediction = $result;
            }
    } else {
        $error = "Please enter a ticker symbol.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Stock Prediction</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
<div class="container mt-5">
    <h1>Get Stock Price Prediction</h1>
    <form method="POST" class="mb-4">
        <input type="text" name="ticker" placeholder="Enter ticker symbol" class="form-control" required>
        <button type="submit" class="btn btn-primary mt-2">Predict</button>
    </form>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($prediction): ?>
        <div class="alert alert-success">
            <p><strong>Ticker:</strong> <?= htmlspecialchars($ticker) ?></p>
            <p><strong>Last Known Price:</strong> <?= htmlspecialchars($prediction['last_known_price'] ?? 'N/A') ?></p>
            <hr />
            <h5>Model Predictions</h5>
            <?php if (!empty($prediction['predictions']) && is_array($prediction['predictions'])): ?>
                <?php foreach ($prediction['predictions'] as $p): ?>
                    <div class="mb-2">
                        <strong><?= htmlspecialchars($p['model_type'] ?? 'Model') ?>:</strong>
                        <?= htmlspecialchars(implode(', ', array_map(function($v){ return number_format((float)$v,2,'.',''); }, ($p['predicted_price'] ?? [])))) ?: 'N/A' ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No model predictions returned.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>

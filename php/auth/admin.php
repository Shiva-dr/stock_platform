<?php
session_start();
require_once '../config.php';

// ---------------------- Prevent Browser Caching ----------------------
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// ---------------------- Admin Authentication ----------------------
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// ---------------------- CSRF Token ----------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Admin info
$user_name = $_SESSION['user_name'];
$action_msg = $_SESSION['action_msg'] ?? '';
unset($_SESSION['action_msg']);

// ---------------------- Handle Admin Actions ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['csrf_token']) 
    && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {

    $action = $_POST['action'];

    // Delete user
    if ($action === 'delete_user' && isset($_POST['user_id'])) {
        $uid = intval($_POST['user_id']);
        if ($uid != $_SESSION['user_id']) { // Prevent self-deletion
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $uid);
            $action_msg = $stmt->execute() ? "✅ User deleted." : "❌ Error deleting user: " . $conn->error;
            $stmt->close();
        } else {
            $action_msg = "⚠️ You cannot delete your own account.";
        }
    }

    // Add company
    if ($action === 'add_company') {
        $name = trim($_POST['name']);
        $ticker = strtoupper(trim($_POST['ticker']));
        $description = trim($_POST['description']);
        $sector = trim($_POST['sector']);
        if ($name && $ticker) {
            $stmt = $conn->prepare("INSERT INTO companies (name, ticker, description, sector) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $ticker, $description, $sector);
            $action_msg = $stmt->execute() ? "✅ Company added successfully." : "❌ Error adding company: " . $conn->error;
            $stmt->close();
        } else {
            $action_msg = "⚠️ Please fill in company name and ticker.";
        }
    }

    // Delete company
    if ($action === 'delete_company' && isset($_POST['company_id'])) {
        $cid = intval($_POST['company_id']);
        $stmt = $conn->prepare("DELETE FROM companies WHERE id = ?");
        $stmt->bind_param("i", $cid);
        $action_msg = $stmt->execute() ? "✅ Company deleted." : "❌ Error deleting company: " . $conn->error;
        $stmt->close();
    }

    // Update company
    if ($action === 'update_company' && isset($_POST['company_id'])) {
        $cid = intval($_POST['company_id']);
        $name = trim($_POST['name']);
        $ticker = strtoupper(trim($_POST['ticker']));
        $description = trim($_POST['description']);
        $sector = trim($_POST['sector']);
        if ($name && $ticker) {
            $stmt = $conn->prepare("UPDATE companies SET name = ?, ticker = ?, description = ?, sector = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $ticker, $description, $sector, $cid);
            $action_msg = $stmt->execute() ? "✅ Company updated successfully." : "❌ Error updating company: " . $conn->error;
            $stmt->close();
        } else {
            $action_msg = "⚠️ Please fill in all fields for update.";
        }
    }
}

// ---------------------- Fetch Users and Companies ----------------------
$users_result = $conn->query("SELECT id, name, email, created_at FROM users ORDER BY id DESC");
$companies_result = $conn->query("SELECT id, name, ticker, description, sector FROM companies ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Dashboard - Stock Platform</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<style>
body { background-color: #f5f6fa; }
h3 { margin-top: 20px; }
.card { border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
.table th, .table td { vertical-align: middle; }
.navbar-brand { font-weight: bold; font-size: 1.4rem; }
textarea { resize: none; }
#predictionChartContainer { white-space: pre-wrap; }
.table-responsive { max-height:420px; overflow:auto; }
.table-responsive thead th { position: sticky; top:0; background:#fff; z-index:2; }
.table-secondary td { background:#f1f5f9; font-weight:600; }
.badge-error { background:#dc3545; color:#fff; }
.badge-ok { background:#198754; color:#fff; }
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="#">📊 Stock Platform Admin</a>
        <div class="d-flex align-items-center">
            <span class="navbar-text me-3">👤 <?= htmlspecialchars($user_name) ?></span>
            <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>
</nav>

<main class="container mt-4 mb-5">

<!-- Action Message -->
<?php if ($action_msg): ?>
    <div class="alert <?= str_contains($action_msg, '✅') ? 'alert-success' : 'alert-warning' ?>">
        <?= htmlspecialchars($action_msg) ?>
    </div>
<?php endif; ?>

<h1 class="mb-4">⚙️ Admin Dashboard</h1>

<!-- Users Management -->
<div class="card p-3 mb-4">
<h3>👥 Users Management</h3>
<p class="text-muted">Manage registered users. You cannot delete your own account.</p>
<div class="table-responsive">
<table class="table table-hover table-bordered align-middle">
<thead class="table-dark">
<tr>
<th>ID</th><th>Name</th><th>Email</th><th>Created At</th><th>Action</th>
</tr>
</thead>
<tbody>
<?php while ($user = $users_result->fetch_assoc()): ?>
<tr>
<td><?= $user['id'] ?></td>
<td><?= htmlspecialchars($user['name']) ?></td>
<td><?= htmlspecialchars($user['email']) ?></td>
<td><?= $user['created_at'] ?></td>
<td>
<?php if ($user['id'] != $_SESSION['user_id']): ?>
<form method="POST" style="display:inline-block;">
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<input type="hidden" name="action" value="delete_user">
<input type="hidden" name="user_id" value="<?= $user['id'] ?>">
<button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('Delete this user?')">
<i class="bi bi-trash3"></i> Delete
</button>
</form>
<?php else: ?>
<span class="text-muted">--</span>
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>

<!-- Companies Management -->
<div class="card p-3 mb-4">
<h3>🏢 Companies Management</h3>

<!-- Add Company -->
<form method="POST" class="row g-3 mb-4">
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<input type="hidden" name="action" value="add_company">
<div class="col-md-3">
<input type="text" name="name" placeholder="Company Name" class="form-control" required>
</div>
<div class="col-md-2">
<input type="text" name="ticker" placeholder="Ticker Symbol" class="form-control" required>
</div>
<div class="col-md-4">
<textarea name="description" placeholder="Company Info" class="form-control" rows="2" required></textarea>
</div>
<div class="col-md-2">
<input type="text" name="sector" placeholder="Sector" class="form-control">
</div>
<div class="col-md-1 d-grid">
<button class="btn btn-success" type="submit"><i class="bi bi-plus-circle"></i> Add</button>
</div>
</form>

<!-- Companies Table -->
<div class="table-responsive">
<table class="table table-hover table-bordered align-middle">
<thead class="table-dark">
<tr>
<th>ID</th><th>Name</th><th>Ticker</th><th>Info</th><th>Sector</th><th>Actions</th>
</tr>
</thead>
<tbody>
<?php while ($company = $companies_result->fetch_assoc()): ?>
<tr>
<td><?= $company['id'] ?></td>
<td><?= htmlspecialchars($company['name']) ?></td>
<td><?= htmlspecialchars($company['ticker']) ?></td>
<td><?= htmlspecialchars($company['description']) ?></td>
<td><?= htmlspecialchars($company['sector']) ?></td>
<td>
<form method="POST" style="display:inline-block;">
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<input type="hidden" name="action" value="delete_company">
<input type="hidden" name="company_id" value="<?= $company['id'] ?>">
<button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('Delete this company?')"><i class="bi bi-trash3"></i></button>
</form>

<!-- Update Modal -->
<button class="btn btn-sm btn-warning" type="button" data-bs-toggle="modal" data-bs-target="#updateModal<?= $company['id'] ?>"><i class="bi bi-pencil-square"></i></button>
<div class="modal fade" id="updateModal<?= $company['id'] ?>" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Update Company</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<form method="POST">
<div class="modal-body">
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<input type="hidden" name="action" value="update_company">
<input type="hidden" name="company_id" value="<?= $company['id'] ?>">
<div class="mb-3"><label>Company Name</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($company['name']) ?>" required></div>
<div class="mb-3"><label>Ticker Symbol</label><input type="text" name="ticker" class="form-control" value="<?= htmlspecialchars($company['ticker']) ?>" required></div>
<div class="mb-3"><label>Company Info</label><textarea name="description" class="form-control" rows="3" required><?= htmlspecialchars($company['description']) ?></textarea></div>
<div class="mb-3"><label>Sector</label><input type="text" name="sector" class="form-control" value="<?= htmlspecialchars($company['sector']) ?>"></div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
<button type="submit" class="btn btn-primary">Update</button>
</div>
</form>
</div>
</div>
</div>

</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>

<!-- Prediction Analysis Tool -->
<div class="card p-3 mb-4">
<h3><i class="bi bi-lightning-charge-fill text-warning"></i> Prediction Analysis Tool</h3>
<form id="predictForm" class="row g-3">
    <div class="col-md-6">
        <input type="text" id="tickerPredict" name="ticker" placeholder="Enter Ticker (e.g., HDL)" class="form-control" required>
    </div>
    <div class="col-md-2 d-grid">
        <button type="submit" class="btn btn-primary">Run Prediction</button>
    </div>
</form>
<div id="predictionTableContainer" class="mt-3" style="display:none;">
    <div class="table-responsive">
        <table class="table table-sm table-striped" id="adminPredictionsTable">
            <thead>
                <tr><th>Model</th><th>Latest Pred</th><th>MAPE</th><th>RMSE</th><th>Action</th></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div id="predictionChartContainer" class="mt-3" style="display:none;">
    <canvas id="predictionChart" height="150"></canvas>
</div>

<!-- Inline admin detail table -->
<div id="adminInlineDetail" class="mt-3" style="display:none;">
    <div class="card">
        <div class="card-header bg-light">Per-day Comparison — <span id="adminInlineModelName"></span></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-bordered" id="adminDetailInlineTable">
                    <thead><tr><th>Date</th><th>Actual</th><th>Predicted</th><th>Change</th><th>Error</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal for admin details -->
<div class="modal fade" id="adminModelModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="adminModelTitle">Model Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3 text-end">
            <button id="adminRetrainBtn" class="btn btn-sm btn-warning"><i class="bi bi-arrow-clockwise"></i> Retrain</button>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-sm" id="adminModelDetailTable">
                <thead><tr><th>Date</th><th>Actual</th><th>Predicted</th><th>Change</th><th>Error</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
      </div>
    </div>
  </div>
</div>

</div>

<!-- Per-day Admin Modal -->
<div class="modal fade" id="adminPerDayModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Per-day Comparison</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
            <table class="table table-sm table-borderless">
                <tbody>
                    <tr><th style="width:40%">Date</th><td id="adminPerDayDate">-</td></tr>
                    <tr><th>Actual Close</th><td id="adminPerDayActual">-</td></tr>
                    <tr><th>Predicted</th><td id="adminPerDayPred">-</td></tr>
                    <tr><th>Change in Actual</th><td id="adminPerDayChange">-</td></tr>
                    <tr><th>Absolute Error</th><td id="adminPerDayError">-</td></tr>
                </tbody>
            </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

</main>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
<script>
const baseApi = "http://127.0.0.1:5000";
let predictionChart = null;

document.getElementById('predictForm').addEventListener('submit', async function(e){
    e.preventDefault();
    const ticker = document.getElementById('tickerPredict').value.trim();
    if(!ticker) return;

    const chartContainer = document.getElementById('predictionChartContainer');
    chartContainer.style.display = 'block';

    try {
        const resp = await fetch(`http://127.0.0.1:5000/predict`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ticker, horizon: 30 })
        });
        if(!resp.ok) throw new Error('HTTP error ' + resp.status);
        const data = await resp.json();

        if(!data.predictions || !Array.isArray(data.predictions) || data.predictions.length===0){
            alert("❌ Prediction failed or no data returned.");
            return;
        }

        // Build admin summary table first
        const $adminTbody = $('#adminPredictionsTable tbody'); $adminTbody.empty();
        data.predictions.forEach((p, i)=>{
            const latest = (p.predicted_price && p.predicted_price.length)? Number(p.predicted_price[0]) : null;
            const mape = p.metrics && p.metrics.mape!=null ? p.metrics.mape.toFixed(2) : '';
            const rmse = p.metrics && p.metrics.rmse!=null ? p.metrics.rmse.toFixed(2) : '';
            const $tr = $(`<tr style="cursor:pointer;"></tr>`);
            $tr.append(`<td>${p.model_type}</td>`);
            $tr.append(`<td>${latest!=null? latest.toFixed(2): 'N/A'}</td>`);
            $tr.append(`<td>${mape}</td>`);
            $tr.append(`<td>${rmse}</td>`);
            $tr.append(`<td><button class="btn btn-sm btn-primary admin-detail-btn" data-idx="${i}">Details</button></td>`);
            $adminTbody.append($tr);
        });
        $('#predictionTableContainer').show();

        // Now prepare chart as before
        const horizon = (data.predictions[0].predicted_price || data.predictions[0].predicted || []).length || 0;
        const labels = [];
        for(let i=1;i<=horizon;i++) labels.push(`Day ${i}`);

        const datasets = data.predictions.map((p, i) => ({
            label: p.model_type || p.model || `Model ${i+1}`,
            data: (p.predicted_price || p.predicted || []).map(Number),
            borderColor: ['#198754','#ffc107','#0dcaf0','#6610f2','#e83e8c'][i%5],
            backgroundColor: 'transparent',
            borderWidth: 2,
            tension: 0.25
        }));

        if(predictionChart) predictionChart.destroy();
        const ctx = document.getElementById('predictionChart').getContext('2d');
        predictionChart = new Chart(ctx, {
            type: 'line',
            data: { labels, datasets },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top' } },
                interaction: { mode: 'index', intersect: false },
                scales: { y: { beginAtZero: false } }
            }
        });

        // admin details buttons
        $('.admin-detail-btn').on('click', function(){
            const idx = $(this).data('idx');
            const model = data.predictions[idx];
            // populate modal table
            const $mb = $('#adminModelDetailTable tbody'); $mb.empty();
            const history = data.history || [];
            const lastDate = history.length? new Date(history[history.length-1].Date) : new Date();
            const horizonLocal = model.predicted_price.length;
            // Historical section: show last N days from CSV so users always see actual Close values
            const HIST_DAYS = Math.min(30, history.length);
            if(HIST_DAYS>0){
                $mb.append(`<tr class="table-secondary"><td colspan="5"><strong>Historical (last ${HIST_DAYS} days)</strong></td></tr>`);
                const startIdx = Math.max(0, history.length - HIST_DAYS);
                for(let j=startIdx;j<history.length;j++){
                    const hr = history[j];
                    const dateStr = hr.Date;
                    const actual = (hr.Close!=null && hr.Close!=='')? Number(hr.Close) : null;
                    // try to find a backtest prediction for this date
                    let predMatch = null;
                    if(Array.isArray(model.backtest)) predMatch = model.backtest.find(b=> (b.date||b['date'])===dateStr);
                    const pred = predMatch? Number(predMatch.predicted) : null;
                    const error = (actual!=null && pred!=null)? Math.abs(pred-actual).toFixed(2) : 'N/A';
                    let changeActual = '';
                    if(j>0){ const prev = Number(history[j-1].Close); changeActual = (actual!=null? (actual - prev).toFixed(2) : ''); }
                    const rowHtml = `<tr class="drill-row" data-date="${dateStr}" data-actual="${actual!=null?actual:''}" data-pred="${pred!=null?pred:''}"><td>${dateStr}</td><td>${actual!=null? actual.toFixed(2): 'N/A'}</td><td>${pred!=null? pred.toFixed(2): 'N/A'}</td><td>${changeActual}</td><td>${error}</td></tr>`;
                    $mb.append(rowHtml);
                }
            }

            // If backtest points are available from API, show them next (these have actuals and were model-generated)
            if(Array.isArray(model.backtest) && model.backtest.length){
                $mb.append(`<tr class="table-secondary"><td colspan="5"><strong>Backtest (model vs actual)</strong></td></tr>`);
                model.backtest.forEach(bt=>{
                    const dateStr = bt.date || bt['date'];
                    const actual = (bt.actual!=null)? Number(bt.actual) : null;
                    const pred = (bt.predicted!=null)? Number(bt.predicted) : null;
                    const predError = (actual!=null && pred!=null)? Math.abs(pred-actual).toFixed(2) : 'N/A';
                    let changeActual = '';
                    const idxHist = history.findIndex(r=>r.Date===dateStr);
                    if(idxHist>0){ const prev = Number(history[idxHist-1].Close); changeActual = (actual - prev).toFixed(2); }
                    const rowHtml = `<tr class="drill-row" data-date="${dateStr}" data-actual="${actual!=null?actual:''}" data-pred="${pred!=null?pred:''}"><td>${dateStr}</td><td>${actual!=null? actual.toFixed(2): 'N/A'}</td><td>${pred!=null? pred.toFixed(2): 'N/A'}</td><td>${changeActual}</td><td><span class="badge ${predError==='N/A'? 'badge-secondary': (Number(predError)>50? 'badge-error':'badge-ok')}">${predError}</span></td></tr>`;
                    $mb.append(rowHtml);
                });
            }

            // then future
            for(let i=0;i<horizonLocal;i++){
                const d = new Date(lastDate); d.setDate(d.getDate()+i+1);
                const dateStr = d.toISOString().substring(0,10);
                const actualObj = history.find(r=>r.Date===dateStr);
                const actual = actualObj? Number(actualObj.Close) : null;
                let changeActual = '';
                if(actualObj){
                    const prevIndex = history.findIndex(r=>r.Date===dateStr)-1;
                    if(prevIndex>=0){ const prev = Number(history[prevIndex].Close); changeActual = (actual - prev).toFixed(2); }
                }
                const pred = Number(model.predicted_price[i]);
                const predError = (actual!=null)? Math.abs(pred - actual).toFixed(2) : 'N/A';
                const rowHtml = `<tr class="drill-row" data-date="${dateStr}" data-actual="${actual!=null?actual:''}" data-pred="${pred}"><td>${dateStr}</td><td>${actual!=null? actual.toFixed(2): 'N/A'}</td><td>${pred.toFixed(2)}</td><td>${changeActual}</td><td>${predError}</td></tr>`;
                $mb.append(rowHtml);
            }
            // populate inline admin detail table as well
            $('#adminInlineModelName').text(model.model_type);
            const $inlineAdmin = $('#adminDetailInlineTable tbody'); $inlineAdmin.empty();
            $('#adminModelDetailTable tbody tr.drill-row').each(function(){ $inlineAdmin.append($(this).clone()); });
            try{ if($.fn.DataTable.isDataTable('#adminDetailInlineTable')){ $('#adminDetailInlineTable').DataTable().destroy(); } }catch(e){}
            $('#adminDetailInlineTable').DataTable({paging:true, pageLength:10, searching:true, info:true});
            $('#adminInlineDetail').fadeIn();

            // attach click handler for per-day drill
            $('#adminModelDetailTable tbody').off('click').on('click', 'tr.drill-row', function(){
                const $r = $(this);
                const date = $r.data('date');
                const actual = $r.data('actual');
                const pred = $r.data('pred');
                const error = (actual!='' && pred!='')? Math.abs(Number(pred)-Number(actual)).toFixed(2) : 'N/A';
                const prevIdx = history.findIndex(r=>r.Date===date)-1;
                const prev = (prevIdx>=0)? Number(history[prevIdx].Close) : null;
                const changeActual = (actual!='' && prev!=null)? (Number(actual)-prev).toFixed(2) : '';
                $('#adminPerDayDate').text(date);
                $('#adminPerDayActual').text(actual!=''? Number(actual).toFixed(2) : 'N/A');
                $('#adminPerDayPred').text(pred!=''? Number(pred).toFixed(2) : 'N/A');
                $('#adminPerDayChange').text(changeActual!=''? changeActual : 'N/A');
                $('#adminPerDayError').text(error);
                const perModal = new bootstrap.Modal(document.getElementById('adminPerDayModal'));
                perModal.show();
            });
            $('#adminModelTitle').text(model.model_type + ' — Details');
            $('#adminRetrainBtn').off('click').on('click', async ()=>{
                $('#adminRetrainBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Queued');
                try{
                    const jobResp = await $.ajax({url:`${baseApi}/retrain`, method:'POST', contentType:'application/json', data: JSON.stringify({ticker: $('#tickerPredict').val(), model: model.model_type, horizon: model.predicted_price.length}), dataType:'json'});
                    if(!jobResp || !jobResp.job_id){ throw new Error('failed to enqueue job'); }
                    const jobId = jobResp.job_id;
                    $('#adminRetrainBtn').html('Retraining...');
                    const pollInterval = 2000; const maxWait = 5*60*1000; const start = Date.now();
                    const poller = setInterval(async ()=>{
                        try{
                            const status = await $.getJSON(`${baseApi}/job_status/${jobId}`);
                            if(status.status === 'done'){
                                clearInterval(poller);
                                $('#adminRetrainBtn').prop('disabled', false).html('<i class="bi bi-arrow-clockwise"></i> Retrain');
                                // refresh data
                                $('#predictForm').submit();
                                return;
                            } else if(status.status === 'failed'){
                                clearInterval(poller);
                                alert('Retrain failed: ' + (status.error || 'see logs'));
                                $('#adminRetrainBtn').prop('disabled', false).html('<i class="bi bi-arrow-clockwise"></i> Retrain');
                                return;
                            }
                            if(Date.now()-start > maxWait){ clearInterval(poller); alert('Retrain taking too long. Check job status later.'); $('#adminRetrainBtn').prop('disabled', false).html('<i class="bi bi-arrow-clockwise"></i> Retrain'); return; }
                        }catch(e){ console.error('poll', e); }
                    }, pollInterval);
                }catch(e){ console.error(e); alert('Failed to enqueue retrain job'); $('#adminRetrainBtn').prop('disabled', false).html('<i class="bi bi-arrow-clockwise"></i> Retrain'); }
            });
            const modal = new bootstrap.Modal(document.getElementById('adminModelModal'));
            modal.show();
        });

    } catch(err){
        console.error(err);
        alert("❌ Error connecting to Flask backend.");
    }
});
</script>
</body>
</html>

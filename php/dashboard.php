<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

if(isset($_SESSION['admin_id'])){
    header("Location: ../admin_dashboard.php"); exit();
}
if(!isset($_SESSION['user_id'])){
    header("Location: login.php"); exit();
}

require_once 'config.php';
$stmt = $conn->prepare("SELECT name FROM users WHERE id=?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>📊 Stock Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
<!-- DataTables for sortable/searchable tables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<style>
body {background:#f4f6f9; font-family:'Segoe UI',sans-serif;}
.sidebar {width:220px; background:#1f2937; color:#fff; position:fixed; height:100%; padding-top:20px;}
.sidebar a {color:#fff; display:block; padding:10px 20px; text-decoration:none; margin-bottom:5px; border-radius:5px;}
.sidebar a:hover {background:#374151;}
.content {margin-left:240px; padding:20px;}
.header {display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;}
.kpi-card {border-radius:10px; box-shadow:0 3px 6px rgba(0,0,0,0.08);}
#loader {display:none; text-align:center; margin:20px;}
.small-muted {font-size:0.9rem; color:#6c757d;}
/* Make table headers sticky inside scrollable containers */
.table-responsive { max-height: 420px; overflow: auto; }
.table-responsive thead th { position: sticky; top: 0; background: #fff; z-index: 2; }
/* Section header rows styling */
.table-secondary td { background:#f1f5f9; font-weight:600; }
/* Error badge */
.badge-error { background:#dc3545; color:#fff; }
.badge-ok { background:#198754; color:#fff; }
</style>
</head>
<body>

<div class="sidebar">
    <h4 class="text-center mb-4">Stock Dashboard</h4>
    <a href="#"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
    <a href="auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>

<div class="content">
    <div class="header">
        <h2>Welcome, <?= htmlspecialchars($user['name']) ?>!</h2>
        <div class="small-muted">Backend: Flask API — <span id="apiStatus">checking...</span></div>
    </div>

    <div id="loader">
        <div class="spinner-border text-primary" role="status"></div>
        <p>Loading data...</p>
    </div>

    <!-- Controls -->
    <div class="row mb-3">
        <div class="col-md-5">
            <select id="tickerSelect" class="form-select">
                <option value="">-- Select Ticker --</option>
            </select>
        </div>
        <div class="col-md-3">
            <button id="loadDataBtn" class="btn btn-primary w-100">
                <i class="fa-solid fa-magnifying-glass-chart"></i> Predict
            </button>
        </div>
    </div>

    <!-- KPIs -->
    <div class="row mb-4" id="kpiSection" style="display:none;">
        <div class="col-md-3 mb-2">
            <div class="card kpi-card text-center p-3 bg-white">
                <h6 class="text-muted">Last Price</h6>
                <h4 id="kpiPrice">N/A</h4>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card kpi-card text-center p-3 bg-white">
                <h6 class="text-muted">Max Prediction</h6>
                <h4 id="kpiMax">N/A</h4>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card kpi-card text-center p-3 bg-white">
                <h6 class="text-muted">Min Prediction</h6>
                <h4 id="kpiMin">N/A</h4>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card kpi-card text-center p-3 bg-white">
                <h6 class="text-muted">Action</h6>
                <h4 id="kpiAction"><span class="badge bg-secondary">N/A</span></h4>
            </div>
        </div>
    </div>

    <!-- Predictions Table -->
    <div class="card mb-4" id="predictionTableCard" style="display:none;">
        <div class="card-header bg-secondary text-white">Model Predictions (Latest)</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-striped" id="predictionsTable">
                    <thead>
                        <tr>
                            <th>Model</th>
                            <th>Latest Pred</th>
                            <th>Avg Pred</th>
                            <th>Change % (vs last)</th>
                            <th>MAPE %</th>
                            <th>RMSE</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <p class="small-muted mt-2">Click a model row to view the full per-day comparison and retrain options.</p>
        </div>
    </div>

    <!-- Inline Detailed Table (appears when user selects a model) -->
    <div class="card mb-4" id="modelDetailInlineCard" style="display:none;">
        <div class="card-header bg-light">Per-day Comparison — <span id="inlineModelName"></span></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-bordered" id="modelDetailInlineTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Actual Close</th>
                            <th>Predicted Price</th>
                            <th>Change in Actual</th>
                            <th>Absolute Error</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Chart -->
    <div class="card mb-4" id="chartCard" style="display:none;">
        <div class="card-header bg-primary text-white">Last 30 Days & 30-day Forecast</div>
        <div class="card-body">
            <canvas id="stockChart" height="150"></canvas>
        </div>
    </div>

    <!-- Indicators -->
    <div class="card mb-4" id="indicatorCard" style="display:none;">
        <div class="card-header bg-info text-white">Technical Indicators</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4"><canvas id="smaChart" height="100"></canvas></div>
                <div class="col-md-4"><canvas id="rsiChart" height="100"></canvas></div>
                <div class="col-md-4"><canvas id="macdChart" height="100"></canvas></div>
            </div>
        </div>
    </div>

    <!-- Model Details Modal -->
    <div class="modal fade" id="modelDetailModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modelDetailTitle">Model Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3 text-end">
                <button id="retrainModelBtn" class="btn btn-sm btn-warning"><i class="fa-solid fa-rotate-right"></i> Retrain Model</button>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm" id="modelDetailTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Actual Price</th>
                            <th>Predicted Price</th>
                            <th>Change in Actual</th>
                            <th>Predicted Error</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Per-day Detail Modal (shows actual/predicted for a single date) -->
    <div class="modal fade" id="perDayModal" tabindex="-1" aria-hidden="true">
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
                        <tr><th style="width:40%">Date</th><td id="perDayDate">-</td></tr>
                        <tr><th>Actual Close</th><td id="perDayActual">-</td></tr>
                        <tr><th>Predicted</th><td id="perDayPred">-</td></tr>
                        <tr><th>Change in Actual</th><td id="perDayChange">-</td></tr>
                        <tr><th>Absolute Error</th><td id="perDayError">-</td></tr>
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

</div>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const baseApi = "http://127.0.0.1:5000";
let stockChart=null, smaChart=null, rsiChart=null, macdChart=null;
// Initialize main predictions table as DataTable after DOM ready
$(document).ready(function(){
    // create DataTable when predictions table becomes visible later
});

// API Status
function showApiStatus(text, ok){ $("#apiStatus").text(text).css('color', ok? 'green':'red'); }

// Load tickers
$(document).ready(()=>{
    $.getJSON(baseApi+"/api/tickers")
    .done(data=>{
        showApiStatus("API OK", true);
        if(data.tickers) data.tickers.forEach(t=>$("#tickerSelect").append(`<option value="${t}">${t}</option>`));
    }).fail(err=>{ console.error(err); showApiStatus("API unreachable", false); });
});

// Load data & Linear Regression
$("#loadDataBtn").click(async()=>{
    const ticker=$("#tickerSelect").val();
    if(!ticker){ alert("Select a ticker"); return; }
    $("#loader").show();
    try{
        const [dataRes,predRes] = await Promise.all([
            $.getJSON(`${baseApi}/api/data?ticker=${ticker}`),
            $.ajax({url:`${baseApi}/predict`, method:"POST", contentType:"application/json", data:JSON.stringify({ticker, horizon:30}), dataType:"json"})
        ]);

        if(dataRes.error||predRes.error){ alert(dataRes.error||predRes.error); return; }

        const rows = dataRes.rows.slice(-30); // last 30 days for chart
        // full history returned in predict (last ~120 rows) for actual lookup in modal
        const historyFull = (predRes.history && predRes.history.length) ? predRes.history : dataRes.rows;
        const lastPrice = rows[rows.length-1]?.Close||0;
        const predictions = (predRes.predictions || []).slice();

        // Prepare predicted arrays for each model
        const modelPreds = predictions.map(p=>({
            model: p.model_type || p.model || 'Unknown',
            preds: (p.predicted_price || p.predicted || []).map(Number),
            metrics: p.metrics || {},
            backtest: Array.isArray(p.backtest) ? p.backtest : (p.backtest || [])
        })).filter(m=>m.preds && m.preds.length);

        // Determine horizon (use first model length or 0)
        const horizon = modelPreds.length? modelPreds[0].preds.length : 0;

        // Compute overall min/max across all model predictions
        let allPredValues = [].concat(...modelPreds.map(m=>m.preds));
        const maxPred = allPredValues.length? Math.max(...allPredValues) : lastPrice;
        const minPred = allPredValues.length? Math.min(...allPredValues) : lastPrice;
        const action = maxPred>lastPrice?'Buy':(minPred<lastPrice?'Sell':'Hold');

        $("#kpiPrice").text(lastPrice.toFixed(2));
        $("#kpiMax").text(maxPred.toFixed(2));
        $("#kpiMin").text(minPred.toFixed(2));
        $("#kpiAction").html(`<span class="badge bg-${action=='Buy'?'success':(action=='Sell'?'danger':'secondary')}">${action}</span>`);
        $("#kpiSection").fadeIn(); $("#chartCard, #indicatorCard").fadeIn();

        // Chart: 30 days + predicted for each model
        const labels = rows.map(r=>new Date(r.Date).toLocaleDateString());
        const closes = rows.map(r=>Number(r.Close));
        const futureDates = [];
        if(horizon>0){
            for(let i=0;i<horizon;i++){
                let d=new Date(rows[rows.length-1].Date); d.setDate(d.getDate()+i+1);
                futureDates.push(d.toLocaleDateString());
            }
        }
        const stockLabels = labels.concat(futureDates);

        // base dataset for actual closes
        const datasets = [
            {label:'Close', data:closes.concat(Array(horizon).fill(null)), borderColor:'#111827', tension:0.2, pointRadius:2}
        ];

        // color palette for models
        const colors = ['#0d6efd','#20c997','#fd7e14','#6f42c1','#dc3545','#0dcaf0'];

        modelPreds.forEach((m, idx)=>{
            const color = colors[idx % colors.length];
            const dash = m.model.toLowerCase().includes('linear')? [6,4] : (m.model.toLowerCase().includes('randomforest')? [2,2] : []);
            datasets.push({
                label: m.model,
                data: Array(closes.length).fill(null).concat(m.preds),
                borderColor: color,
                borderDash: dash,
                tension:0.25,
                pointRadius:3
            });
        });

        if(stockChart) stockChart.destroy();
        stockChart = new Chart(document.getElementById('stockChart').getContext('2d'), {
            type:'line',
            data:{ labels: stockLabels, datasets: datasets },
            options:{responsive:true, interaction:{mode:'index',intersect:false}, plugins:{legend:{position:'top'}}, scales:{x:{display:true}, y:{display:true}}}
        });

        // Build predictions table (one latest predicted value per model) -- user can click row for details
        const $tbody = $('#predictionsTable tbody');
        $tbody.empty();
        modelPreds.forEach((m, idx)=>{
            const preds = m.preds;
            const latestPred = preds.length? preds[0] : null;
            const avg = preds.length? (preds.reduce((a,b)=>a+b,0)/preds.length) : 0;
            const changePerc = lastPrice? ((avg - lastPrice)/lastPrice*100) : 0;
            const metrics = m.metrics || {};
            const mapeDisplay = (metrics.mape!=null)? metrics.mape.toFixed(2) : '';
            const rmseDisplay = (metrics.rmse!=null)? metrics.rmse.toFixed(2) : '-';
            const row = $(`<tr class="model-row" data-idx="${idx}" style="cursor:pointer;"></tr>`);
            row.append(`<td>${m.model}</td>`);
            row.append(`<td>${latestPred!==null? latestPred.toFixed(2):'N/A'}</td>`);
            row.append(`<td>${avg.toFixed(2)}</td>`);
            row.append(`<td>${changePerc.toFixed(2)}%</td>`);
            row.append(`<td>${mapeDisplay}</td>`);
            row.append(`<td>${rmseDisplay}</td>`);
            row.on('click', ()=>{
                // show modal with per-day breakdown, using full history for actual lookup
                showModelDetailModal(m, idx, historyFull, predictions, lastPrice);
            });
            $tbody.append(row);
        });
        if(modelPreds.length) $('#predictionTableCard').fadeIn(); else $('#predictionTableCard').hide();

        // show chart after table is ready
        if(modelPreds.length) $('#chartCard').fadeIn(); else $('#chartCard').hide();

        // Indicators
        const sma = rows.map(r=>Number(r.SMA_14||0));
        const rsi = rows.map(r=>Number(r.RSI_14||0));
        const macd = rows.map(r=>Number(r.MACD||0));
        const signal = rows.map(r=>Number(r.Signal||0));

        // Helper: show modal with per-day comparison and retrain button
        function showModelDetailModal(modelObj, idx, historyRows, allPredictions, lastPriceVal){
            const modal = new bootstrap.Modal(document.getElementById('modelDetailModal'));
            $('#modelDetailTitle').text(modelObj.model + ' — Detailed Comparison');
            const $mbody = $('#modelDetailTable tbody'); $mbody.empty();
            const lastDate = new Date(historyRows[historyRows.length-1].Date);
            const horizonLocal = modelObj.preds.length;

            // Historical section: show last N days from CSV (Close values) so users always see actuals
            const HIST_DAYS = Math.min(30, historyRows.length);
            if(HIST_DAYS>0){
                $mbody.append(`<tr class="table-secondary"><td colspan="5"><strong>Historical (last ${HIST_DAYS} days)</strong></td></tr>`);
                const startIdx = Math.max(0, historyRows.length - HIST_DAYS);
                for(let j=startIdx;j<historyRows.length;j++){
                    const hr = historyRows[j];
                    const dateStr = hr.Date;
                    const actual = (hr.Close!=null && hr.Close!=='')? Number(hr.Close) : null;
                    // try to find a backtest prediction for this date
                    let predMatch = null;
                    if(Array.isArray(modelObj.backtest)) predMatch = modelObj.backtest.find(b=> (b.date||b['date'])===dateStr);
                    const pred = predMatch? Number(predMatch.predicted) : null;
                    const error = (actual!=null && pred!=null)? Math.abs(pred-actual).toFixed(2) : 'N/A';
                    let changeActual = '';
                    if(j>0){ const prev = Number(historyRows[j-1].Close); changeActual = (actual!=null? (actual - prev).toFixed(2) : ''); }
                    const rowHtml = `<tr class="drill-row" data-date="${dateStr}" data-actual="${actual!=null?actual:''}" data-pred="${pred!=null?pred:''}"><td>${dateStr}</td><td>${actual!=null? actual.toFixed(2): 'N/A'}</td><td>${pred!=null? pred.toFixed(2): 'N/A'}</td><td>${changeActual}</td><td>${error}</td></tr>`;
                    $mbody.append(rowHtml);
                }
            }

            // If backtest points are available from API, show them next (these have actuals and were model-generated)
            if(Array.isArray(modelObj.backtest) && modelObj.backtest.length){
                $mbody.append(`<tr class="table-secondary"><td colspan="5"><strong>Backtest (model vs actual)</strong></td></tr>`);
                modelObj.backtest.forEach(bt=>{
                    const dateStr = bt.date || bt['date'];
                    const actual = (bt.actual!=null)? Number(bt.actual) : null;
                    const pred = (bt.predicted!=null)? Number(bt.predicted) : null;
                    const predError = (actual!=null && pred!=null)? Math.abs(pred-actual).toFixed(2) : 'N/A';
                    // change in actual: find index in historyRows
                    let changeActual = '';
                    const idxHist = historyRows.findIndex(r=>r.Date===dateStr);
                    if(idxHist>0){ const prev = Number(historyRows[idxHist-1].Close); changeActual = (actual - prev).toFixed(2); }
                    const rowHtml = `<tr class="drill-row" data-date="${dateStr}" data-actual="${actual!=null?actual:''}" data-pred="${pred!=null?pred:''}"><td>${dateStr}</td><td>${actual!=null? actual.toFixed(2): 'N/A'}</td><td>${pred!=null? pred.toFixed(2): 'N/A'}</td><td>${changeActual}</td><td><span class="badge ${predError==='N/A'? 'badge-secondary': (Number(predError)>50? 'badge-error':'badge-ok')}">${predError}</span></td></tr>`;
                    $mbody.append(rowHtml);
                });
            }

            // Then show future forecast rows (no actuals expected unless CSV has that date)
            for(let i=0;i<horizonLocal;i++){
                const d = new Date(lastDate); d.setDate(d.getDate()+i+1);
                const dateStr = d.toISOString().substring(0,10);
                const actualObj = historyRows.find(r=>r.Date===dateStr);
                const actual = actualObj? Number(actualObj.Close) : null;
                let changeActual = '';
                if(actualObj){
                    const prevIndex = historyRows.findIndex(r=>r.Date===dateStr)-1;
                    if(prevIndex>=0){
                        const prev = Number(historyRows[prevIndex].Close);
                        changeActual = (actual - prev).toFixed(2);
                    }
                }
                const pred = Number(modelObj.preds[i]);
                const predError = (actual!=null)? Math.abs(pred - actual).toFixed(2) : 'N/A';
                const errorBadge = (actual!=null)? `<span class="badge ${Math.abs(Number(pred)-Number(actual))>50? 'badge-error':'badge-ok'}">${predError}</span>` : '<span class="badge badge-secondary">N/A</span>';
                const rowHtml = `<tr class="drill-row" data-date="${dateStr}" data-actual="${actual!=null?actual:''}" data-pred="${pred}"><td>${dateStr}</td><td>${actual!=null? actual.toFixed(2): 'N/A'}</td><td>${pred.toFixed(2)}</td><td>${changeActual}</td><td>${errorBadge}</td></tr>`;
                $mbody.append(rowHtml);
            }

            // populate inline detailed table as well
            $('#inlineModelName').text(modelObj.model);
            const $inline = $('#modelDetailInlineTable tbody'); $inline.empty();
            $('#modelDetailTable tbody tr.drill-row').each(function(){ $inline.append($(this).clone()); });
            // initialize DataTable for inline table (destroy if exists)
            try{ if($.fn.DataTable.isDataTable('#modelDetailInlineTable')){ $('#modelDetailInlineTable').DataTable().destroy(); } }catch(e){}
            $('#modelDetailInlineTable').DataTable({paging:true, pageLength:10, searching:true, info:true});
            $('#modelDetailInlineCard').fadeIn();

            // row click: open per-day detail modal
            $('#modelDetailTable tbody').off('click').on('click', 'tr.drill-row', function(){
                const $r = $(this);
                const date = $r.data('date');
                const actual = $r.data('actual');
                const pred = $r.data('pred');
                const error = (actual!='' && pred!='')? Math.abs(Number(pred)-Number(actual)).toFixed(2) : 'N/A';
                const prevIdx = historyRows.findIndex(r=>r.Date===date)-1;
                const prev = (prevIdx>=0)? Number(historyRows[prevIdx].Close) : null;
                const changeActual = (actual!='' && prev!=null)? (Number(actual)-prev).toFixed(2) : '';
                $('#perDayDate').text(date);
                $('#perDayActual').text(actual!=''? Number(actual).toFixed(2) : 'N/A');
                $('#perDayPred').text(pred!=''? Number(pred).toFixed(2) : 'N/A');
                $('#perDayChange').text(changeActual!=''? changeActual : 'N/A');
                $('#perDayError').text(error);
                const perModal = new bootstrap.Modal(document.getElementById('perDayModal'));
                perModal.show();
            });

            // retrain action (enqueue background retrain and poll job status)
            $('#retrainModelBtn').off('click').on('click', async ()=>{
                $('#retrainModelBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Queued');
                try{
                    const jobResp = await $.ajax({url:`${baseApi}/retrain`, method:'POST', contentType:'application/json', data: JSON.stringify({ticker: $('#tickerSelect').val(), model: modelObj.model, horizon: modelObj.preds.length}), dataType:'json'});
                    if(!jobResp || !jobResp.job_id){ throw new Error('failed to enqueue job'); }
                    const jobId = jobResp.job_id;
                    // poll for job status
                    const pollInterval = 2000; // 2s
                    const maxWait = 5 * 60 * 1000; // 5 minutes
                    const start = Date.now();
                    $('#retrainModelBtn').html('Retraining...');
                    const poller = setInterval(async ()=>{
                        try{
                            const status = await $.getJSON(`${baseApi}/job_status/${jobId}`);
                            if(status.status === 'done'){
                                clearInterval(poller);
                                // if result contains predictions, update UI
                                if(status.result && status.result.predictions){
                                    const newModel = status.result.predictions.find(p=> (p.model_type||p.model) === modelObj.model );
                                    if(newModel){
                                        modelObj.preds = (newModel.predicted_price||[]).map(Number);
                                        modelObj.metrics = newModel.metrics || modelObj.metrics;
                                    }
                                }
                                // refresh main UI
                                $('#retrainModelBtn').prop('disabled', false).html('<i class="fa-solid fa-rotate-right"></i> Retrain Model');
                                // re-run load to refresh tables and chart
                                $('#loadDataBtn').trigger('click');
                                return;
                            }else if(status.status === 'failed'){
                                clearInterval(poller);
                                alert('Retrain job failed: ' + (status.error || 'see server logs'));
                                $('#retrainModelBtn').prop('disabled', false).html('<i class="fa-solid fa-rotate-right"></i> Retrain Model');
                                return;
                            }
                            if(Date.now() - start > maxWait){
                                clearInterval(poller);
                                alert('Retrain taking too long. Please check job status later.');
                                $('#retrainModelBtn').prop('disabled', false).html('<i class="fa-solid fa-rotate-right"></i> Retrain Model');
                                return;
                            }
                        }catch(e){ console.error('Polling error', e); }
                    }, pollInterval);
                }catch(err){ console.error(err); alert('Failed to enqueue retrain job.'); $('#retrainModelBtn').prop('disabled', false).html('<i class="fa-solid fa-rotate-right"></i> Retrain Model'); }
            });

            modal.show();
        }

        if(smaChart) smaChart.destroy();
        smaChart = new Chart(document.getElementById('smaChart').getContext('2d'), {type:'line', data:{labels,datasets:[{label:'SMA(14)', data:sma, borderColor:'#6f42c1', tension:0.25, pointRadius:0}]}, options:{responsive:true, plugins:{legend:{display:false}}}});
        if(rsiChart) rsiChart.destroy();
        rsiChart = new Chart(document.getElementById('rsiChart').getContext('2d'), {type:'line', data:{labels,datasets:[{label:'RSI(14)', data:rsi, borderColor:'#fd7e14', tension:0.25, pointRadius:0}]}, options:{responsive:true, plugins:{legend:{display:false}}}});
        if(macdChart) macdChart.destroy();
        macdChart = new Chart(document.getElementById('macdChart').getContext('2d'), {type:'line', data:{labels,datasets:[{label:'MACD', data:macd, borderColor:'#0d6efd', tension:0.25, pointRadius:0},{label:'Signal', data:signal, borderColor:'#dc3545', tension:0.25, pointRadius:0}]}, options:{responsive:true}});
    }catch(err){console.error(err); alert("Failed to fetch data from API.");}
    finally{$("#loader").hide();}
});
</script>
</body>
</html>

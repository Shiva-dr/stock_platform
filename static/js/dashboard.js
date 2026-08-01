document.addEventListener("DOMContentLoaded", () => {
    const predictBtn = document.getElementById("predict-btn");
    const predictionResult = document.getElementById("prediction-result");
    const tickerInput = document.getElementById("ticker");

    // Load chart for default ticker
    predictAndRender("HDL");

    predictBtn.addEventListener("click", async () => {
        const ticker = tickerInput.value.trim();
        if (!ticker) return alert("Please enter a ticker");
        predictAndRender(ticker);
    });
});

async function predictAndRender(ticker) {
    const container = document.getElementById("chart-container");
    container.innerHTML = '<div class="text-muted">Loading...</div>';

    try {
        const res = await fetch('/predict', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ticker })
        });
        const payload = await res.json();
        if (payload.error) {
            container.innerHTML = `<span class="text-danger">${payload.error}</span>`;
            return;
        }

        renderPlotWithPredictions(payload.history, payload.predictions, ticker);

        // Show model summary under prediction-result
        const predictionResult = document.getElementById('prediction-result');
        predictionResult.innerHTML = '';
        payload.predictions.forEach(p => {
            if (p.predicted) {
                const latest = p.predicted[0];
                const avg = (p.predicted.reduce((a,b)=>a+b,0)/p.predicted.length).toFixed(2);
                const el = document.createElement('div');
                el.className = 'small text-secondary mb-1';
                el.innerHTML = `<strong>${p.model_type}</strong>: next ${p.predicted.length} days avg <span class="text-success">${avg}</span>`;
                predictionResult.appendChild(el);
            } else if (p.error) {
                const el = document.createElement('div');
                el.className = 'small text-danger mb-1';
                el.textContent = `${p.model_type} error: ${p.error}`;
                predictionResult.appendChild(el);
            }
        });

    } catch (e) {
        document.getElementById('chart-container').innerHTML = `<span class="text-danger">Request failed: ${e.message}</span>`;
    }
}

function renderPlotWithPredictions(history, predictions, ticker) {
    // history: array of {Date, Close}
    const dates = history.map(r => r.Date);
    const close = history.map(r => r.Close);

    // base trace: historical close as line
    const histTrace = {
        x: dates,
        y: close,
        mode: 'lines',
        name: `${ticker} Close`,
        line: { color: '#2c3e50', width: 2 }
    };

    const traces = [histTrace];

    // colors for models
    const colors = ['#e67e22','#27ae60','#2980b9','#8e44ad','#c0392b'];

    // add each model's forecast as a dashed line starting from last historical date
    predictions.forEach((p, idx) => {
        if (!p.predicted) return;
        const modelDates = p.dates || [];
        const modelVals = p.predicted || [];
        traces.push({
            x: modelDates,
            y: modelVals,
            mode: 'lines+markers',
            name: p.model_type,
            line: { dash: 'dash', color: colors[idx % colors.length], width: 2 },
            marker: { size: 6 }
        });
    });

    const layout = {
        title: `${ticker} — Historical and Model Forecasts`,
        margin: { t: 40 },
        legend: { orientation: 'h', y: -0.2 },
        xaxis: { tickangle: -45 },
        transition: { duration: 700, easing: 'cubic-in-out' },
        hovermode: 'x unified'
    };

    Plotly.newPlot('chart-container', traces, layout, {responsive:true});

    // subtle reveal animation: fade-in each model trace sequentially
    let startIdx = 1;
    const revealInterval = 500;
    const total = traces.length;
    const interval = setInterval(() => {
        if (startIdx >= total) {
            clearInterval(interval);
            return;
        }
        const update = { 'line.opacity': 1, 'marker.opacity': 1 };
        Plotly.restyle('chart-container', update, [startIdx]);
        startIdx++;
    }, revealInterval);

    // initially hide model traces (set opacity to 0) then reveal
    for (let i=1;i<traces.length;i++) {
        Plotly.restyle('chart-container', {'line.opacity': 0, 'marker.opacity':0}, [i]);
    }
}

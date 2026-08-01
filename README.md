<<<<<<< HEAD
# Stock Platform

This repository contains a development stock prediction platform with a Flask backend that runs multiple forecasting models and a PHP/Bootstrap frontend dashboard (user and admin views). The app supports multi-model predictions, backtesting, CSV-based actuals (Close price), an asynchronous retrain job flow, and a drillable UI for per-model and per-day inspection.

---

## Contents
- backend/ - Flask API, model orchestration, CSV reading, caching, job queue files
- php/ - Frontend dashboard and admin UI (Bootstrap, jQuery, Chart.js, DataTables)
- backend/data/ - CSV files for each ticker (Date, Close, ...)
- backend/outputs/ - cached prediction summaries and job files

---

## Requirements
- Windows (development environment tested on Windows)
- PHP & web server (XAMPP recommended) serving the `php/` folder (e.g., place project in `C:/xampp/htdocs/stock_platform`)
- Python 3.8+ (for backend)
- Optional Python packages for some models: prophet, statsmodels, xgboost

---

## Backend setup
1. Create and activate a Python virtual environment in `backend/`:

```powershell
cd backend
python -m venv .venv
.\.venv\Scripts\Activate.ps1    # PowerShell
pip install -U pip
pip install -r requirements.txt   # if present
```

2. If `requirements.txt` is not present, install core dependencies:

```powershell
pip install flask pandas scikit-learn numpy matplotlib flask-cors
# Optional for some models:
pip install prophet statsmodels xgboost
```

3. Start the Flask API (development):

```powershell
cd backend
python api.py
# or: set FLASK_APP=api.py; flask run
```

The API binds to 127.0.0.1:5000 by default in development. See `backend/api.py` for configuration details.

---

## Frontend (PHP) setup
1. Ensure PHP/XAMPP is installed and the project is placed under the webroot (e.g., `C:/xampp/htdocs/stock_platform`).
2. Start Apache via XAMPP. Open the dashboard in a browser:

```
http://localhost/stock_platform/php/dashboard.php
```

3. The admin view is at:

```
http://localhost/stock_platform/php/auth/admin.php
```

---

## How it works (overview)
- The backend reads CSVs from `backend/data/<TICKER>.csv` (expects a `Date` and `Close` column). Close values are used as the ground-truth actual prices.
- POST `/predict` accepts {ticker, horizon} and returns:
  - history: recent CSV rows (Date, Close)
  - predictions: array of per-model objects with predicted series, backtest points, and metrics (MAPE, RMSE)
  - last_known_price, horizon
- POST `/retrain` enqueues a background job which recomputes models and writes a job file under `backend/outputs/jobs/<job_id>.json`.
- GET `/job_status/<job_id>` returns job status and results when complete.

---

## Dashboard features
- Table-first layout: summary table with one row per model (latest prediction, error metrics).
- Click a model to open a modal with three sections: Historical (CSV Close), Backtest (per-fold points), Forecast (future predictions). Each row is drillable to a compact per-day modal.
- Inline detailed table (DataTables) appears when a model is clicked — searchable, sortable, and paginated.
- Retrain button enqueues an async job and the UI polls `/job_status/<job_id>` to refresh when done.

---

## Troubleshooting
- If the API is unreachable:
  - Confirm Flask is running (check `backend/api.py` logs).
  - Confirm CORS / origin settings if PHP is served from a different host/port.
- If Actual (Close) is N/A in the UI:
  - Inspect `backend/data/<TICKER>.csv` for expected `Date` and `Close` columns and consistent date formats (YYYY-MM-DD recommended).
- Long-running models (Prophet, XGBoost) can take time to compute. Use the retrain job and be patient — the UI polls for completion.

---

## Tests & development notes
- The background job implementation is in-process (threads) for development only. For production, consider Celery/RQ or OS-level job workers with durable queues.
- Caching lives in `backend/outputs/` and has a TTL. Clearing cache is part of retrain.

---

## Pushing this project to GitHub
To push the repository to GitHub, the usual workflow is:

```powershell
cd C:\xampp\htdocs\stock_platform
git init
git add .
git commit -m "Initial commit: stock platform" --author="Your Name <you@example.com>"
# Add remote (HTTPS or SSH)
# Example HTTPS: https://github.com/yourname/repo.git
# Example SSH: git@github.com:yourname/repo.git
git remote add origin <REPO_URL>
# If using HTTPS, configure credentials (personal access token) when prompted
git push -u origin main
```

Note on authentication: If pushing via HTTPS, you will need a Personal Access Token (PAT) for authentication — do not share the PAT in insecure channels. If pushing via SSH, ensure the machine has the SSH key added to your GitHub account.

---

## License
Add an appropriate license file (e.g., MIT) if you plan to make this public.

---

If you'd like, I can:
- Initialize a git repository here, commit the current code with an appropriate commit message (including the required Co-authored-by trailer), and push to the GitHub repository you provide.
- Or, provide step-by-step guidance and commands so you can push from your machine.

Please tell me how you'd like to proceed.
=======
# stock_platform
>>>>>>> 5ddb3db7bbe37add5d2690b0e70d1e551d6cbaf1

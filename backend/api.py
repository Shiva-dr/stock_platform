import os
import json
from flask import Flask, request, jsonify
from flask_cors import CORS
import pandas as pd
import numpy as np
from sklearn.linear_model import LinearRegression

# Optional model imports
try:
    from prophet import Prophet
    HAS_PROPHET = True
except Exception:
    HAS_PROPHET = False

try:
    from statsmodels.tsa.arima.model import ARIMA
    HAS_ARIMA = True
except Exception:
    HAS_ARIMA = False

try:
    from sklearn.ensemble import RandomForestRegressor, GradientBoostingRegressor
    HAS_SKLEARN = True
except Exception:
    HAS_SKLEARN = False

try:
    import xgboost as xgb
    HAS_XGB = True
except Exception:
    HAS_XGB = False

BASE_DIR = os.path.dirname(__file__)
DATA_DIR = os.path.join(BASE_DIR, 'data')
OUTPUT_DIR = os.path.join(BASE_DIR, 'outputs')
JOB_DIR = os.path.join(OUTPUT_DIR, 'jobs')
# ensure data, outputs and jobs dirs exist
os.makedirs(DATA_DIR, exist_ok=True)
os.makedirs(OUTPUT_DIR, exist_ok=True)
os.makedirs(JOB_DIR, exist_ok=True)
import re
import threading
import uuid
import time
TICKER_RE = re.compile(r'^[A-Z0-9_\-]+$')

app = Flask(__name__)
# Restrict CORS to localhost origins only for safety
from flask_cors import CORS as _CORS
_allowed = ["http://localhost", "http://127.0.0.1", "http://localhost:80", "http://127.0.0.1:80"]
_CORS(app, resources={r"/api/*": {"origins": _allowed}, r"/predict": {"origins": _allowed}, r"/retrain": {"origins": _allowed}, r"/job_status/*": {"origins": _allowed}})

# configure logging
import logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s [%(levelname)s] %(message)s')
app.logger = logging.getLogger('stock_api')
app.logger.setLevel(logging.INFO)


def _read_csv_for_ticker(ticker):
    # sanitize ticker to avoid path traversal and invalid input
    if not isinstance(ticker, str):
        return None
    ticker = ticker.strip().upper()
    if not TICKER_RE.match(ticker):
        app.logger.warning(f"Invalid ticker format: {ticker}")
        return None
    path = os.path.join(DATA_DIR, f"{ticker}.csv")
    if not os.path.exists(path):
        app.logger.info(f"Ticker CSV not found: {path}")
        return None
    try:
        df = pd.read_csv(path)
    except Exception as e:
        app.logger.exception(f"Failed to read CSV for {ticker}: {e}")
        return None
    # normalize columns
    cols = [c.strip() for c in df.columns]
    df.columns = cols
    # guess date and close columns
    date_col = next((c for c in cols if 'date' in c.lower()), cols[0])
    close_col = next((c for c in cols if 'close' in c.lower() or 'last' in c.lower()), cols[-1])
    df = df.rename(columns={date_col: 'Date', close_col: 'Close'})
    df['Date'] = pd.to_datetime(df['Date'], errors='coerce')
    df['Close'] = pd.to_numeric(df['Close'], errors='coerce')
    df = df.dropna(subset=['Date','Close']).sort_values('Date').reset_index(drop=True)
    return df


def sma(series, window=14):
    return series.rolling(window).mean()


def rsi(series, window=14):
    delta = series.diff()
    up = delta.clip(lower=0)
    down = -1 * delta.clip(upper=0)
    ma_up = up.ewm(alpha=1/window, adjust=False).mean()
    ma_down = down.ewm(alpha=1/window, adjust=False).mean()
    rs = ma_up / (ma_down.replace(0, np.nan))
    rsi = 100 - (100 / (1 + rs))
    return rsi.fillna(50)


def macd(series, slow=26, fast=12, signal=9):
    ema_fast = series.ewm(span=fast, adjust=False).mean()
    ema_slow = series.ewm(span=slow, adjust=False).mean()
    macd_line = ema_fast - ema_slow
    signal_line = macd_line.ewm(span=signal, adjust=False).mean()
    return macd_line, signal_line


@app.route('/api/tickers')
def api_tickers():
    files = [f for f in os.listdir(DATA_DIR) if f.lower().endswith('.csv')]
    tickers = [os.path.splitext(f)[0].upper() for f in files]
    return jsonify({'tickers': tickers})


@app.route('/health')
def health():
    return jsonify({'status': 'ok', 'tickers': len([f for f in os.listdir(DATA_DIR) if f.lower().endswith('.csv')])})


@app.route('/api/data')
def api_data():
    ticker = request.args.get('ticker')
    if not ticker:
        return jsonify({'error': 'ticker required'}), 400
    ticker = ticker.upper()
    df = _read_csv_for_ticker(ticker)
    if df is None or df.empty:
        return jsonify({'error': 'ticker data not found'}), 404

    # compute indicators
    df['SMA_14'] = sma(df['Close'], 14)
    df['RSI_14'] = rsi(df['Close'], 14)
    macd_line, signal_line = macd(df['Close'])
    df['MACD'] = macd_line
    df['Signal'] = signal_line

    # convert to simple JSON rows
    rows = df[['Date','Close','SMA_14','RSI_14','MACD','Signal']].copy()
    rows['Date'] = rows['Date'].dt.strftime('%Y-%m-%d')
    rows = rows.fillna('').to_dict(orient='records')
    return jsonify({'rows': rows})


@app.route('/predict', methods=['POST'])
def api_predict():
    data = request.get_json(force=True) or {}
    ticker = (data.get('ticker') or '').upper()
    if not ticker:
        return jsonify({'error': 'ticker required'}), 400

    # basic sanitize
    if not TICKER_RE.match(ticker):
        return jsonify({'error': 'invalid ticker format'}), 400

    # parse optional horizon early so cache behavior can respect request
    try:
        req_h = int(data.get('horizon')) if data.get('horizon') is not None else None
    except Exception:
        req_h = None

    # helper: compute backtest metrics (mape, rmse) using simple rolling-origin one-step forecasts
    def compute_backtest_metrics(series, model_name, df_for_dates=None):
        from math import sqrt
        series = pd.Series(series).astype(float).reset_index(drop=True)
        n = len(series)
        metrics = {'mape': None, 'rmse': None, 'n_folds': 0}
        # minimal training sizes per model
        min_train_map = {
            'Linear Regression': 10,
            'Prophet': 30,
            'ARIMA': 30,
            'RandomForest': 40,
            'GradientBoosting': 40,
            'XGBoost': 40
        }
        min_train = min_train_map.get(model_name, 30)
        if n <= min_train + 2:
            return metrics
        max_folds = 5
        # number of folds we can perform
        available = n - min_train
        k = min(max_folds, available)
        if k <= 0:
            return metrics
        errors = []
        sq_errors = []
        # use last k points as test indices: test at indices n-k .. n-1
        for test_idx in range(n - k, n):
            train = series.iloc[:test_idx].reset_index(drop=True)
            true_val = float(series.iloc[test_idx])
            if len(train) < min_train:
                continue
            try:
                if model_name == 'Linear Regression':
                    X = np.arange(len(train)).reshape(-1,1)
                    y = train.values
                    lr = LinearRegression(); lr.fit(X,y)
                    pred = float(lr.predict(np.array([[len(train)]]))[0])
                elif model_name == 'Prophet' and HAS_PROPHET:
                    if df_for_dates is not None and len(df_for_dates)>=len(train):
                        t_dates = df_for_dates.iloc[:len(train)].copy()
                        tdf = pd.DataFrame({'ds': t_dates, 'y': train.values})
                    else:
                        tdf = pd.DataFrame({'ds': pd.date_range(end=pd.Timestamp('today'), periods=len(train)), 'y': train.values})
                    m = Prophet(daily_seasonality=True)
                    m.fit(tdf)
                    future = m.make_future_dataframe(periods=1)
                    p = m.predict(future)
                    pred = float(p['yhat'].iloc[-1])
                elif model_name == 'ARIMA' and HAS_ARIMA:
                    model = ARIMA(train, order=(5,1,0))
                    fit = model.fit()
                    pred = float(fit.forecast(steps=1)[0])
                elif model_name in ('RandomForest','GradientBoosting','XGBoost') and (HAS_SKLEARN or HAS_XGB):
                    lags = 14
                    if len(train) <= lags + 1:
                        continue
                    df_l = pd.DataFrame({'y': train})
                    for i in range(1, lags+1):
                        df_l[f'lag_{i}'] = df_l['y'].shift(i)
                    df_l = df_l.dropna()
                    if df_l.empty:
                        continue
                    X_train = df_l.drop(columns=['y']).values
                    y_train = df_l['y'].values
                    if model_name == 'RandomForest':
                        from sklearn.ensemble import RandomForestRegressor
                        mdl = RandomForestRegressor(n_estimators=100, random_state=42)
                    elif model_name == 'GradientBoosting':
                        from sklearn.ensemble import GradientBoostingRegressor
                        mdl = GradientBoostingRegressor(n_estimators=100, random_state=42)
                    else:
                        import xgboost as xgb
                        mdl = xgb.XGBRegressor(n_estimators=100, random_state=42, verbosity=0)
                    mdl.fit(X_train, y_train)
                    recent = list(train.iloc[-lags:].values)
                    x_in = np.array(recent[-lags:]).reshape(1,-1)
                    pred = float(mdl.predict(x_in)[0])
                else:
                    continue
                err = abs((pred - true_val) / (true_val if true_val != 0 else 1)) * 100
                errors.append(err)
                sq_errors.append((pred - true_val)**2)
            except Exception:
                continue
        if errors:
            mape = sum(errors)/len(errors)
            rmse = (sum(sq_errors)/len(sq_errors))**0.5
            metrics = {'mape': round(mape,3), 'rmse': round(rmse,3), 'n_folds': len(errors)}
        return metrics

    # check cache
    cache_file = os.path.join(OUTPUT_DIR, f"{ticker}_summary.json")
    try:
        if os.path.exists(cache_file):
            # use cache if updated within last 6 hours
            mtime = os.path.getmtime(cache_file)
            import time
            if time.time() - mtime < 6 * 3600:
                with open(cache_file, 'r') as f:
                    cached = json.load(f)
                # Determine cached horizon (try 'horizon' field, otherwise infer from predictions length)
                cached_h = None
                if isinstance(cached, dict):
                    if 'horizon' in cached:
                        try:
                            cached_h = int(cached.get('horizon'))
                        except Exception:
                            cached_h = None
                    elif isinstance(cached.get('predictions'), list) and len(cached.get('predictions'))>0:
                        try:
                            cached_h = int(len(cached['predictions'][0].get('predicted_price', [])))
                        except Exception:
                            cached_h = None
                    elif isinstance(cached.get('models'), dict) and len(cached.get('models'))>0:
                        # infer from first model series
                        try:
                            first_key = next(iter(cached['models']))
                            cached_h = int(len(cached['models'][first_key]))
                        except Exception:
                            cached_h = None
                # If a specific horizon is requested and cached horizon differs, skip cache
                if req_h is not None and cached_h is not None and int(cached_h) != int(req_h):
                    app.logger.info(f"Cached horizon {cached_h} != requested {req_h}; recomputing for {ticker}")
                else:
                    app.logger.info(f"Serving cached predictions for {ticker}")
                    # Normalize older summary.json shape if needed
                    if 'predictions' in cached:
                        # ensure metrics present; if not, compute and attach them
                        try:
                            df_cached = _read_csv_for_ticker(ticker)
                            if df_cached is not None:
                                last_vals = df_cached['Close'].astype(float).tolist()
                                updated = False
                                for p in cached.get('predictions', []):
                                    if not p.get('metrics'):
                                        try:
                                            m = compute_backtest_metrics(last_vals, p.get('model_type') or p.get('model') or 'unknown', df_cached['Date'])
                                            p['metrics'] = m
                                            if m.get('mape') is not None:
                                                p['accuracy_percent'] = max(0, min(100, round(100 - m['mape'],2)))
                                            else:
                                                p['accuracy_percent'] = None
                                            updated = True
                                        except Exception:
                                            pass
                                if updated:
                                    try:
                                        with open(cache_file, 'w') as f:
                                            json.dump(cached, f, indent=2, default=str)
                                    except Exception:
                                        pass
                        except Exception:
                            pass
                        return jsonify(cached)
                    if 'models' in cached:
                        # convert models dict -> predictions list
                        preds = []
                        for mname, series in cached.get('models', {}).items():
                            # series may be list of {ds,yhat}
                            vals = []
                            for row in series:
                                if isinstance(row, dict) and 'yhat' in row:
                                    try:
                                        vals.append(round(float(row['yhat']),2))
                                    except Exception:
                                        pass
                                else:
                                    try:
                                        vals.append(round(float(row),2))
                                    except Exception:
                                        pass
                            preds.append({'model_type': mname, 'predicted_price': vals})
                        resp = {
                            'history': cached.get('history', []),
                            'predictions': preds,
                            'last_known_price': cached.get('actual_price') or cached.get('last_known_price'),
                            'horizon': len(preds[0]['predicted_price']) if preds else 0
                        }
                        # compute metrics for these preds if possible
                        try:
                            df_cached = _read_csv_for_ticker(ticker)
                            if df_cached is not None:
                                last_vals = df_cached['Close'].astype(float).tolist()
                                for p in resp['predictions']:
                                    try:
                                        m = compute_backtest_metrics(last_vals, p.get('model_type') or p.get('model') or 'unknown', df_cached['Date'])
                                        p['metrics'] = m
                                        if m.get('mape') is not None:
                                            p['accuracy_percent'] = max(0, min(100, round(100 - m['mape'],2)))
                                        else:
                                            p['accuracy_percent'] = None
                                    except Exception:
                                        p['metrics'] = {'mape':None,'rmse':None,'n_folds':0}
                        except Exception:
                            pass
                        return jsonify(resp)
                    # fallback: return raw cached
                    return jsonify(cached)
    except Exception as e:
        app.logger.exception(f"Cache read failed for {ticker}: {e}")

    df = _read_csv_for_ticker(ticker)
    if df is None or df.empty:
        return jsonify({'error': 'ticker data not found'}), 404

    # prepare series
    closes = df['Close'].astype(float).reset_index(drop=True)
    if len(closes) < 6:
        return jsonify({'error': 'not enough data for prediction (need >=6 points)'}), 400

    # Accept optional horizon from request, cap to 30 days
    # determine horizon (respect previously-parsed req_h); default 7, cap 30
    if req_h is None:
        try:
            req_h = int(data.get('horizon', 7))
        except Exception:
            req_h = 7
    else:
        try:
            req_h = int(req_h)
        except Exception:
            req_h = 7
    horizon = max(1, min(30, req_h))
    last_date = df['Date'].iloc[-1]
    future_dates = [(last_date + pd.Timedelta(days=i)).strftime('%Y-%m-%d') for i in range(1, horizon+1)]

    results = []

    # Linear Regression (baseline)
    try:
        X = np.arange(len(closes)).reshape(-1,1)
        y = closes.values
        lr = LinearRegression()
        lr.fit(X, y)
        future_X = np.arange(len(closes), len(closes)+horizon).reshape(-1,1)
        lr_preds = [round(float(x),2) for x in lr.predict(future_X).tolist()]
        results.append({'model_type':'Linear Regression','dates':future_dates,'predicted':lr_preds})
    except Exception as e:
        results.append({'model_type':'Linear Regression','error':str(e)})

    # Prophet
    if HAS_PROPHET:
        try:
            mdf = df[['Date','Close']].rename(columns={'Date':'ds','Close':'y'}).copy()
            m = Prophet(daily_seasonality=True)
            m.fit(mdf)
            future = m.make_future_dataframe(periods=horizon)
            f = m.predict(future).tail(horizon)
            preds = [round(float(x),2) for x in f['yhat'].tolist()]
            dates = [d.strftime('%Y-%m-%d') for d in f['ds']]
            results.append({'model_type':'Prophet','dates':dates,'predicted':preds})
        except Exception as e:
            results.append({'model_type':'Prophet','error':str(e)})

    # ARIMA
    if HAS_ARIMA:
        try:
            ser = closes.copy()
            model = ARIMA(ser, order=(5,1,0))
            fit = model.fit()
            fc = fit.forecast(steps=horizon)
            preds = [round(float(x),2) for x in fc.tolist()]
            results.append({'model_type':'ARIMA','dates':future_dates,'predicted':preds})
        except Exception as e:
            results.append({'model_type':'ARIMA','error':str(e)})

    # Tree models (RandomForest, GradientBoosting, XGBoost)
    def make_lagged(series, lags=14):
        ser = pd.Series(series)
        df_l = pd.DataFrame({'y': ser})
        for i in range(1, lags+1):
            df_l[f'lag_{i}'] = df_l['y'].shift(i)
        df_l = df_l.dropna()
        return df_l

    if HAS_SKLEARN or HAS_XGB:
        try:
            lagged = make_lagged(closes, lags=14)
            if not lagged.empty and len(lagged) > 20:
                X = lagged.drop(columns=['y']).values
                y = lagged['y'].values
                # RandomForest
                try:
                    from sklearn.ensemble import RandomForestRegressor
                    rf = RandomForestRegressor(n_estimators=200, random_state=42)
                    rf.fit(X, y)
                    # recursive forecast
                    recent = list(closes.iloc[-14:].values)
                    preds = []
                    for _ in range(horizon):
                        x = np.array(recent[-14:]).reshape(1,-1)
                        yhat = rf.predict(x)[0]
                        preds.append(round(float(yhat),2))
                        recent.append(yhat)
                    results.append({'model_type':'RandomForest','dates':future_dates,'predicted':preds})
                except Exception as e:
                    results.append({'model_type':'RandomForest','error':str(e)})

                # GradientBoosting
                try:
                    from sklearn.ensemble import GradientBoostingRegressor
                    gb = GradientBoostingRegressor(n_estimators=200, random_state=42)
                    gb.fit(X, y)
                    recent = list(closes.iloc[-14:].values)
                    preds = []
                    for _ in range(horizon):
                        x = np.array(recent[-14:]).reshape(1,-1)
                        yhat = gb.predict(x)[0]
                        preds.append(round(float(yhat),2))
                        recent.append(yhat)
                    results.append({'model_type':'GradientBoosting','dates':future_dates,'predicted':preds})
                except Exception as e:
                    results.append({'model_type':'GradientBoosting','error':str(e)})

                # XGBoost if available
                if HAS_XGB:
                    try:
                        xg = xgb.XGBRegressor(n_estimators=200, random_state=42, verbosity=0)
                        xg.fit(X, y)
                        recent = list(closes.iloc[-14:].values)
                        preds = []
                        for _ in range(horizon):
                            x = np.array(recent[-14:]).reshape(1,-1)
                            yhat = xg.predict(x)[0]
                            preds.append(round(float(yhat),2))
                            recent.append(yhat)
                        results.append({'model_type':'XGBoost','dates':future_dates,'predicted':preds})
                    except Exception as e:
                        results.append({'model_type':'XGBoost','error':str(e)})
            else:
                # not enough data for tree models
                pass
        except Exception as e:
            results.append({'model_type':'tree_models','error':str(e)})

    # return historical tail plus predictions
    hist = df[['Date','Close']].tail(120).copy()
    hist['Date'] = hist['Date'].dt.strftime('%Y-%m-%d')
    hist_rows = hist.to_dict(orient='records')

    # Normalize results to the shape the dashboard expects: predictions -> [{model_type, predicted_price: [...]}, ...]
    predictions_out = []
    for r in results:
        model = r.get('model_type') or r.get('model') or 'unknown'
        preds = []
        if isinstance(r.get('predicted'), list):
            preds = r.get('predicted')
        elif isinstance(r.get('predicted_price'), list):
            preds = r.get('predicted_price')
        elif isinstance(r.get('predicted_price'), (str, int, float)):
            preds = [r.get('predicted_price')]
        else:
            # model failed or has no predictions
            preds = []
        # ensure numeric list
        preds = [round(float(x), 2) for x in preds] if preds else []
        predictions_out.append({'model_type': model, 'predicted_price': preds})

    # ---- Backtest / compute simple accuracy metrics ----
    def compute_backtest_metrics(series, model_name, dates=None):
        # returns dict with metrics and a list of backtest points (date,predicted,actual,error)
        from math import sqrt
        series = pd.Series(series).astype(float).reset_index(drop=True)
        n = len(series)
        metrics = {'mape': None, 'rmse': None, 'n_folds': 0}
        backtest_points = []
        # minimal training sizes per model
        min_train_map = {
            'Linear Regression': 10,
            'Prophet': 30,
            'ARIMA': 30,
            'RandomForest': 40,
            'GradientBoosting': 40,
            'XGBoost': 40
        }
        min_train = min_train_map.get(model_name, 30)
        if n <= min_train + 2:
            return {'metrics': metrics, 'backtest': backtest_points}
        max_folds = 5
        # number of folds we can perform
        available = n - min_train
        k = min(max_folds, available)
        if k <= 0:
            return {'metrics': metrics, 'backtest': backtest_points}
        errors = []
        sq_errors = []
        # use last k points as test indices: test at indices n-k .. n-1
        for test_idx in range(n - k, n):
            train = series.iloc[:test_idx].reset_index(drop=True)
            true_val = float(series.iloc[test_idx])
            if len(train) < min_train:
                continue
            try:
                if model_name == 'Linear Regression':
                    X = np.arange(len(train)).reshape(-1,1)
                    y = train.values
                    lr = LinearRegression(); lr.fit(X,y)
                    pred = float(lr.predict(np.array([[len(train)]]))[0])
                elif model_name == 'Prophet' and HAS_PROPHET:
                    # create artificial dates based on original df spacing
                    # use the last len(train) dates from the original df if available
                    t_dates = df['Date'].iloc[:len(train)].copy()
                    if len(t_dates.dropna()) == len(train):
                        tdf = pd.DataFrame({'ds': t_dates, 'y': train.values})
                    else:
                        tdf = pd.DataFrame({'ds': pd.date_range(end=pd.Timestamp('today'), periods=len(train)), 'y': train.values})
                    m = Prophet(daily_seasonality=True)
                    m.fit(tdf)
                    future = m.make_future_dataframe(periods=1)
                    p = m.predict(future)
                    pred = float(p['yhat'].iloc[-1])
                elif model_name == 'ARIMA' and HAS_ARIMA:
                    model = ARIMA(train, order=(5,1,0))
                    fit = model.fit()
                    pred = float(fit.forecast(steps=1)[0])
                elif model_name in ('RandomForest','GradientBoosting','XGBoost') and (HAS_SKLEARN or HAS_XGB):
                    lags = 14
                    if len(train) <= lags + 1:
                        continue
                    df_l = pd.DataFrame({'y': train})
                    for i in range(1, lags+1):
                        df_l[f'lag_{i}'] = df_l['y'].shift(i)
                    df_l = df_l.dropna()
                    if df_l.empty:
                        continue
                    X_train = df_l.drop(columns=['y']).values
                    y_train = df_l['y'].values
                    if model_name == 'RandomForest':
                        from sklearn.ensemble import RandomForestRegressor
                        mdl = RandomForestRegressor(n_estimators=100, random_state=42)
                    elif model_name == 'GradientBoosting':
                        from sklearn.ensemble import GradientBoostingRegressor
                        mdl = GradientBoostingRegressor(n_estimators=100, random_state=42)
                    else:
                        import xgboost as xgb
                        mdl = xgb.XGBRegressor(n_estimators=100, random_state=42, verbosity=0)
                    mdl.fit(X_train, y_train)
                    recent = list(train.iloc[-lags:].values)
                    x_in = np.array(recent[-lags:]).reshape(1,-1)
                    pred = float(mdl.predict(x_in)[0])
                else:
                    continue
                # capture error
                err = abs((pred - true_val) / (true_val if true_val != 0 else 1)) * 100
                errors.append(err)
                sq_errors.append((pred - true_val)**2)
                # compute date for this test index if dates provided
                date_str = None
                try:
                    if dates is not None and len(dates) > test_idx:
                        dval = dates.iloc[test_idx]
                        try:
                            date_str = pd.to_datetime(dval).strftime('%Y-%m-%d')
                        except Exception:
                            date_str = str(dval)
                except Exception:
                    date_str = None
                backtest_points.append({'date': date_str, 'predicted': round(float(pred),2), 'actual': round(float(true_val),2), 'error': round(abs(pred - true_val),2)})
            except Exception:
                continue
        if errors:
            mape = sum(errors)/len(errors)
            rmse = (sum(sq_errors)/len(sq_errors))**0.5
            metrics = {'mape': round(mape,3), 'rmse': round(rmse,3), 'n_folds': len(errors)}
        return {'metrics': metrics, 'backtest': backtest_points}

    # compute metrics and attach backtest points per model (only if enough history)
    last_vals = df['Close'].astype(float).tolist()
    for p in predictions_out:
        try:
            res = compute_backtest_metrics(last_vals, p['model_type'], df['Date'])
            metrics = res.get('metrics', {'mape': None, 'rmse': None, 'n_folds': 0})
            backtest = res.get('backtest', [])
        except Exception:
            metrics = {'mape': None, 'rmse': None, 'n_folds': 0}
            backtest = []
        p['metrics'] = metrics
        p['backtest'] = backtest
        # allow quick accuracy percent for frontend convenience
        if metrics.get('mape') is not None:
            try:
                p['accuracy_percent'] = max(0, min(100, round(100 - metrics['mape'],2)))
            except Exception:
                p['accuracy_percent'] = None
        else:
            p['accuracy_percent'] = None

    # Build final response
    last_known_price = None
    try:
        last_known_price = float(df['Close'].iloc[-1])
    except Exception:
        last_known_price = None

    response = {
        'history': hist_rows,
        'predictions': predictions_out,
        'last_known_price': last_known_price,
        'horizon': horizon
    }
    # write cache for faster subsequent requests
    try:
        with open(cache_file, 'w') as f:
            json.dump(response, f, indent=2, default=str)
        app.logger.info(f"Wrote cache for {ticker} -> {cache_file}")
    except Exception as e:
        app.logger.exception(f"Failed to write cache for {ticker}: {e}")

    return jsonify(response)


@app.route('/retrain', methods=['POST'])
def api_retrain():
    """Asynchronous retrain: enqueue a job that will call the local /predict endpoint and update job file.
    Returns job_id immediately. Use /job_status/<job_id> to poll status/result."""
    data = request.get_json(force=True) or {}
    ticker = (data.get('ticker') or '').upper()
    horizon = int(data.get('horizon') or 7)
    if not ticker or not TICKER_RE.match(ticker):
        return jsonify({'error':'ticker required'}), 400

    job_id = uuid.uuid4().hex
    job_file = os.path.join(JOB_DIR, f"{job_id}.json")
    job_meta = {'id': job_id, 'ticker': ticker, 'status': 'pending', 'created_at': time.time(), 'horizon': horizon}
    try:
        with open(job_file, 'w') as jf:
            json.dump(job_meta, jf)
    except Exception as e:
        app.logger.exception(f"Failed to write job file {job_file}: {e}")
        return jsonify({'error': 'failed to create job'}), 500

    def background_job(jid, jfpath, tk, hz):
        try:
            # remove cache first so /predict recomputes
            cache_f = os.path.join(OUTPUT_DIR, f"{tk}_summary.json")
            try:
                if os.path.exists(cache_f): os.remove(cache_f)
            except Exception:
                pass
            import requests
            resp = requests.post('http://127.0.0.1:5000/predict', json={'ticker': tk, 'horizon': hz}, timeout=600)
            result = None
            if resp.status_code == 200:
                try:
                    result = resp.json()
                except Exception:
                    result = {'error': 'invalid json from predict'}
            else:
                result = {'error': f'predict failed: {resp.status_code}'}
            job_update = {'id': jid, 'ticker': tk, 'status': 'done' if resp.status_code==200 else 'failed', 'finished_at': time.time(), 'result': result}
            with open(jfpath, 'w') as jf2:
                json.dump(job_update, jf2)
        except Exception as e:
            app.logger.exception(f"Background retrain job {jid} failed: {e}")
            try:
                with open(jfpath, 'w') as jf3:
                    json.dump({'id': jid, 'ticker': tk, 'status': 'failed', 'error': str(e), 'finished_at': time.time()}, jf3)
            except Exception:
                pass

    t = threading.Thread(target=background_job, args=(job_id, job_file, ticker, horizon), daemon=True)
    t.start()

    return jsonify({'job_id': job_id, 'status': 'pending'})


@app.route('/job_status/<job_id>')
def job_status(job_id):
    jf = os.path.join(JOB_DIR, f"{job_id}.json")
    if not os.path.exists(jf):
        return jsonify({'error': 'job not found'}), 404
    try:
        with open(jf, 'r') as f:
            data = json.load(f)
        return jsonify(data)
    except Exception as e:
        app.logger.exception(f"Failed reading job file {jf}: {e}")
        return jsonify({'error': 'failed to read job file'}), 500


if __name__ == '__main__':
    # When run directly, start the server on 127.0.0.1:5000 (threaded to allow background retrain HTTP requests)
    app.run(host='127.0.0.1', port=5000, threaded=True)

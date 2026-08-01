import os
import json
import pandas as pd
import numpy as np
from datetime import timedelta

# Optional imports (detect availability)
try:
    from prophet import Prophet
    HAS_PROPHET = True
except Exception:
    HAS_PROPHET = False

try:
    from statsmodels.tsa.arima.model import ARIMA
    HAS_STATS = True
except Exception:
    HAS_STATS = False

try:
    from sklearn.ensemble import RandomForestRegressor, GradientBoostingRegressor
    from sklearn.model_selection import train_test_split
    HAS_SKLEARN = True
except Exception:
    HAS_SKLEARN = False

try:
    import xgboost as xgb
    HAS_XGB = True
except Exception:
    HAS_XGB = False

try:
    import yfinance as yf
    HAS_YF = True
except Exception:
    HAS_YF = False


DATA_DIR = os.path.join(os.path.dirname(__file__), 'data')
OUTPUT_DIR = os.path.join(os.path.dirname(__file__), 'outputs')
os.makedirs(OUTPUT_DIR, exist_ok=True)

FORECAST_DAYS = 7
LAGS = 14  # number of lag features for tree models


def load_csv(path):
    df = pd.read_csv(path)
    cols = [c.strip().lower() for c in df.columns]
    df.columns = cols
    date_col = next((c for c in cols if 'date' in c), 'date')
    close_col = next((c for c in cols if 'close' in c or 'adj close' in c or 'last' in c), cols[-1])
    df = df.rename(columns={date_col: 'ds', close_col: 'y'})
    df['ds'] = pd.to_datetime(df['ds'], errors='coerce')
    df['y'] = pd.to_numeric(df['y'], errors='coerce')
    df = df.dropna(subset=['ds', 'y']).sort_values('ds')
    return df


def make_lag_features(series, lags=LAGS):
    df = pd.DataFrame({'y': series})
    for i in range(1, lags + 1):
        df[f'lag_{i}'] = df['y'].shift(i)
    df = df.dropna()
    return df


def forecast_prophet(df):
    if not HAS_PROPHET:
        return None
    m = Prophet(daily_seasonality=True)
    m.fit(df[['ds','y']])
    future = m.make_future_dataframe(periods=FORECAST_DAYS)
    f = m.predict(future)
    res = f[['ds','yhat']].tail(FORECAST_DAYS).rename(columns={'yhat':'yhat'})
    res['yhat_lower'] = f['yhat_lower'].tail(FORECAST_DAYS).values
    res['yhat_upper'] = f['yhat_upper'].tail(FORECAST_DAYS).values
    return res


def forecast_arima(series):
    if not HAS_STATS:
        return None
    try:
        # use a simple ARIMA(5,1,0) as robust default
        model = ARIMA(series, order=(5,1,0))
        model_fit = model.fit()
        forecast = model_fit.forecast(steps=FORECAST_DAYS)
        idx = pd.date_range(start=series.index[-1] + pd.Timedelta(days=1), periods=FORECAST_DAYS, freq='D')
        res = pd.DataFrame({'ds': idx, 'yhat': forecast.values})
        return res
    except Exception as e:
        print('ARIMA failed:', e)
        return None


def forecast_tree_models(series):
    if not HAS_SKLEARN:
        return {}
    results = {}
    # Prepare supervised dataset using lag features
    lagged = make_lag_features(series)
    X = lagged.drop(columns=['y']).values
    y = lagged['y'].values
    if len(y) < 30:
        return {}
    # Split just to follow sklearn pattern (not strictly necessary)
    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.1, shuffle=False)

    # RandomForest
    try:
        rf = RandomForestRegressor(n_estimators=200, random_state=42)
        rf.fit(X_train, y_train)
        results['RandomForest'] = recursive_forecast(rf, series, LAGS)
    except Exception as e:
        print('RandomForest failed:', e)

    # GradientBoosting
    try:
        gb = GradientBoostingRegressor(n_estimators=200, random_state=42)
        gb.fit(X_train, y_train)
        results['GradientBoosting'] = recursive_forecast(gb, series, LAGS)
    except Exception as e:
        print('GradientBoosting failed:', e)

    # XGBoost (if available)
    if HAS_XGB:
        try:
            xg = xgb.XGBRegressor(n_estimators=200, random_state=42, verbosity=0)
            xg.fit(X_train, y_train)
            results['XGBoost'] = recursive_forecast(xg, series, LAGS)
        except Exception as e:
            print('XGBoost failed:', e)

    return results


def recursive_forecast(model, series, lags):
    # series: pandas Series with DateTimeIndex
    recent = list(series.iloc[-lags:].values)
    preds = []
    for _ in range(FORECAST_DAYS):
        x = np.array(recent[-lags:]).reshape(1, -1)
        yhat = model.predict(x)[0]
        preds.append(yhat)
        recent.append(yhat)
    idx = pd.date_range(start=series.index[-1] + pd.Timedelta(days=1), periods=FORECAST_DAYS, freq='D')
    return pd.DataFrame({'ds': idx, 'yhat': preds})


def get_latest_price_from_yf(ticker):
    if not HAS_YF:
        return None
    try:
        t = yf.Ticker(ticker)
        info = t.history(period='1d')
        if not info.empty:
            return float(info['Close'].iloc[-1])
    except Exception:
        return None
    return None


def process_file(path):
    ticker = os.path.splitext(os.path.basename(path))[0]
    print(f"Processing {ticker}")
    df = load_csv(path)
    if df is None or df.empty:
        print('No data or failed to parse', path)
        return
    # Ensure daily frequency index
    series = df.set_index('ds')['y'].asfreq('D')
    # Forward-fill small gaps
    series = series.fillna(method='ffill')

    summary = {'ticker': ticker, 'models': {}, 'actual_price': None}

    # Latest actual price: try yfinance then fallback to last CSV close
    latest_price = None
    if HAS_YF:
        latest_price = get_latest_price_from_yf(ticker)
    if latest_price is None:
        latest_price = float(df['y'].iloc[-1])
    summary['actual_price'] = latest_price

    # Prophet
    if HAS_PROPHET:
        try:
            print('Running Prophet for', ticker)
            prophet_df = df[['ds','y']].copy()
            res = forecast_prophet(prophet_df)
            if res is not None:
                out_csv = os.path.join(OUTPUT_DIR, f'{ticker}_Prophet.csv')
                res.to_csv(out_csv, index=False)
                summary['models']['Prophet'] = res[['ds','yhat']].to_dict(orient='records')
        except Exception as e:
            print('Prophet error:', e)

    # ARIMA
    if HAS_STATS:
        try:
            print('Running ARIMA for', ticker)
            arima_res = forecast_arima(series.dropna())
            if arima_res is not None:
                out_csv = os.path.join(OUTPUT_DIR, f'{ticker}_ARIMA.csv')
                arima_res.to_csv(out_csv, index=False)
                summary['models']['ARIMA'] = arima_res[['ds','yhat']].to_dict(orient='records')
        except Exception as e:
            print('ARIMA error:', e)

    # Tree models
    if HAS_SKLEARN or HAS_XGB:
        try:
            print('Running tree models for', ticker)
            tree_results = forecast_tree_models(series.dropna())
            for name, res in tree_results.items():
                out_csv = os.path.join(OUTPUT_DIR, f'{ticker}_{name}.csv')
                res.to_csv(out_csv, index=False)
                summary['models'][name] = res[['ds','yhat']].to_dict(orient='records')
        except Exception as e:
            print('Tree models error:', e)

    # Save summary JSON
    out_summary = os.path.join(OUTPUT_DIR, f'{ticker}_summary.json')
    with open(out_summary, 'w') as f:
        json.dump(summary, f, default=str, indent=2)
    print(f'Wrote summary for {ticker} -> {out_summary}')


if __name__ == '__main__':
    files = [os.path.join(DATA_DIR, f) for f in os.listdir(DATA_DIR) if f.lower().endswith('.csv')]
    if not files:
        print('No CSV files found in', DATA_DIR)
    for p in files:
        try:
            process_file(p)
        except Exception as e:
            print('Failed processing', p, e)
    print('Done')

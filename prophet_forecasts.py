import os
import pandas as pd
import matplotlib.pyplot as plt
from prophet import Prophet
from datetime import timedelta

# ---------------- Config ----------------
TICKERS = ['HDL', 'NABIL', 'NTC', 'NIMB', 'SHIVM', 'NRIC']
DATA_DIR = 'data'
OUTPUT_DIR = 'outputs'
FORECAST_DAYS = 5  # Match app.py prediction days
os.makedirs(OUTPUT_DIR, exist_ok=True)

# ---------------- Utilities ----------------
def load_csv(ticker):
    path = os.path.join(DATA_DIR, f"{ticker}.csv")
    if not os.path.exists(path):
        return None
    df = pd.read_csv(path)
    df.columns = [c.strip().lower() for c in df.columns]
    date_col = next((c for c in df.columns if 'date' in c), 'date')
    close_col = next((c for c in df.columns if 'close' in c), 'close')
    df.rename(columns={date_col:'ds', close_col:'y'}, inplace=True)
    df['ds'] = pd.to_datetime(df['ds'], errors='coerce')
    df['y'] = pd.to_numeric(df['y'], errors='coerce')
    df = df.dropna(subset=['ds', 'y']).sort_values('ds')
    if df.empty:
        return None
    return df

# ---------------- Forecast & Plot ----------------
def forecast_prophet(df, ticker, days=FORECAST_DAYS):
    model = Prophet(daily_seasonality=True)
    model.fit(df)
    future = model.make_future_dataframe(periods=days)
    forecast = model.predict(future)
    return forecast.tail(days)

def save_forecast_plot(df, forecast, ticker):
    plt.figure(figsize=(12,6))
    ax = plt.gca()

    # Confidence interval
    ax.fill_between(forecast['ds'], forecast['yhat_lower'], forecast['yhat_upper'], color='lightblue', alpha=0.5)

    # Forecast line
    ax.plot(forecast['ds'], forecast['yhat'], color='blue', linewidth=2, label='Forecast')

    # Historical data
    ax.plot(df['ds'], df['y'], color='black', label='Historical')
    ax.scatter(df['ds'].iloc[-1], df['y'].iloc[-1], color='black', s=35, edgecolor='white', linewidth=0.7)

    # Titles and labels
    ax.set_title(f"{ticker} Price Forecast", fontsize=16)
    ax.set_ylabel('Price')
    ax.set_xlabel('Date')
    ax.grid(True, linestyle='--', alpha=0.25)
    ax.legend(frameon=False)
    plt.xticks(rotation=25)

    # Save
    out_path = os.path.join(OUTPUT_DIR, f'forecast_{ticker}.png')
    plt.tight_layout()
    plt.savefig(out_path, dpi=150)
    plt.close()
    print(f"Saved forecast for {ticker}: {out_path}")
    return out_path

# ---------------- Process all tickers ----------------
def process_ticker(ticker):
    df = load_csv(ticker)
    if df is None or len(df) < 10:
        print(f"[SKIP] Not enough data for {ticker}")
        return
    forecast = forecast_prophet(df, ticker)
    save_forecast_plot(df, forecast, ticker)

if __name__ == "__main__":
    for t in TICKERS:
        process_ticker(t)

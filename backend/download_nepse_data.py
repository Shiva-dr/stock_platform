import os
import requests
import pandas as pd
from datetime import datetime, timedelta

DATA_DIR = 'data'
if not os.path.exists(DATA_DIR):
    os.makedirs(DATA_DIR)

tickers = ['HDL', 'NIMB', 'NTC', 'NABIL', 'SHIVM', 'NRIC']

BASE_URL = "https://nepsealpha.com/api/historical_prices"

# Fake headers to mimic browser requests
HEADERS = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
                  "AppleWebKit/537.36 (KHTML, like Gecko) "
                  "Chrome/115.0.0.0 Safari/537.36",
    "Accept": "application/json, text/javascript, */*; q=0.01",
    "Referer": "https://nepsealpha.com/",
    "X-Requested-With": "XMLHttpRequest"
}

def fetch_and_save_csv(ticker):
    end_date = datetime.today()
    start_date = end_date - timedelta(days=90)

    params = {
        'ticker': ticker,
        'period': 'daily',
        'start': start_date.strftime('%Y-%m-%d'),
        'end': end_date.strftime('%Y-%m-%d'),
    }

    print(f"Fetching data for {ticker} from {params['start']} to {params['end']}...")

    try:
        response = requests.get(BASE_URL, params=params, headers=HEADERS)
        response.raise_for_status()
        data = response.json()

        if 'data' not in data or not data['data']:
            print(f"No data found for {ticker}")
            return

        df = pd.DataFrame(data['data'])

        if 'date' in df.columns:
            df['date'] = pd.to_datetime(df['date'])
            df.sort_values('date', inplace=True)

        filename = os.path.join(DATA_DIR, f"{ticker}.csv")
        df.to_csv(filename, index=False)
        print(f"Saved {len(df)} rows for {ticker} to {filename}")

    except Exception as e:
        print(f"Failed to fetch/save data for {ticker}: {e}")

if __name__ == '__main__':
    for ticker in tickers:
        fetch_and_save_csv(ticker)

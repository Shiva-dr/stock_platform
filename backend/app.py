import os
import pandas as pd
import numpy as np
import mysql.connector
from datetime import datetime, timedelta
from sklearn.linear_model import LinearRegression
import traceback

# ----------------- Configuration -----------------
DATA_DIR = 'data'
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'stock_platform'
}

# ----------------- Database Connection -----------------
def get_db_connection():
    return mysql.connector.connect(**DB_CONFIG)

# ----------------- Utilities -----------------
def clean_numeric(series):
    return pd.to_numeric(series.astype(str).str.replace(',', ''), errors='coerce')

def sanitize_ticker(filename):
    return os.path.splitext(filename)[0].strip().upper()

def linear_regression_predict(closes):
    X = np.arange(len(closes)).reshape(-1,1)
    y = closes
    model = LinearRegression()
    model.fit(X, y)
    next_val = model.predict(np.array([[len(closes)]]))[0]
    return next_val

# ----------------- Save CSV to Database -----------------
def save_csv_to_db(file_path, ticker):
    try:
        df = pd.read_csv(file_path)
        df.columns = [c.strip().lower() for c in df.columns]
        date_col = next((c for c in df.columns if 'date' in c), 'date')
        close_col = next((c for c in df.columns if 'close' in c), 'close')
        df.rename(columns={date_col:'date', close_col:'close'}, inplace=True)
        df['date'] = pd.to_datetime(df['date'], errors='coerce')
        df['close'] = clean_numeric(df['close'])
        df = df.dropna(subset=['close']).sort_values('date')
        
        conn = get_db_connection()
        cursor = conn.cursor()
        for _, row in df.iterrows():
            cursor.execute(
                "INSERT INTO historical_data (ticker, date, close) VALUES (%s,%s,%s) "
                "ON DUPLICATE KEY UPDATE close=%s",
                (ticker, row['date'].strftime('%Y-%m-%d'), row['close'], row['close'])
            )
        conn.commit()
        cursor.close()
        conn.close()
        return df
    except Exception as e:
        print(f"Error saving CSV {ticker} to DB:", e)
        traceback.print_exc()
        return None

# ----------------- Predict last 30 days -----------------
def predict_last_30_days(df, ticker):
    df = df.sort_values('date').reset_index(drop=True)
    if len(df) < 31:
        print("Not enough data for 30-day predictions")
        return

    predictions = []
    for i in range(len(df)-1-30, len(df)-1):  # last 30 days
        closes = df['close'].iloc[:i+1].values
        predicted = linear_regression_predict(closes)
        actual_next = df['close'].iloc[i+1]
        accuracy = 100 - abs((predicted-actual_next)/actual_next)*100 if actual_next!=0 else 0
        predictions.append((
            ticker,
            df['date'].iloc[i].strftime('%Y-%m-%d'),
            df['close'].iloc[i],
            round(predicted,2),
            round(accuracy,2)
        ))
    
    # Save to prediction table
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        for t, date, actual, predicted, acc in predictions:
            cursor.execute(
                "INSERT INTO prediction (ticker, date, actual_close, predicted_close, accuracy) "
                "VALUES (%s,%s,%s,%s,%s) "
                "ON DUPLICATE KEY UPDATE predicted_close=%s, accuracy=%s",
                (t, date, actual, predicted, acc, predicted, acc)
            )
        conn.commit()
        cursor.close()
        conn.close()
        print(f"Saved {len(predictions)} predictions for {ticker}")
    except Exception as e:
        print("Error saving predictions:", e)
        traceback.print_exc()

# ----------------- Main Process -----------------
def main():
    files = [f for f in os.listdir(DATA_DIR) if f.endswith('.csv')]
    for file in files:
        ticker = sanitize_ticker(file)
        file_path = os.path.join(DATA_DIR, file)
        df = save_csv_to_db(file_path, ticker)
        if df is not None:
            predict_last_30_days(df, ticker)

if __name__ == "__main__":
    main()

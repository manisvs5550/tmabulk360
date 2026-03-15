from flask import Flask, render_template, request
import pandas as pd
import sqlite3
import os
from datetime import timedelta

app = Flask(__name__)
app.secret_key = "your_secret_key"

DB_PATH = "ship_movement.sqlite3"

# Ensure uploads folder exists
if not os.path.exists("uploads"):
    os.makedirs("uploads")

# SQLite connection
def get_db_connection():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn

# Import AIS Data
def import_ais_data(file_path):
    try:
        conn = get_db_connection()
        cursor = conn.cursor()

        df = pd.read_excel(file_path)

        df.columns = df.columns.str.strip().str.lower()
        column_map = {
            'date and time': 'Date and Time',
            'longitude': 'Longitude',
            'latitude': 'Latitude',
            'current direction': 'Current Direction',
            'current speed': 'Current Speed',
            'wave height': 'Wave Height',
            'wave period': 'Wave Period',
            'sig wave height': 'Sig Wave Height',
            'swell direction': 'Swell Direction',
            'swell height': 'Swell Height',
            'swell period': 'Swell Period',
            'wind direction 10m': 'Wind Direction 10m',
            'wind speed 10m': 'Wind Speed 10m',
            'gps distance': 'GPS Distance',
            'gps sog': 'GPS SOG',
            'heading': 'Heading',
            'current factor': 'Current Factor',
            'weather factor': 'Weather Factor',
            'dt performance speed': 'DT Performance Speed'
        }

        df = df[list(column_map.keys())]
        df = df.rename(columns=column_map)
        df = df[df['Current Direction'] > 0]
        df['Date and Time'] = pd.to_datetime(df['Date and Time'], errors='coerce', dayfirst=True)
        df = df.dropna(subset=['Date and Time'])

        cursor.execute("DROP TABLE IF EXISTS AISData")
        cursor.execute("""
            CREATE TABLE AISData (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                `Date and Time` TEXT,
                `Longitude` REAL,
                `Latitude` REAL,
                `Current Direction` REAL,
                `Current Speed` REAL,
                `Wave Height` REAL,
                `Wave Period` REAL,
                `Sig Wave Height` REAL,
                `Swell Direction` REAL,
                `Swell Height` REAL,
                `Swell Period` REAL,
                `Wind Direction 10m` REAL,
                `Wind Speed 10m` REAL,
                `GPS Distance` REAL,
                `GPS SOG` REAL,
                `Heading` REAL,
                `Current Factor` REAL,
                `Weather Factor` REAL,
                `DT Performance Speed` REAL
            )
        """)

        for _, row in df.iterrows():
            row_data = [
                None if pd.isna(x) else x.isoformat() if isinstance(x, pd.Timestamp) else x
                for x in row
            ]
            cursor.execute("""
                INSERT INTO AISData
                (`Date and Time`, `Longitude`, `Latitude`, `Current Direction`, `Current Speed`,
                 `Wave Height`, `Wave Period`, `Sig Wave Height`, `Swell Direction`, `Swell Height`,
                 `Swell Period`, `Wind Direction 10m`, `Wind Speed 10m`, `GPS Distance`, `GPS SOG`,
                 `Heading`, `Current Factor`, `Weather Factor`, `DT Performance Speed`)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            """, row_data)

        conn.commit()
        conn.close()
        return f"✅ Imported {len(df)} rows into AISData"

    except Exception as e:
        return f"❌ Error importing AIS Data: {e}"

# Import VesonData
def import_weather_data(file_path):
    try:
        conn = get_db_connection()
        cursor = conn.cursor()

        df = pd.read_excel(file_path)
        df.columns = df.columns.str.strip().str.lower()
        column_map = {
            'date time': 'Date Time',
            'steaming hours': 'Steaming Hours',
            'observed distance': 'Observed Distance',
            'cp ordered speed': 'CP Ordered Speed',
            'projected speed': 'Projected Speed',
            'average rpm': 'Average RPM',
            'lsf consumption': 'LSF Consumption',
            'lgo consumption': 'LGO Consumption',
            'vessel condition': 'Vessel Condition',
            'sea height': 'Sea Height'
        }
        df = df[list(column_map.keys())]
        df = df.rename(columns=column_map)
        df['Date Time'] = pd.to_datetime(df['Date Time'], errors='coerce', dayfirst=True)
        df = df.dropna(subset=['Date Time'])
        df['Total Consumption'] = df['LSF Consumption'].fillna(0) + df['LGO Consumption'].fillna(0)

        cursor.execute("DROP TABLE IF EXISTS VesonData")
        cursor.execute("""
            CREATE TABLE VesonData (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                `Date Time` TEXT,
                `Steaming Hours` REAL,
                `Observed Distance` REAL,
                `CP Ordered Speed` REAL,
                `Projected Speed` REAL,
                `Average RPM` REAL,
                `LSF Consumption` REAL,
                `LGO Consumption` REAL,
                `Vessel Condition` TEXT,
                `Sea Height` REAL,
                `Total Consumption` REAL,
                `hind_cast_avg_cf` REAL,
                `Current adjusted +-distance` REAL,
                `Hindcast-Max SWH` REAL,
                `Hindcast-Max Wind Speed` REAL,
                `Hind Cast-Good weather` TEXT
            )
        """)

        for _, row in df.iterrows():
            row_data = [
                None if pd.isna(x) else x.isoformat() if isinstance(x, pd.Timestamp) else x
                for x in row
            ] + [None]*5  # additional columns
            cursor.execute("""
                INSERT INTO VesonData
                (`Date Time`, `Steaming Hours`, `Observed Distance`, `CP Ordered Speed`, `Projected Speed`,
                 `Average RPM`, `LSF Consumption`, `LGO Consumption`, `Vessel Condition`, `Sea Height`,
                 `Total Consumption`, `hind_cast_avg_cf`, `Current adjusted +-distance`,
                 `Hindcast-Max SWH`, `Hindcast-Max Wind Speed`, `Hind Cast-Good weather`)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            """, row_data)

        conn.commit()
        conn.close()
        return f"✅ Imported {len(df)} rows into VesonData"

    except Exception as e:
        return f"❌ Error importing Weather Data: {e}"

# Calculate hindcast values
def calculate_hindcast_and_adjusted_distance():
    try:
        conn = get_db_connection()
        cursor = conn.cursor()

        cursor.execute("SELECT id, `Date Time`, `Steaming Hours` FROM VesonData")
        veson_rows = cursor.fetchall()

        for row in veson_rows:
            veson_id, date_time_str, steaming_hours = row
            avg_cf, adjusted_distance = None, None
            hindcast_max_swh, hindcast_max_wind, good_weather = None, None, None

            if date_time_str and steaming_hours:
                date_time = pd.to_datetime(date_time_str)
                start_time = date_time - timedelta(hours=float(steaming_hours))
                end_time = date_time

                # Hind cast avg CF
                cursor.execute("""
                    SELECT AVG(`Current Factor`) FROM AISData
                    WHERE `Date and Time` BETWEEN ? AND ?
                """, (start_time.isoformat(), end_time.isoformat()))
                avg_cf = cursor.fetchone()[0]
                if avg_cf is not None:
                    avg_cf = round(avg_cf, 2)
                    adjusted_distance = round(avg_cf * float(steaming_hours), 2)

                # Hindcast-Max SWH
                cursor.execute("""
                    SELECT MAX(`Sig Wave Height`) FROM AISData
                    WHERE `Date and Time` BETWEEN ? AND ?
                """, (start_time.isoformat(), end_time.isoformat()))
                hindcast_max_swh = cursor.fetchone()[0]
                if hindcast_max_swh is not None:
                    hindcast_max_swh = round(hindcast_max_swh, 2)

                # Hindcast-Max Wind Speed
                cursor.execute("""
                    SELECT MAX(`Wind Speed 10m`) FROM AISData
                    WHERE `Date and Time` BETWEEN ? AND ?
                """, (start_time.isoformat(), end_time.isoformat()))
                hindcast_max_wind = cursor.fetchone()[0]
                if hindcast_max_wind is not None:
                    hindcast_max_wind = round(hindcast_max_wind, 2)

                # Good weather
                if hindcast_max_swh is not None and hindcast_max_wind is not None:
                    good_weather = "Yes" if (hindcast_max_swh < 1.501 and hindcast_max_wind < 16) else "No"

            cursor.execute("""
                UPDATE VesonData
                SET hind_cast_avg_cf = ?,
                    `Current adjusted +-distance` = ?,
                    `Hindcast-Max SWH` = ?,
                    `Hindcast-Max Wind Speed` = ?,
                    `Hind Cast-Good weather` = ?
                WHERE id = ?
            """, (avg_cf, adjusted_distance, hindcast_max_swh, hindcast_max_wind, good_weather, veson_id))

        conn.commit()
        conn.close()
        print("✅ Calculations completed.")

    except Exception as e:
        print(f"❌ Error calculating values: {e}")

# Flask route
@app.route("/", methods=["GET", "POST"])
def upload_files():
    ais_message = None
    weather_message = None

    if request.method == "POST":
        if "ais_file" in request.files and request.files["ais_file"].filename != "":
            ais_file = request.files["ais_file"]
            path = os.path.join("uploads", ais_file.filename)
            ais_file.save(path)
            ais_message = import_ais_data(path)

        if "weather_file" in request.files and request.files["weather_file"].filename != "":
            weather_file = request.files["weather_file"]
            path = os.path.join("uploads", weather_file.filename)
            weather_file.save(path)
            weather_message = import_weather_data(path)
            calculate_hindcast_and_adjusted_distance()

    return render_template("upload.html", ais_message=ais_message, weather_message=weather_message)

# Generate performance summary
@app.route("/generate_summary", methods=["POST"])
def generate_summary():
    try:
        conn = get_db_connection()
        cursor = conn.cursor()

        cursor.execute("""
            SELECT DISTINCT `Vessel Condition` FROM VesonData
            WHERE `Vessel Condition` IS NOT NULL AND `Vessel Condition` != ''
        """)
        vessel_conditions = [row[0] for row in cursor.fetchall()]
        summary = {}

        for vc in vessel_conditions:
            summary[vc] = {}
            for speed in [13.5, 12.0]:
                for weather_type, weather_filter in [("all_weather", None), ("good_weather", "Yes")]:
                    key = f"{speed}_{weather_type}"

                    conditions = ["`Vessel Condition` = ?", "`CP Ordered Speed` = ?"]
                    params = [vc, speed]

                    if weather_filter:
                        conditions.append("`Hind Cast-Good weather` = ?")
                        params.append(weather_filter)

                    where_clause = " AND ".join(conditions)

                    cursor.execute(f"SELECT SUM(`Steaming Hours`) FROM VesonData WHERE {where_clause}", params)
                    total_time = cursor.fetchone()[0] or 0

                    cursor.execute(f"SELECT SUM(`Observed Distance`) FROM VesonData WHERE {where_clause}", params)
                    total_distance = cursor.fetchone()[0] or 0

                    cursor.execute(f"SELECT SUM(`Total Consumption`) FROM VesonData WHERE {where_clause}", params)
                    total_consumption = cursor.fetchone()[0] or 0

                    avg_speed = round(total_distance / total_time, 2) if total_time > 0 else 0
                    avg_consumption_per_day = round((total_consumption / total_time) * 24, 2) if total_time > 0 else 0

                    summary[vc][key] = {
                        "total_time": round(total_time, 2),
                        "total_distance": round(total_distance, 2),
                        "total_consumption": round(total_consumption, 2),
                        "avg_speed": avg_speed,
                        "avg_consumption_per_day": avg_consumption_per_day
                    }

        conn.close()
        return render_template("summary.html", summary=summary)

    except Exception as e:
        return f"❌ Error generating summary: {e}"

if __name__ == "__main__":
    app.run(debug=True)

import os
from datetime import date, datetime, timedelta
from flask import Flask, render_template, request, redirect, url_for, send_from_directory, session
from openpyxl import load_workbook
import sqlite3

app = Flask(__name__)
app.secret_key = os.environ.get("SECRET_KEY", os.urandom(32))

DB_PATH = "ship_movement.sqlite3"

# Ensure uploads folder exists
if not os.path.exists("uploads"):
    os.makedirs("uploads")

# SQLite connection
def get_db_connection():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn

def _parse_datetime(value):
    """Parse a cell value into a datetime, return None on failure."""
    if isinstance(value, datetime):
        return value
    if value is None:
        return None
    for fmt in ("%d/%m/%Y %H:%M", "%d-%m-%Y %H:%M", "%Y-%m-%d %H:%M:%S", "%Y-%m-%dT%H:%M:%S"):
        try:
            return datetime.strptime(str(value).strip(), fmt)
        except (ValueError, TypeError):
            continue
    return None


def _read_excel_rows(file_path, expected_columns):
    """Read an xlsx file, return (header_index_map, data_rows)."""
    wb = load_workbook(file_path, read_only=True, data_only=True)
    ws = wb.active
    rows = ws.iter_rows(values_only=True)
    raw_header = next(rows)
    header = [str(h).strip().lower() if h else '' for h in raw_header]
    col_map = {}
    for expected in expected_columns:
        if expected in header:
            col_map[expected] = header.index(expected)
    data = list(rows)
    wb.close()
    return col_map, data


# Import AIS Data
def import_ais_data(file_path):
    try:
        conn = get_db_connection()
        cursor = conn.cursor()

        expected = [
            'date and time', 'longitude', 'latitude', 'current direction',
            'current speed', 'wave height', 'wave period', 'sig wave height',
            'swell direction', 'swell height', 'swell period',
            'wind direction 10m', 'wind speed 10m', 'gps distance', 'gps sog',
            'heading', 'current factor', 'weather factor', 'dt performance speed'
        ]
        db_columns = [
            'Date and Time', 'Longitude', 'Latitude', 'Current Direction',
            'Current Speed', 'Wave Height', 'Wave Period', 'Sig Wave Height',
            'Swell Direction', 'Swell Height', 'Swell Period',
            'Wind Direction 10m', 'Wind Speed 10m', 'GPS Distance', 'GPS SOG',
            'Heading', 'Current Factor', 'Weather Factor', 'DT Performance Speed'
        ]

        col_map, data = _read_excel_rows(file_path, expected)

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

        count = 0
        for row in data:
            values = []
            for col_name in expected:
                idx = col_map.get(col_name)
                values.append(row[idx] if idx is not None else None)

            # Filter: Current Direction > 0
            cur_dir = values[expected.index('current direction')]
            if cur_dir is None or float(cur_dir) <= 0:
                continue

            # Parse datetime
            dt = _parse_datetime(values[0])
            if dt is None:
                continue
            values[0] = dt.isoformat()

            # Convert numeric cells: None stays None
            row_data = [values[0]] + [
                float(v) if v is not None else None for v in values[1:]
            ]

            cursor.execute("""
                INSERT INTO AISData
                (`Date and Time`, `Longitude`, `Latitude`, `Current Direction`, `Current Speed`,
                 `Wave Height`, `Wave Period`, `Sig Wave Height`, `Swell Direction`, `Swell Height`,
                 `Swell Period`, `Wind Direction 10m`, `Wind Speed 10m`, `GPS Distance`, `GPS SOG`,
                 `Heading`, `Current Factor`, `Weather Factor`, `DT Performance Speed`)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            """, row_data)
            count += 1

        conn.commit()
        conn.close()
        return f"✅ Imported {count} rows into AISData"

    except Exception as e:
        return f"❌ Error importing AIS Data: {e}"

# Import VesonData
def import_weather_data(file_path):
    try:
        conn = get_db_connection()
        cursor = conn.cursor()

        expected = [
            'date time', 'steaming hours', 'observed distance', 'cp ordered speed',
            'projected speed', 'average rpm', 'lsf consumption', 'lgo consumption',
            'vessel condition', 'sea height'
        ]

        col_map, data = _read_excel_rows(file_path, expected)

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

        count = 0
        for row in data:
            values = []
            for col_name in expected:
                idx = col_map.get(col_name)
                values.append(row[idx] if idx is not None else None)

            # Parse datetime
            dt = _parse_datetime(values[0])
            if dt is None:
                continue

            lsf = float(values[6]) if values[6] is not None else 0
            lgo = float(values[7]) if values[7] is not None else 0
            total_consumption = lsf + lgo

            row_data = [
                dt.isoformat(),
                float(values[1]) if values[1] is not None else None,
                float(values[2]) if values[2] is not None else None,
                float(values[3]) if values[3] is not None else None,
                float(values[4]) if values[4] is not None else None,
                float(values[5]) if values[5] is not None else None,
                lsf if values[6] is not None else None,
                lgo if values[7] is not None else None,
                str(values[8]).strip() if values[8] is not None else None,
                float(values[9]) if values[9] is not None else None,
                total_consumption,
                None, None, None, None, None  # hindcast columns
            ]

            cursor.execute("""
                INSERT INTO VesonData
                (`Date Time`, `Steaming Hours`, `Observed Distance`, `CP Ordered Speed`, `Projected Speed`,
                 `Average RPM`, `LSF Consumption`, `LGO Consumption`, `Vessel Condition`, `Sea Height`,
                 `Total Consumption`, `hind_cast_avg_cf`, `Current adjusted +-distance`,
                 `Hindcast-Max SWH`, `Hindcast-Max Wind Speed`, `Hind Cast-Good weather`)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            """, row_data)
            count += 1

        conn.commit()
        conn.close()
        return f"✅ Imported {count} rows into VesonData"

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
                date_time = datetime.fromisoformat(date_time_str)
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


@app.route("/ship-performance", methods=["GET", "POST"])
def ship_performance():
    if 'logged_in' not in session:
        return redirect(url_for('login'))

    if request.method == "POST":
        ais_message = None
        weather_message = None

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

        try:
            summary = _build_summary()
            return render_template("summary.html", summary=summary)
        except Exception as e:
            return render_template("upload.html", error=f"Error generating summary: {e}")

    return render_template("upload.html")

# Helper: build summary data from DB
def _build_summary():
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
    return summary


# Generate performance summary
@app.route("/generate_summary", methods=["POST"])
def generate_summary():
    if 'logged_in' not in session:
        return redirect(url_for('login'))
    try:
        summary = _build_summary()
        return render_template("summary.html", summary=summary)
    except Exception as e:
        return f"❌ Error generating summary: {e}"


# Download summary as PDF
@app.route("/sw.js")
def service_worker():
    """Serve SW from root scope so it can cache all routes."""
    return send_from_directory(app.static_folder, "sw.js", mimetype="application/javascript")


@app.route("/offline")
def offline():
    return render_template("offline.html")


@app.route("/")
def index():
    return render_template("index.html")


@app.route("/login", methods=["GET", "POST"])
def login():
    if request.method == "POST":
        if request.form.get("username") == "Admin" and request.form.get("password") == "Admin":
            session['logged_in'] = True
            return redirect(url_for("dashboard"))
        else:
            return render_template("login.html", error="Invalid credentials")
    return render_template("login.html")


@app.route("/logout")
def logout():
    session.pop('logged_in', None)
    return redirect(url_for('login'))


@app.route("/dashboard")
def dashboard():
    if 'logged_in' not in session:
        return redirect(url_for('login'))
    return render_template("dashboard.html")


@app.route("/contact", methods=["POST"])
def contact():
    name = request.form.get("name", "").strip()
    email = request.form.get("email", "").strip()
    company = request.form.get("company", "").strip()
    reason = request.form.get("reason", "").strip()
    # TODO: Process the contact form (e.g., send email, save to DB)
    return redirect(url_for("index", _anchor="contact"))


if __name__ == "__main__":
    app.run(debug=True, port=5000)

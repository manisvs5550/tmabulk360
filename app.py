import os
import asyncio
import sqlite3
from datetime import datetime
from flask import Flask, render_template, request, redirect, url_for, send_from_directory, make_response, session, jsonify
from playwright.async_api import async_playwright

app = Flask(__name__)
app.secret_key = os.environ.get("SECRET_KEY", os.urandom(32))

DB_PATH = "ship_movement.sqlite3"

# --- Inventory items from the cleaning & maintenance checklist ---
INVENTORY_ITEMS = [
    {"no": 1,  "item": "HIGH PRESSURE WATER JET-200 Bar", "min_qty": 1, "remarks": "200Bar"},
    {"no": 2,  "item": "High pressure water jet -500 Bar", "min_qty": 1, "remarks": "Reconditioned Old Equipment with broken nozzle or gun or similar issues"},
    {"no": 3,  "item": "HIGH PRESSURE WATER JET- SET OF SPARE PARTS FOR PUMPING ELEMENT", "min_qty": None, "remarks": ""},
    {"no": 4,  "item": "HOLD CLEANING GUN (Combi gun etc)", "min_qty": 2, "remarks": ""},
    {"no": 5,  "item": "STAND FOR HOLD CLEANING GUN", "min_qty": 2, "remarks": ""},
    {"no": 6,  "item": "CHEMICAL APPLICATOR UNIT", "min_qty": 1, "remarks": "Air Operated Chemical Pump"},
    {"no": 7,  "item": "TELESCOPIC POLE FOR REACHING HIGH AREAS BY THE USE OF CHEMICAL APPLICATOR", "min_qty": 1, "remarks": "Mention how many meters long"},
    {"no": 8,  "item": "SPRAY FOAM SYSTEM WITH MINI GUN (CHEMICAL APPLICATION)", "min_qty": 1, "remarks": ""},
    {"no": 9,  "item": "ALLUMINIUM/ STEEL SCAFFOLDING TOWER or SIMILAR EQUIPMENT", "min_qty": 1, "remarks": ""},
    {"no": 10, "item": "MAN CAGE/BASKET/SIMILAR EQUIPMENT LIKE MOVABLE PLATFORMS, LADDER ETC USED TO REACH UPPER PARTS OF CARGO HOLDS BY THE USE OF SHIP'S CRANE", "min_qty": 1, "remarks": ""},
    {"no": 11, "item": "WOODEN STAGES", "min_qty": 2, "remarks": "Gondola"},
    {"no": 12, "item": "TELESCOPIC LADDER", "min_qty": 2, "remarks": "Maximum 6mtrs"},
    {"no": 13, "item": "AIRLESS PAINT SPRAY MACHINE", "min_qty": 1, "remarks": ""},
    {"no": 14, "item": "EXTENSION POLE FOR PAINT SPRAY MACHINE", "min_qty": 1, "remarks": ""},
    {"no": 15, "item": "HEAVY DUTY DESCALING MACHINES FOR TANK TOPS (Rustibus, Scatol etc)", "min_qty": 1, "remarks": "Reconditioned Old Equipment"},
    {"no": 16, "item": "PNEUMATIC SCALING HAMMER", "min_qty": 4, "remarks": ""},
    {"no": 17, "item": "TELESCOPIC POLE", "min_qty": 4, "remarks": "5mtrs"},
    {"no": 18, "item": "FIXED AIR COMPRESSOR (For Deck use)", "min_qty": 1, "remarks": ""},
    {"no": 19, "item": "ELECTRICAL SUBMERSIBLE PUMP capable of transferring cargo hold wash water from tanktop to overboard or in wash water tank", "min_qty": 1, "remarks": ""},
    {"no": 20, "item": "WILDEN PUMP (diaphragm air pump) capable of transferring cargo hold wash water from tanktop to overboard or in wash water tank", "min_qty": 1, "remarks": ""},
    {"no": 21, "item": "CHEMICAL PROTECTION SUIT", "min_qty": 3, "remarks": ""},
    {"no": 22, "item": "RESPIRATION FACE MASK", "min_qty": 5, "remarks": ""},
    {"no": 23, "item": "SPARE FILTER FOR FULL FACE MASK", "min_qty": 4, "remarks": ""},
]


def get_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn


def init_inventory_table():
    """Create the inventory_submissions table if it doesn't exist."""
    with sqlite3.connect(DB_PATH) as conn:
        conn.execute("""
            CREATE TABLE IF NOT EXISTS inventory_submissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                item_no INTEGER NOT NULL,
                item_name TEXT NOT NULL,
                qty_requested INTEGER NOT NULL,
                submitted_at TEXT NOT NULL
            )
        """)
        conn.commit()


init_inventory_table()


@app.route("/inventory", methods=["GET"])
def inventory():
    if 'logged_in' not in session:
        return redirect(url_for('login'))
    return render_template("inventory.html", items=INVENTORY_ITEMS, success=request.args.get("success"))


@app.route("/inventory/submit", methods=["POST"])
def inventory_submit():
    if 'logged_in' not in session:
        return redirect(url_for('login'))

    username = session.get("username", "Admin")
    now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    with sqlite3.connect(DB_PATH) as conn:
        for item in INVENTORY_ITEMS:
            field = f"rob_{item['no']}"
            value = request.form.get(field, "").strip()
            if value:
                try:
                    qty = int(value)
                except (ValueError, TypeError):
                    continue
                if qty < 0:
                    continue
                min_qty = item.get("min_qty")
                if min_qty is not None and qty != 0 and qty < min_qty:
                    continue
                conn.execute(
                    "INSERT INTO inventory_submissions (username, item_no, item_name, qty_requested, submitted_at) VALUES (?, ?, ?, ?, ?)",
                    (username, item["no"], item["item"], qty, now),
                )
        conn.commit()

    return redirect(url_for("inventory", success="1"))


@app.route("/inventory/history")
def inventory_history():
    if 'logged_in' not in session:
        return redirect(url_for('login'))
    conn = get_db()
    rows = conn.execute(
        "SELECT username, item_no, item_name, qty_requested, submitted_at FROM inventory_submissions ORDER BY submitted_at DESC, item_no"
    ).fetchall()
    conn.close()
    return render_template("inventory_history.html", rows=rows)



@app.route("/generate-pdf-image")
async def generate_pdf_image():
    """Generates a PDF of the index page using a headless browser."""
    async with async_playwright() as p:
        browser = await p.chromium.launch()
        page = await browser.new_page()
        
        # Navigate to the live index page
        # Note: This requires the Flask app to be running
        base_url = request.url_root
        await page.goto(base_url, wait_until="networkidle")
        
        # Generate the PDF from the live page
        pdf_bytes = await page.pdf(format="A4", print_background=True, margin={"top": "1cm", "bottom": "1cm", "left": "1cm", "right": "1cm"})
        
        await browser.close()

    # Create a response
    response = make_response(pdf_bytes)
    response.headers['Content-Type'] = 'application/pdf'
    response.headers['Content-Disposition'] = 'inline; filename=tmaops360_preview.pdf'
    return response


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
            session['username'] = request.form.get("username")
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
    env = os.environ.get("FLASK_ENV", "development")
    if env == "production":
        from waitress import serve
        port = int(os.environ.get("PORT", 8080))
        print(f"Serving on http://0.0.0.0:{port}")
        serve(app, host="0.0.0.0", port=port)
    else:
        from hypercorn.config import Config
        from hypercorn.asyncio import serve
        from asgiref.wsgi import WsgiToAsgi

        config = Config()
        config.bind = ["localhost:5000"]
        config.use_reloader = True
        
        asyncio.run(serve(WsgiToAsgi(app), config))

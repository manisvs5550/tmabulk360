import os

bind = f"0.0.0.0:{os.environ.get('PORT', '10000')}"
timeout = 180
workers = 1

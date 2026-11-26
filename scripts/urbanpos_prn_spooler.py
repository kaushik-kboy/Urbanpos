"""
UrbanPOS Automatic PRN Spooler & Hardware Print Service for TSC TE244.
Monitors the user's Downloads folder for labels_tsc_te244*.prn and prints
instantly via Windows win32print, then cleans up the downloaded file.
Also runs an HTTP server on port 8989 for direct 1-click web printing.
"""

import os
import sys
import time
import glob
import json
import threading
from http.server import HTTPServer, BaseHTTPRequestHandler

try:
    import win32print
except ImportError:
    print("win32print not found. Install via pip install pywin32")
    sys.exit(1)

PRINTER_NAME = "TSC TE244"
PORT = 8989
DOWNLOADS_DIR = os.path.join(os.path.expanduser("~"), "Downloads")
SPOOL_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "spool")

os.makedirs(SPOOL_DIR, exist_ok=True)

def print_raw_tspl(data_bytes):
    """Send raw TSPL bytes directly to TSC TE244 via win32print."""
    if not data_bytes or b"PRINT" not in data_bytes:
        return False, "No PRINT commands found in TSPL payload"

    try:
        p = win32print.OpenPrinter(PRINTER_NAME)
        try:
            job = win32print.StartDocPrinter(p, 1, ("UrbanPOS Direct Print", None, "RAW"))
            try:
                win32print.StartPagePrinter(p)
                win32print.WritePrinter(p, data_bytes)
                win32print.EndPagePrinter(p)
            finally:
                win32print.EndDocPrinter(p)
        finally:
            win32print.ClosePrinter(p)
        return True, "Printed successfully to " + PRINTER_NAME
    except Exception as e:
        return False, str(e)

class PrintRequestHandler(BaseHTTPRequestHandler):
    def _send_cors_headers(self):
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type, X-Requested-With, Authorization")
        self.send_header("Access-Control-Allow-Private-Network", "true")

    def do_OPTIONS(self):
        self.send_response(200)
        self._send_cors_headers()
        self.end_headers()

    def do_GET(self):
        if self.path in ["/", "/status", "/ping"]:
            self.send_response(200)
            self._send_cors_headers()
            self.send_header("Content-Type", "application/json")
            self.end_headers()
            response = {
                "status": "online",
                "service": "UrbanPOS TSC Print Bridge & Auto Spooler",
                "printer": PRINTER_NAME,
                "calibrated_format": "50x38_2up_horizontal"
            }
            self.wfile.write(json.dumps(response).encode("utf-8"))
        else:
            self.send_response(404)
            self.end_headers()

    def do_POST(self):
        if self.path == "/print":
            content_length = int(self.headers.get("Content-Length", 0))
            if content_length <= 0:
                self.send_response(400)
                self._send_cors_headers()
                self.send_header("Content-Type", "application/json")
                self.end_headers()
                self.wfile.write(json.dumps({"success": False, "error": "Empty print data"}).encode("utf-8"))
                return

            raw_body = self.rfile.read(content_length)
            tspl_bytes = raw_body
            try:
                data = json.loads(raw_body.decode("utf-8", errors="ignore"))
                if isinstance(data, dict) and "tspl" in data:
                    tspl_bytes = data["tspl"].encode("utf-8")
            except Exception:
                pass

            success, msg = print_raw_tspl(tspl_bytes)
            status_code = 200 if success else 500
            self.send_response(status_code)
            self._send_cors_headers()
            self.send_header("Content-Type", "application/json")
            self.end_headers()
            self.wfile.write(json.dumps({"success": success, "message": msg}).encode("utf-8"))
        else:
            self.send_response(404)
            self.end_headers()

    def log_message(self, format, *args):
        pass

def run_http_server():
    try:
        server = HTTPServer(("127.0.0.1", PORT), PrintRequestHandler)
        server.serve_forever()
    except Exception:
        pass

def log_action(msg):
    try:
        log_file = os.path.join(SPOOL_DIR, "spooler.log")
        with open(log_file, "a", encoding="utf-8") as f:
            f.write(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] {msg}\n")
    except Exception:
        pass

def watch_folders():
    """Continuously watches Downloads and Spool folders for PRN files and prints them instantly."""
    patterns = [
        os.path.join(DOWNLOADS_DIR, "*.prn"),
        os.path.join(DOWNLOADS_DIR, "labels_tsc_te244*.prn"),
        os.path.join(DOWNLOADS_DIR, "urbanpos_*.prn"),
        os.path.join(DOWNLOADS_DIR, "labels_*.prn"),
        os.path.join(SPOOL_DIR, "*.prn")
    ]

    log_action("UrbanPOS PRN Spooler started. Watching: " + DOWNLOADS_DIR)

    while True:
        try:
            for pattern in patterns:
                for file_path in glob.glob(pattern):
                    try:
                        size1 = os.path.getsize(file_path)
                        time.sleep(0.12)
                        size2 = os.path.getsize(file_path)
                        if size1 != size2 or size1 == 0:
                            continue

                        with open(file_path, "rb") as f:
                            content = f.read()

                        if content and (b"PRINT" in content or b"SIZE" in content):
                            success, msg = print_raw_tspl(content)
                            log_action(f"Printed {file_path}: {success} ({msg})")
                            if success:
                                try:
                                    os.remove(file_path)
                                except Exception:
                                    pass
                    except Exception as e:
                        log_action(f"Error handling {file_path}: {e}")
        except Exception:
            pass
        time.sleep(0.3)

if __name__ == "__main__":
    # Start HTTP server on port 8989 in background thread
    t = threading.Thread(target=run_http_server, daemon=True)
    t.start()

    # Run PRN downloads watcher on main thread
    watch_folders()

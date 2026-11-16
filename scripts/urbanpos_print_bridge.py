"""
UrbanPOS Local Print Bridge for TSC TE244 Thermal Printer.
Listens on http://127.0.0.1:8989 and forwards raw TSPL directly to TSC TE244 on USB.
Enables instant 1-click printing from pos.indianpetcompany.com web browser!
"""

import sys
import json
from http.server import HTTPServer, BaseHTTPRequestHandler
import win32print

PRINTER_NAME = "TSC TE244"
PORT = 8989

class PrintRequestHandler(BaseHTTPRequestHandler):
    def _send_cors_headers(self):
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type, X-Requested-With, Authorization")

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
                "service": "UrbanPOS TSC Print Bridge",
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

            # Check if JSON payload with 'tspl' string or raw TSPL bytes
            tspl_bytes = raw_body
            try:
                data = json.loads(raw_body.decode("utf-8", errors="ignore"))
                if isinstance(data, dict) and "tspl" in data:
                    tspl_bytes = data["tspl"].encode("utf-8")
            except Exception:
                pass

            try:
                p = win32print.OpenPrinter(PRINTER_NAME)
                try:
                    job = win32print.StartDocPrinter(p, 1, ("UrbanPOS Direct Print", None, "RAW"))
                    try:
                        win32print.StartPagePrinter(p)
                        win32print.WritePrinter(p, tspl_bytes)
                        win32print.EndPagePrinter(p)
                    finally:
                        win32print.EndDocPrinter(p)
                finally:
                    win32print.ClosePrinter(p)

                self.send_response(200)
                self._send_cors_headers()
                self.send_header("Content-Type", "application/json")
                self.end_headers()
                self.wfile.write(json.dumps({
                    "success": True,
                    "message": "Printed directly to " + PRINTER_NAME
                }).encode("utf-8"))
            except Exception as e:
                self.send_response(500)
                self._send_cors_headers()
                self.send_header("Content-Type", "application/json")
                self.end_headers()
                self.wfile.write(json.dumps({
                    "success": False,
                    "error": str(e)
                }).encode("utf-8"))
        else:
            self.send_response(404)
            self.end_headers()

    def log_message(self, format, *args):
        # Suppress noisy HTTP request console logging
        pass

def run():
    server = HTTPServer(("127.0.0.1", PORT), PrintRequestHandler)
    print(f"UrbanPOS TSC TE244 Print Bridge running on http://127.0.0.1:{PORT}")
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()

if __name__ == "__main__":
    run()

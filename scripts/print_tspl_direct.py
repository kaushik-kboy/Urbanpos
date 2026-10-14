import sys
import win32print

printer_name = "TSC TE244"

try:
    if len(sys.argv) > 1:
        with open(sys.argv[1], 'rb') as f:
            data = f.read()
    else:
        data = sys.stdin.buffer.read()

    if not data:
        print("ERROR: No print data provided")
        sys.exit(1)

    p = win32print.OpenPrinter(printer_name)
    try:
        job = win32print.StartDocPrinter(p, 1, ("UrbanPOS Direct Print", None, "RAW"))
        try:
            win32print.StartPagePrinter(p)
            win32print.WritePrinter(p, data)
            win32print.EndPagePrinter(p)
            print("SUCCESS")
        finally:
            win32print.EndDocPrinter(p)
    finally:
        win32print.ClosePrinter(p)
except Exception as e:
    print(f"ERROR: {e}")
    sys.exit(1)

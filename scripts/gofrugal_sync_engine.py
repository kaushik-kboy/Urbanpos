#!/usr/bin/env python3
"""
GoFrugal TruePOS Automated Headless Report Sync Engine
Extracts Sales, Purchases, Stock Transfers, and Inventory Valuation CSVs.
"""

import os
import sys
import time
import argparse
import datetime
from playwright.sync_api import sync_playwright

# Set UTF-8 output
sys.stdout.reconfigure(encoding='utf-8')

DEFAULT_URL = os.getenv("GOFRUGAL_URL", "https://urbanpets.true-pos.com/TruePOS/index.do")
DEFAULT_USER = os.getenv("GOFRUGAL_USERNAME", "admin1")
DEFAULT_PASS = os.getenv("GOFRUGAL_PASSWORD", "Moni@31")

REPORT_CONFIGS = {
    "110150": {
        "key": "daily_sales_110150",
        "name": "Daily Sales Summary",
        "url": "https://urbanpets.true-pos.com/smartreport/index.html#/reports?reportId=110150&productId=5"
    },
    "110116": {
        "key": "sales_register_110116",
        "name": "Sales Register (Itemized Bills)",
        "url": "https://urbanpets.true-pos.com/smartreport/index.html#/reports?reportId=110116&productId=5"
    },
    "110120": {
        "key": "purchase_detail_110120",
        "name": "Purchase Detail (GRN)",
        "url": "https://urbanpets.true-pos.com/smartreport/index.html#/reports?reportId=110120&productId=5"
    },
    "110282": {
        "key": "transfer_detail_110282",
        "name": "Stock Transfer Out Detail",
        "url": "https://urbanpets.true-pos.com/smartreport/index.html#/reports?reportId=110282&productId=5"
    }
}

def login(page, base_url, username, password):
    print(f"[*] Navigating to {base_url}...")
    page.goto(base_url, timeout=45000)
    page.wait_for_selector("#t_username", timeout=20000)

    print(f"[*] Submitting credentials for user '{username}'...")
    page.fill("#t_username", username)
    page.fill("#t_password", password)
    page.click("button.btn-submit")
    page.wait_for_timeout(3500)

    if "killSession.do" in page.content():
        print("[!] Detected concurrent session. Terminating previous session...")
        page.click("a[href='killSession.do']")
        page.wait_for_timeout(3000)
        page.goto(base_url)
        page.wait_for_selector("#t_username", timeout=15000)
        page.fill("#t_username", username)
        page.fill("#t_password", password)
        page.click("button.btn-submit")
        page.wait_for_timeout(4000)

    # Dismiss any notification modal
    try:
        if page.locator("button.btn-understand").is_visible():
            page.click("button.btn-understand")
            page.wait_for_timeout(1000)
    except Exception:
        pass

    print("[+] Successfully authenticated into TruePOS!")

def set_date_filter(page, target_date_str):
    """
    Sets date range in SmartReport daterangepicker.
    target_date_str format: YYYY-MM-DD
    """
    print(f"[*] Setting SmartReport date filter to {target_date_str}...")
    
    # Open datepicker input
    page.evaluate("""() => {
        const inp = document.querySelector('.icons-calendar-icomoon').closest('.input-group').querySelector('input');
        if (inp) inp.click();
    }""")
    page.wait_for_timeout(1200)

    # Convert YYYY-MM-DD to DD-MM-YYYY if needed by daterangepicker
    dt = datetime.datetime.strptime(target_date_str, "%Y-%m-%d")
    formatted_date = dt.strftime("%d-%m-%Y")

    # Check if target is today or this month
    today_str = datetime.date.today().strftime("%Y-%m-%d")
    
    handled = False
    if target_date_str == today_str:
        today_btn = page.locator("button.sr-rangeBtn:has-text('Today')").first
        if today_btn.is_visible():
            today_btn.click()
            handled = True

    if not handled:
        # Fill daterangepicker inputs directly via JS
        page.evaluate(f"""() => {{
            const picker = document.querySelector('.daterangepicker');
            if (picker) {{
                const startInp = picker.querySelector('input[name="daterangepicker_start"]');
                const endInp = picker.querySelector('input[name="daterangepicker_end"]');
                if (startInp && endInp) {{
                    startInp.value = '{formatted_date}';
                    startInp.dispatchEvent(new Event('change', {{ bubbles: true }}));
                    endInp.value = '{formatted_date}';
                    endInp.dispatchEvent(new Event('change', {{ bubbles: true }}));
                }}
                const applyBtn = picker.querySelector('button.btn-primary, button.applyBtn');
                if (applyBtn) applyBtn.click();
            }}
        }}""")
        page.wait_for_timeout(1000)

    # Click main Filter Apply button
    print("[*] Applying main report filters...")
    apply_btn = page.locator("button:has-text('Apply')").first
    if apply_btn.is_visible():
        apply_btn.click()
    page.wait_for_timeout(7000)

def export_report_csv(page, output_file_path):
    print("[*] Opening export drawer...")
    page.mouse.click(14, 180)
    page.wait_for_timeout(2500)

    print("[*] Triggering CSV export download...")
    with page.expect_download(timeout=30000) as download_info:
        clicked = page.evaluate("""() => {
            const rows = document.querySelectorAll('li, div.row, div.form-group, tr');
            for (const r of rows) {
                if (r.innerText && r.innerText.includes('Export as csv')) {
                    const btn = r.querySelector('button, a, input[type="button"]');
                    if (btn) { btn.click(); return true; }
                }
            }
            const allBtns = document.querySelectorAll('button');
            for (const b of allBtns) {
                if (b.innerText && b.innerText.includes('Export')) {
                    b.click(); return true;
                }
            }
            return false;
        }""")
        if not clicked:
            raise Exception("Export as csv button not found in drawer!")

    download = download_info.value
    download.save_as(output_file_path)
    file_size = os.path.getsize(output_file_path)
    print(f"[+] Download complete: {output_file_path} ({file_size} bytes)")
    return file_size

def run_sync(target_date, out_dir, report_ids=None, headless=True):
    os.makedirs(out_dir, exist_ok=True)
    if report_ids is None:
        report_ids = ["110150", "110116", "110120", "110282"]

    print("=========================================================")
    print("      GOFRUGAL TRUEPOS AUTOMATED EXTRACTION ENGINE       ")
    print(f" Target Date:  {target_date}")
    print(f" Output Dir:   {out_dir}")
    print(f" Reports:      {', '.join(report_ids)}")
    print("=========================================================")

    results = {}

    with sync_playwright() as p:
        browser = p.chromium.launch(channel="chrome", headless=headless)
        page = browser.new_page(
            viewport={"width": 1920, "height": 1080},
            accept_downloads=True
        )

        # SmartReport 404 bypass
        def handle_route(route):
            url = route.request.url
            if "/WebReporter/" in url:
                new_url = url.replace("/WebReporter/", "/TruePOS/")
                try:
                    route.continue_(url=new_url)
                except Exception:
                    route.continue_()
            else:
                route.continue_()

        page.route("**/WebReporter/**", handle_route)

        login(page, DEFAULT_URL, DEFAULT_USER, DEFAULT_PASS)

        for rep_id in report_ids:
            cfg = REPORT_CONFIGS.get(rep_id)
            if not cfg:
                print(f"[!] Warning: Unknown report ID {rep_id}, skipping.")
                continue

            print(f"\n>>> PROCESSING REPORT {rep_id}: {cfg['name']} <<<")
            target_csv = os.path.join(out_dir, f"{cfg['key']}.csv")

            try:
                page.goto(cfg['url'], timeout=45000)
                page.wait_for_timeout(5000)

                set_date_filter(page, target_date)
                sz = export_report_csv(page, target_csv)
                results[rep_id] = {
                    "status": "success",
                    "file": target_csv,
                    "bytes": sz
                }
            except Exception as e:
                print(f"[-] ERROR exporting {rep_id}: {e}")
                results[rep_id] = {
                    "status": "failed",
                    "error": str(e)
                }

        browser.close()

    print("\n=========================================================")
    print("                 EXTRACTION SUMMARY                     ")
    print("=========================================================")
    all_success = True
    for rep_id, res in results.items():
        name = REPORT_CONFIGS.get(rep_id, {}).get('name', rep_id)
        if res['status'] == 'success':
            print(f" [✓] {rep_id} - {name}: {res['bytes']} bytes -> {res['file']}")
        else:
            print(f" [✗] {rep_id} - {name}: FAILED ({res.get('error')})")
            all_success = False

    return all_success

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="GoFrugal Headless Sync Engine")
    parser.add_argument("--date", help="Target Date YYYY-MM-DD (default yesterday)", default=None)
    parser.add_argument("--out-dir", help="Output directory for CSVs", default=None)
    parser.add_argument("--reports", help="Comma-separated report IDs (default all)", default=None)
    parser.add_argument("--no-headless", help="Run with visible browser", action="store_true")

    args = parser.parse_args()

    sync_date = args.date
    if not sync_date:
        # Default to yesterday
        sync_date = (datetime.date.today() - datetime.timedelta(days=1)).strftime("%Y-%m-%d")

    out_directory = args.out_dir
    if not out_directory:
        out_directory = os.path.join(os.path.dirname(__file__), "..", "storage", "app", "gofrugal_sync", sync_date)
    out_directory = os.path.abspath(out_directory)

    report_list = None
    if args.reports:
        report_list = [r.strip() for r in args.reports.split(",")]

    is_headless = not args.no_headless
    success = run_sync(sync_date, out_directory, report_list, headless=is_headless)
    sys.exit(0 if success else 1)

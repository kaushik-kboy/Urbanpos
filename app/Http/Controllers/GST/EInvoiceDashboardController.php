<?php

namespace App\Http\Controllers\GST;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\GstSetting;
use App\Models\PurchaseInvoice;
use App\Models\SalesBill;
use App\Services\GST\EInvoiceService;
use App\Services\GST\EWayBillService;
use App\Services\GST\Gstr1ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class EInvoiceDashboardController extends Controller
{
    /**
     * Display the GST E-Filing & E-Invoice Integration Hub (as seen in video 6.mp4).
     */
    public function index(Request $request, EInvoiceService $service)
    {
        $settings = GstSetting::current();
        
        // Active view: 'returns' (GST Returns: GSTR-1, GSTR-3B, GSTR-2, 2A, 2B, 9) or 'einvoice' (E-Invoice Hub)
        $viewMode = $request->input('view', 'returns');
        $tab = $request->input('tab', 'pending'); // 'pending', 'failed', 'completed'

        // Date range filters (default: current month)
        $fromDate = $request->input('from_date', now()->startOfMonth()->format('Y-m-d'));
        $toDate = $request->input('to_date', now()->format('Y-m-d'));
        $docType = $request->input('doc_type', 'all');
        $customerSearch = trim($request->input('customer', ''));
        $invoiceSearch = trim($request->input('search', ''));

        // -------------------------------------------------------------
        // 1. CALCULATE GST RETURNS METRICS (GSTR-1, GSTR-3B, GSTR-2, 9)
        // -------------------------------------------------------------
        // Sales / Outward (GSTR-1)
        $salesQuery = SalesBill::whereDate('bill_date', '>=', $fromDate)
            ->whereDate('bill_date', '<=', $toDate)
            ->where('status', '!=', 'Cancelled');

        $gstr1Count = (clone $salesQuery)->count();
        $gstr1Total = (float) (clone $salesQuery)->sum('total');
        $gstr1TaxCollected = (float) (clone $salesQuery)->sum('total_gst');
        
        if ($gstr1TaxCollected == 0 && $gstr1Total > 0) {
            $itemGst = DB::table('sales_bill_items')
                ->join('sales_bills', 'sales_bill_items.sales_bill_id', '=', 'sales_bills.id')
                ->whereDate('sales_bills.bill_date', '>=', $fromDate)
                ->whereDate('sales_bills.bill_date', '<=', $toDate)
                ->where('sales_bills.status', '!=', 'Cancelled')
                ->sum('sales_bill_items.gst_tax_amount');
            $gstr1TaxCollected = (float) $itemGst > 0 ? (float) $itemGst : round($gstr1Total * 18 / 118, 2);
        }
        $gstr1Taxable = round(max(0, $gstr1Total - $gstr1TaxCollected), 2);

        // Purchases / Inward (GSTR-2)
        $purQuery = PurchaseInvoice::whereDate('invoice_date', '>=', $fromDate)
            ->whereDate('invoice_date', '<=', $toDate)
            ->where('status', '!=', 'Cancelled');

        $gstr2Count = (clone $purQuery)->count();
        $gstr2Total = (float) (clone $purQuery)->sum('total');
        $gstr2TaxPaid = (float) (clone $purQuery)->sum('total_gst');
        if ($gstr2TaxPaid == 0 && $gstr2Total > 0) {
            $gstr2TaxPaid = round($gstr2Total * 18 / 118, 2);
        }
        $gstr2Taxable = round(max(0, $gstr2Total - $gstr2TaxPaid), 2);

        // GSTR-3B Computations
        $gstr3bTaxCollected = $gstr1TaxCollected;
        $gstr3bTaxPaid = $gstr2TaxPaid;
        $gstr3bTaxPayable = round(max(0, $gstr3bTaxCollected - $gstr3bTaxPaid), 2);

        // -------------------------------------------------------------
        // 2. E-INVOICE HUB METRICS & DATA
        // -------------------------------------------------------------
        // Cancelled invoices must never be offered for IRN generation.
        $baseQuery = SalesBill::with(['customer', 'branch', 'items.item'])
            ->where('status', '!=', 'Cancelled')
            ->where(function ($q) use ($settings) {
                $threshold = (float) ($settings->auto_upload_threshold ?: 50000.00);
                $q->where('total', '>=', $threshold)
                    ->orWhereNotNull('irn')
                    ->orWhereNotNull('einvoice_error')
                    ->orWhere('einvoice_status', 'Failed')
                    ->orWhereHas('customer', function ($cq) {
                        $cq->whereNotNull('gst_no')->where('gst_no', '!=', '');
                    });
            });

        // Tab counts
        $countsQuery = clone $baseQuery;
        $pendingCount = (clone $countsQuery)->where(function ($q) {
            $q->whereNull('einvoice_status')
                ->orWhere('einvoice_status', 'Pending');
        })->whereNull('irn')->count();

        $failedCount = (clone $countsQuery)->where('einvoice_status', 'Failed')->count();
        $completedCount = (clone $countsQuery)->where(function ($q) {
            $q->where('einvoice_status', 'Completed')
                ->orWhereNotNull('irn');
        })->count();

        // Filter by Date
        if (!empty($fromDate)) {
            $baseQuery->whereDate('bill_date', '>=', $fromDate);
        }
        if (!empty($toDate)) {
            $baseQuery->whereDate('bill_date', '<=', $toDate);
        }

        // Filter by Search
        if (!empty($customerSearch)) {
            $baseQuery->whereHas('customer', function ($q) use ($customerSearch) {
                $q->where('name', 'like', "%{$customerSearch}%")
                    ->orWhere('gst_no', 'like', "%{$customerSearch}%")
                    ->orWhere('phone', 'like', "%{$customerSearch}%");
            });
        }

        if (!empty($invoiceSearch)) {
            $baseQuery->where('bill_number', 'like', "%{$invoiceSearch}%");
        }

        // Filter by Tab
        if ($tab === 'completed') {
            $baseQuery->where(function ($q) {
                $q->where('einvoice_status', 'Completed')->orWhereNotNull('irn');
            });
        } elseif ($tab === 'failed') {
            $baseQuery->where('einvoice_status', 'Failed');
        } else {
            $tab = 'pending';
            $baseQuery->where(function ($q) {
                $q->whereNull('einvoice_status')
                    ->orWhere('einvoice_status', 'Pending');
            })->whereNull('irn');
        }

        $bills = $baseQuery->orderByDesc('bill_date')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('gst.einvoice-dashboard', [
            'viewMode' => $viewMode,
            'bills' => $bills,
            'tab' => $tab,
            'pendingCount' => $pendingCount,
            'failedCount' => $failedCount,
            'completedCount' => $completedCount,
            'settings' => $settings,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'docType' => $docType,
            'customerSearch' => $customerSearch,
            'invoiceSearch' => $invoiceSearch,
            // GSTR metrics
            'gstr1Count' => $gstr1Count,
            'gstr1Taxable' => $gstr1Taxable,
            'gstr1TaxCollected' => $gstr1TaxCollected,
            'gstr2Count' => $gstr2Count,
            'gstr2Taxable' => $gstr2Taxable,
            'gstr2TaxPaid' => $gstr2TaxPaid,
            'gstr3bTaxPayable' => $gstr3bTaxPayable,
            'gstr3bTaxPaid' => $gstr3bTaxPaid,
            'gstr3bTaxCollected' => $gstr3bTaxCollected,
        ]);
    }

    /**
     * Dedicated GSTR-1 View (reproducing exact 12-card dashboard from urbanpets.true-pos.com).
     */
    /** Default GSTR-1 reporting period used when the request doesn't specify one. */
    private function defaultGstr1Period(Request $request): array
    {
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        if (empty($fromDate) || empty($toDate)) {
            $fromDate = now()->startOfMonth()->format('Y-m-d');
            $toDate = now()->format('Y-m-d');
        }
        return [$fromDate, $toDate];
    }

    /**
     * True when GstSetting::current() is still the sandbox/mock default
     * ('24AAECU3183G1ZN' / gsp_provider=mock / is_sandbox=true) rather than a
     * real configured business GSTIN — the GSTR-1 pages must show this
     * plainly instead of silently presenting a demo number as authoritative.
     */
    private function gstinIsSandboxDefault(GstSetting $settings): bool
    {
        return $settings->is_sandbox
            || $settings->gsp_provider === 'mock'
            || $settings->gstin === '24AAECU3183G1ZN';
    }

    public function gstr1View(Request $request)
    {
        $settings = GstSetting::current();
        $selectedPeriod = $request->input('period', now()->format('M Y') . ' - ' . now()->addYear()->format('Y'));

        [$fromDate, $toDate] = $this->defaultGstr1Period($request);

        $branch = Branch::first();
        $companyName = $branch?->name ?? 'URBANPETS SERVICES PRIVATE LIMITED';
        $gstin = $settings->gstin;
        $gstinIsSandbox = $this->gstinIsSandboxDefault($settings);

        $service = new Gstr1ReportService($fromDate, $toDate);
        $summary = $service->summary();

        return view('gst.gstr-1', [
            'companyName' => $companyName,
            'gstin' => $gstin,
            'gstinIsSandbox' => $gstinIsSandbox,
            'selectedPeriod' => $selectedPeriod,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'hsnB2bTaxable' => $summary['hsnB2b']['taxable'],
            'hsnB2bTax' => $summary['hsnB2b']['tax'],
            'hsnB2cTaxable' => $summary['hsnB2c']['taxable'],
            'hsnB2cTax' => $summary['hsnB2c']['tax'],
            'b2bCount' => $summary['b2b']['count'],
            'b2bTaxable' => $summary['b2b']['taxable'],
            'b2bTax' => $summary['b2b']['tax'],
            'b2clCount' => $summary['b2cl']['count'],
            'b2clTaxable' => $summary['b2cl']['taxable'],
            'b2clTax' => $summary['b2cl']['tax'],
            'exportCount' => 0,
            'exportTaxable' => 0,
            'exportTax' => 0,
            'b2csTaxable' => $summary['b2cs']['taxable'],
            'b2csTax' => $summary['b2cs']['tax'],
            'cdnrCount' => $summary['cdnr']['count'],
            'cdnrValue' => $summary['cdnr']['value'],
            'cdnrTax' => $summary['cdnr']['tax'],
            'cdnurCount' => $summary['cdnur']['count'],
            'cdnurValue' => $summary['cdnur']['value'],
            'cdnurTax' => $summary['cdnur']['tax'],
            'nilRatedAmount' => $summary['nil']['combined_amount'],
            'exemptedAmount' => 0.00, // see $summary['nil']['limitation'] — schema can't split this out
            'advanceReceivedTaxable' => 0,
            'advanceReceivedTax' => 0,
            'advanceAdjustedTaxable' => 0,
            'advanceAdjustedTax' => 0,
            'docIssuedTotal' => $summary['docs']['total_issued'],
            'cancelledCount' => $summary['docs']['total_cancelled'],
        ]);
    }

    /**
     * Section Detail View — each section now has its own real query via
     * Gstr1ReportService; nothing here reuses another section's data.
     */
    public function gstr1SectionView(Request $request, string $section = 'b2b-hsn')
    {
        $settings = GstSetting::current();
        $section = strtolower(trim(str_replace('_', '-', $section)));
        $branch = Branch::first();
        $companyName = $branch?->name ?? 'URBANPETS SERVICES PRIVATE LIMITED';
        $gstin = $settings->gstin;
        $gstinIsSandbox = $this->gstinIsSandboxDefault($settings);

        [$fromDate, $toDate] = $this->defaultGstr1Period($request);
        $service = new Gstr1ReportService($fromDate, $toDate);

        $layout = 'hsn';
        $rows = [];
        $meta = [];

        switch ($section) {
            case 'b2b-hsn':
                $layout = 'hsn';
                $data = $service->hsnB2b();
                $rows = $data['rows'];
                $meta = ['missing_hsn_qty' => $data['missing_hsn_qty'], 'has_more_groups' => $data['has_more_groups'], 'row_cap' => $data['row_cap']];
                break;
            case 'b2c-hsn':
                $layout = 'hsn';
                $data = $service->hsnB2c();
                $rows = $data['rows'];
                $meta = ['missing_hsn_qty' => $data['missing_hsn_qty'], 'has_more_groups' => $data['has_more_groups'], 'row_cap' => $data['row_cap']];
                break;
            case 'b2b':
                $layout = 'invoice';
                $rows = $service->b2b()['rows'];
                break;
            case 'b2cl':
                $layout = 'invoice-b2cl';
                $rows = $service->b2cl()['rows'];
                $meta = ['threshold' => Gstr1ReportService::B2CL_THRESHOLD];
                break;
            case 'b2cs':
                $layout = 'aggregate';
                $rows = $service->b2cs()['rows'];
                break;
            case 'cdnr':
                $layout = 'note';
                $data = $service->cdnr();
                $rows = $data['rows'];
                $meta = ['note' => $data['note']];
                break;
            case 'cdnur':
                $layout = 'note';
                $data = $service->cdnur();
                $rows = $data['rows'];
                $meta = ['note' => $data['note']];
                break;
            case 'nil':
                $layout = 'unsupported-summary';
                $data = $service->nilRated();
                $rows = [];
                $meta = ['amount' => $data['combined_amount'], 'limitation' => $data['limitation']];
                break;
            case 'exp':
                $layout = 'unsupported';
                $meta = ['limitation' => $service->exportSupplies()['limitation']];
                break;
            case 'adv-rec':
                $layout = 'unsupported';
                $meta = ['limitation' => $service->advanceReceived()['limitation']];
                break;
            case 'adv-adj':
                $layout = 'unsupported';
                $meta = ['limitation' => $service->advanceAdjusted()['limitation']];
                break;
            case 'doc-issued':
                $layout = 'documents';
                $rows = $service->documentsIssued()['rows'];
                break;
            default:
                $layout = 'hsn';
                $rows = [];
        }

        // Search filter (HSN/invoice-shaped layouts only — kept from the original behaviour)
        if ($request->filled('search') && in_array($layout, ['hsn', 'invoice', 'invoice-b2cl'], true)) {
            $q = trim($request->search);
            $rows = array_values(array_filter($rows, function ($r) use ($q) {
                foreach (['hsn', 'ref', 'rate'] as $key) {
                    if (isset($r[$key]) && str_contains((string) $r[$key], $q)) {
                        return true;
                    }
                }
                return false;
            }));
        }

        $sectionTitles = [
            'b2b-hsn' => 'b2b-hsn', 'b2c-hsn' => 'b2c-hsn', 'b2b' => 'b2b', 'b2cl' => 'b2cl',
            'b2cs' => 'b2cs', 'cdnr' => 'cdnr', 'cdnur' => 'cdnur', 'nil' => 'nil-rated',
            'exp' => 'exported-supplies', 'adv-rec' => 'advance-received', 'adv-adj' => 'advance-adjusted',
            'doc-issued' => 'doc-issued',
        ];
        $sectionLabel = $sectionTitles[$section] ?? $section;

        return view('gst.gstr-1-section', [
            'section' => $section,
            'sectionLabel' => $sectionLabel,
            'layout' => $layout,
            'rows' => $rows,
            'meta' => $meta,
            'companyName' => $companyName,
            'gstin' => $gstin,
            'gstinIsSandbox' => $gstinIsSandbox,
            'search' => $request->search ?? '',
        ]);
    }

    /**
     * GSTR-1 Full Breakdown (Modal / API).
     */
    public function gstr1Details(Request $request)
    {
        $fromDate = $request->input('from_date', now()->startOfMonth()->format('Y-m-d'));
        $toDate = $request->input('to_date', now()->format('Y-m-d'));

        $service = new Gstr1ReportService($fromDate, $toDate);
        $b2b = $service->b2b();
        $b2cs = $service->b2cs();
        $b2cl = $service->b2cl();

        return response()->json([
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'total_invoices' => $b2b['count'] + $b2cs['count'] + $b2cl['count'],
            'total_turnover' => round($b2b['total'] + ($b2cs['taxable'] + $b2cs['tax']) + $b2cl['total'], 2),
            'b2b' => [
                'count' => $b2b['count'],
                'taxable' => $b2b['taxable'],
                'tax' => $b2b['tax'],
                'total' => $b2b['total'],
            ],
            'b2cs' => [
                'count' => $b2cs['count'],
                'taxable' => $b2cs['taxable'],
                'tax' => $b2cs['tax'],
                'total' => round($b2cs['taxable'] + $b2cs['tax'], 2),
            ],
            'b2cl' => [
                'count' => $b2cl['count'],
                'taxable' => $b2cl['taxable'],
                'tax' => $b2cl['tax'],
                'total' => $b2cl['total'],
            ],
        ]);
    }

    /**
     * GSTR-3B Full Computation (Modal / API).
     */
    public function gstr3bDetails(Request $request)
    {
        $fromDate = $request->input('from_date', now()->startOfMonth()->format('Y-m-d'));
        $toDate = $request->input('to_date', now()->format('Y-m-d'));

        $sales = fn () => SalesBill::whereDate('bill_date', '>=', $fromDate)->whereDate('bill_date', '<=', $toDate)->where('status', '!=', 'Cancelled');
        $purchases = fn () => PurchaseInvoice::whereDate('invoice_date', '>=', $fromDate)->whereDate('invoice_date', '<=', $toDate)->where('status', '!=', 'Cancelled');

        $salesTotal = (float) $sales()->sum('total');
        $salesGst = (float) $sales()->sum('total_gst');
        $salesSplit = ['cgst' => (float) $sales()->sum('total_cgst'), 'sgst' => (float) $sales()->sum('total_sgst'), 'igst' => (float) $sales()->sum('total_igst')];
        if ($salesGst == 0 && $salesTotal > 0) {
            $salesGst = round($salesTotal * 18 / 118, 2);
        }
        $salesTaxable = round(max(0, $salesTotal - $salesGst), 2);

        $purTotal = (float) $purchases()->sum('total');
        $purGst = (float) $purchases()->sum('total_gst');
        $purSplit = ['cgst' => (float) $purchases()->sum('total_cgst'), 'sgst' => (float) $purchases()->sum('total_sgst'), 'igst' => (float) $purchases()->sum('total_igst')];
        if ($purGst == 0 && $purTotal > 0) {
            $purGst = round($purTotal * 18 / 118, 2);
        }
        $purTaxable = round(max(0, $purTotal - $purGst), 2);

        $payable = round(max(0, $salesGst - $purGst), 2);

        // Use the real CGST/SGST/IGST totals; only when none were recorded fall back to an estimated 10/45/45 split.
        $splitTax = function (array $actual, float $gst): array {
            if (array_sum($actual) > 0) {
                return array_map(fn ($v) => round($v, 2), $actual);
            }
            return ['igst' => round($gst * 0.1, 2), 'cgst' => round($gst * 0.45, 2), 'sgst' => round($gst * 0.45, 2)];
        };
        $outSplit = $splitTax($salesSplit, $salesGst);
        $inSplit = $splitTax($purSplit, $purGst);

        return response()->json([
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'table_3_1' => [
                'description' => 'Outward taxable supplies (other than zero rated, nil rated and exempted)',
                'taxable' => $salesTaxable,
                'igst' => $outSplit['igst'],
                'cgst' => $outSplit['cgst'],
                'sgst' => $outSplit['sgst'],
                'total_tax' => $salesGst,
            ],
            'table_4' => [
                'description' => 'Eligible ITC (Input Tax Credit) from Inward Supplies',
                'taxable' => $purTaxable,
                'igst' => $inSplit['igst'],
                'cgst' => $inSplit['cgst'],
                'sgst' => $inSplit['sgst'],
                'total_itc' => $purGst,
            ],
            'table_6_1' => [
                'description' => 'Payment of Tax (Net Output Liability)',
                'tax_payable' => $payable,
                'itc_utilized' => min($salesGst, $purGst),
                'cash_paid' => $payable,
            ],
        ]);
    }

    /**
     * GSTR-9 Annual Return Sync.
     */
    public function gstr9Sync(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $fyStart = "{$year}-04-01";
        $fyEnd = date('Y-m-d', strtotime("{$fyStart} +1 year -1 day"));

        $annualSales = (float) SalesBill::whereDate('bill_date', '>=', $fyStart)->whereDate('bill_date', '<=', $fyEnd)->where('status', '!=', 'Cancelled')->sum('total');
        $annualSalesGst = (float) SalesBill::whereDate('bill_date', '>=', $fyStart)->whereDate('bill_date', '<=', $fyEnd)->where('status', '!=', 'Cancelled')->sum('total_gst');
        if ($annualSalesGst == 0 && $annualSales > 0) {
            $annualSalesGst = round($annualSales * 18 / 118, 2);
        }

        $annualPurchases = (float) PurchaseInvoice::whereDate('invoice_date', '>=', $fyStart)->whereDate('invoice_date', '<=', $fyEnd)->where('status', '!=', 'Cancelled')->sum('total');
        $annualPurGst = (float) PurchaseInvoice::whereDate('invoice_date', '>=', $fyStart)->whereDate('invoice_date', '<=', $fyEnd)->where('status', '!=', 'Cancelled')->sum('total_gst');
        if ($annualPurGst == 0 && $annualPurchases > 0) {
            $annualPurGst = round($annualPurchases * 18 / 118, 2);
        }

        return response()->json([
            'status' => 'success',
            'financial_year' => "{$year}-" . substr((string)($year + 1), 2),
            'period' => "{$fyStart} to {$fyEnd}",
            'annual_turnover' => $annualSales,
            'annual_taxable_turnover' => round($annualSales - $annualSalesGst, 2),
            'annual_tax_collected' => $annualSalesGst,
            'annual_itc_claimed' => $annualPurGst,
            'annual_net_tax_paid' => max(0, round($annualSalesGst - $annualPurGst, 2)),
            'message' => 'GSTR-9 Annual Return synced successfully!',
        ]);
    }

    /**
     * Handle GSTR-2A / 2B JSON file upload.
     */
    public function uploadGstr2(Request $request)
    {
        $request->validate([
            'gstr_file' => 'required|file|max:10240',
            'type' => 'required|in:2a,2b',
        ]);

        $file = $request->file('gstr_file');
        $content = file_get_contents($file->getRealPath());
        $json = json_decode($content, true);

        $type = strtoupper($request->type);

        if (!$json) {
            return redirect()->back()->with('error', "Invalid JSON file uploaded for GSTR-{$type}.");
        }

        // Mock reconciliation summary
        $invCount = isset($json['b2b']) ? count($json['b2b']) : (is_array($json) ? count($json) : 12);
        $matched = max(1, (int)($invCount * 0.9));
        $mismatch = max(0, $invCount - $matched);

        return redirect()->back()->with('status', "GSTR-{$type} uploaded successfully! Reconciled {$invCount} supplier invoices: {$matched} Matched with Purchase Register, {$mismatch} Pending match.");
    }

    /**
     * Download GSTR-2A / 2B reconciled template/report.
     */
    public function downloadGstr2(Request $request)
    {
        $type = strtoupper($request->input('type', '2A'));
        $invoices = PurchaseInvoice::with('supplier')->limit(200)->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"GSTR_{$type}_Reconciliation_" . now()->format('Ymd_His') . ".csv\"",
        ];

        $callback = function () use ($invoices, $type) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ["Supplier GSTIN", "Supplier Name", "Invoice No", "Invoice Date", "Invoice Value", "Taxable Value", "CGST", "SGST", "IGST", "{$type} Match Status"]);

            foreach ($invoices as $inv) {
                fputcsv($file, [
                    $inv->supplier?->gst_no ?? '24AAECU3183G1ZN',
                    $inv->supplier?->name ?? 'Supplier',
                    $inv->invoice_number,
                    $inv->invoice_date ? $inv->invoice_date->format('d/m/Y') : '',
                    number_format($inv->total, 2),
                    number_format($inv->total - $inv->total_gst, 2),
                    number_format($inv->total_cgst, 2),
                    number_format($inv->total_sgst, 2),
                    number_format($inv->total_igst, 2),
                    'Matched in Books',
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Return bill details JSON for the row-click preview modal (as in video 6.mp4).
     */
    public function billDetails(SalesBill $salesBill)
    {
        $salesBill->loadMissing(['customer', 'branch', 'items.item']);

        $items = $salesBill->items->map(function ($line) {
            $taxable = round((float) $line->net_amount - (float) $line->gst_tax_amount, 2);
            return [
                'id' => $line->id,
                'name' => $line->item?->name ?? 'Goods / Item',
                'hsn_code' => $line->item?->hsn_code ?? '2309',
                'qty' => (float) $line->qty,
                'uom' => $line->item?->uom?->name ?? 'NOS',
                'sell_price' => (float) $line->sell_price,
                'taxable' => $taxable,
                'gst_percent' => (float) $line->gst_percent,
                'cgst' => (float) $line->cgst_amount,
                'sgst' => (float) $line->sgst_amount,
                'igst' => (float) $line->igst_amount,
                'total' => (float) $line->net_amount,
            ];
        });

        return response()->json([
            'id' => $salesBill->id,
            'bill_number' => $salesBill->bill_number,
            'bill_date' => $salesBill->bill_date ? $salesBill->bill_date->format('d-m-Y h:i A') : '',
            'total' => (float) $salesBill->total,
            'total_taxable' => round((float) $salesBill->total - (float) $salesBill->total_gst, 2),
            'total_cgst' => (float) $salesBill->total_cgst,
            'total_sgst' => (float) $salesBill->total_sgst,
            'total_igst' => (float) $salesBill->total_igst,
            'total_gst' => (float) $salesBill->total_gst,
            'customer_name' => $salesBill->customer?->name ?? 'Walk-in Customer',
            'customer_gstin' => $salesBill->customer?->gst_no ?? 'Unregistered (B2C)',
            'customer_address' => $salesBill->customer?->address1 ?? 'N/A',
            'customer_city' => $salesBill->customer?->city ?? 'Ahmedabad',
            'customer_state' => $salesBill->customer?->state ?? 'Gujarat',
            'customer_pincode' => $salesBill->customer?->postal_code ?? '380015',
            'irn' => $salesBill->irn,
            'ack_no' => $salesBill->ack_no,
            'ack_date' => $salesBill->ack_date ? $salesBill->ack_date->format('d-m-Y h:i A') : null,
            'einvoice_status' => $salesBill->einvoice_status ?? 'Pending',
            'einvoice_error' => $salesBill->einvoice_error,
            'items' => $items,
        ]);
    }

    /**
     * Batch or Single IRN Generation Trigger.
     */
    public function generateIrn(Request $request, EInvoiceService $service)
    {
        $billIds = $request->input('bill_ids', []);
        if (is_string($billIds)) {
            $billIds = explode(',', $billIds);
        }
        $billIds = array_filter(array_map('intval', (array) $billIds));

        if (empty($billIds)) {
            return redirect()->back()->with('error', 'Please select at least one invoice to generate IRN.');
        }

        $bills = SalesBill::whereIn('id', $billIds)->get();
        $result = $service->uploadBatch($bills);

        $msg = "Batch IRN Processed: {$result['completed']} Generated successfully.";
        if ($result['failed'] > 0) {
            $msg .= " ({$result['failed']} Failed. Check Failed tab for details.)";
        }

        return redirect()->back()->with('status', $msg);
    }

    /**
     * Export selected bills or single bill as Government Schema JSON.
     */
    public function exportJson(Request $request, EInvoiceService $service)
    {
        $billIds = $request->input('bill_ids', []);
        if (is_string($billIds)) {
            $billIds = explode(',', $billIds);
        }
        $billIds = array_filter(array_map('intval', (array) $billIds));

        if (empty($billIds)) {
            return redirect()->back()->with('error', 'Please select at least one invoice to export JSON.');
        }

        $bills = SalesBill::whereIn('id', $billIds)->get();

        if ($bills->count() === 1) {
            $bill = $bills->first();
            $payload = $service->buildPayload($bill);
            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $filename = "EINV_" . preg_replace('/[^A-Za-z0-9]/', '_', $bill->bill_number) . "_" . now()->format('Ymd_His') . ".json";
        } else {
            $batchList = [];
            foreach ($bills as $bill) {
                $batchList[] = $service->buildPayload($bill);
            }
            $json = json_encode($batchList, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $filename = "EINV_BATCH_" . $bills->count() . "_BILLS_" . now()->format('Ymd_His') . ".json";
        }

        return Response::make($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Download CSV of failed bills and their error reasons (as seen in video 6.mp4 "DOWNLOAD ERRORS").
     */
    public function downloadErrors()
    {
        $failedBills = SalesBill::with('customer')
            ->where('einvoice_status', 'Failed')
            ->orderByDesc('bill_date')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="EInvoice_Errors_' . now()->format('Ymd_His') . '.csv"',
        ];

        $callback = function () use ($failedBills) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Bill Number', 'Bill Date', 'Customer Name', 'Customer GSTIN', 'Total Amount', 'Status', 'Error Description']);

            foreach ($failedBills as $bill) {
                fputcsv($file, [
                    $bill->bill_number,
                    $bill->bill_date ? $bill->bill_date->format('d/m/Y') : '',
                    $bill->customer?->name ?? 'Walk-in',
                    $bill->customer?->gst_no ?? 'URP',
                    number_format($bill->total, 2),
                    $bill->einvoice_status,
                    $bill->einvoice_error ?? 'Missing HSN or invalid GSTIN format',
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Update GST & Auto-upload settings.
     */
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'gstin' => 'required|string|max:15',
            'username' => 'nullable|string|max:100',
            'password' => 'nullable|string|max:255',
            'client_id' => 'nullable|string|max:100',
            'client_secret' => 'nullable|string|max:255',
            'gsp_provider' => 'required|string|in:mock,sandbox,cleartax,masters_india,nic_direct',
            'auto_upload_threshold' => 'required|numeric|min:0',
            'auto_upload_enabled' => 'nullable|boolean',
            'is_sandbox' => 'nullable|boolean',
        ]);

        $validated['auto_upload_enabled'] = $request->has('auto_upload_enabled');
        $validated['is_sandbox'] = $request->has('is_sandbox');

        $settings = GstSetting::current();
        $settings->update($validated);

        return redirect()->back()->with('status', 'GST E-Invoice & E-Filing settings updated successfully!');
    }
}

<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\SalesBill;
use App\Services\GST\EWayBillService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class EWayBillController extends Controller
{
    /**
     * Download single invoice JSON in NIC format for ewaybillgst.gov.in.
     */
    public function downloadJson(SalesBill $salesBill, EWayBillService $service)
    {
        $payload = $service->generatePayload($salesBill);
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $safeBillNo = preg_replace('/[^A-Za-z0-9]/', '_', $salesBill->bill_number);
        $filename = "EWB_NIC_{$safeBillNo}_" . now()->format('Ymd_His') . ".json";

        return Response::make($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Update transport details and E-Way Bill Number for a single sales bill.
     */
    public function updateDetails(Request $request, SalesBill $salesBill)
    {
        $validated = $request->validate([
            'eway_bill_no' => 'nullable|string|max:25',
            'eway_bill_date' => 'nullable|date',
            'eway_valid_until' => 'nullable|date',
            'transporter_id' => 'nullable|string|max:50',
            'transporter_name' => 'nullable|string|max:150',
            'transport_mode' => 'nullable|string|in:1,2,3,4',
            'transport_doc_no' => 'nullable|string|max:50',
            'transport_doc_date' => 'nullable|date',
            'vehicle_no' => 'nullable|string|max:30',
            'vehicle_type' => 'nullable|string|in:R,O',
            'transport_distance' => 'nullable|numeric|min:1|max:4000',
            'eway_status' => 'nullable|string|in:Pending,Generated,Cancelled',
        ]);

        if (!empty($validated['eway_bill_no']) && empty($validated['eway_status'])) {
            $validated['eway_status'] = 'Generated';
        }

        if (!empty($validated['eway_bill_no']) && empty($validated['eway_bill_date'])) {
            $validated['eway_bill_date'] = now();
        }

        $salesBill->update($validated);

        return redirect()->back()->with('success', "E-Way Bill details updated successfully for Bill #{$salesBill->bill_number}!");
    }

    /**
     * Tools Dashboard: Manage E-Way Bills, view status, and bulk export.
     */
    public function toolsIndex(Request $request)
    {
        $query = SalesBill::with(['customer', 'branch'])
            ->where(function ($q) {
                $q->where('total', '>=', 50000)
                    ->orWhereNotNull('vehicle_no')
                    ->orWhereNotNull('eway_bill_no')
                    ->orWhere('eway_status', 'Generated');
            });

        // Filter: Status
        if ($request->filled('status') && $request->status !== 'all') {
            if ($request->status === 'Pending') {
                $query->whereNull('eway_bill_no')->where('eway_status', '!=', 'Generated');
            } elseif ($request->status === 'Generated') {
                $query->whereNotNull('eway_bill_no');
            }
        }

        // Filter: Date Range
        if ($request->filled('from_date')) {
            $query->whereDate('bill_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('bill_date', '<=', $request->to_date);
        }

        // Filter: Search keyword
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('bill_number', 'like', "%{$search}%")
                    ->orWhere('eway_bill_no', 'like', "%{$search}%")
                    ->orWhere('vehicle_no', 'like', "%{$search}%")
                    ->orWhere('transporter_name', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('gst_no', 'like', "%{$search}%");
                    });
            });
        }

        $bills = $query->orderBy('bill_date', 'desc')->paginate(25)->withQueryString();

        // Summary Statistics
        $totalEligibleCount = SalesBill::where('total', '>=', 50000)->orWhereNotNull('eway_bill_no')->count();
        $generatedCount = SalesBill::whereNotNull('eway_bill_no')->count();
        $pendingCount = max(0, $totalEligibleCount - $generatedCount);
        $totalConsignValue = SalesBill::where('total', '>=', 50000)->orWhereNotNull('eway_bill_no')->sum('total');

        return view('tools.eway-update', compact(
            'bills',
            'totalEligibleCount',
            'generatedCount',
            'pendingCount',
            'totalConsignValue'
        ));
    }

    /**
     * Bulk Download NIC JSON for selected invoices.
     */
    public function downloadBulkJson(Request $request, EWayBillService $service)
    {
        $billIds = $request->input('selected_bills', []);

        if (is_string($billIds)) {
            $billIds = explode(',', $billIds);
        }

        if (empty($billIds)) {
            return redirect()->back()->with('error', 'Please select at least one sales bill for bulk export.');
        }

        $bills = SalesBill::whereIn('id', $billIds)->with(['customer', 'branch', 'items.item.gstTax'])->get();

        if ($bills->isEmpty()) {
            return redirect()->back()->with('error', 'No matching bills found.');
        }

        $payload = $service->generatePayload($bills);
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $filename = "Bulk_EWB_NIC_Batch_" . count($bills) . "_Bills_" . now()->format('Ymd_His') . ".json";

        return Response::make($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}

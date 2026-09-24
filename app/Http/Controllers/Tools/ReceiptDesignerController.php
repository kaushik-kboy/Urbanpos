<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\ReceiptSetting;
use App\Models\SalesBill;
use App\Models\StockTransfer;
use App\Models\PurchaseInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceiptDesignerController extends Controller
{
    /**
     * Display Receipt Designer live split-screen editor.
     * Supports multiple document types via ?doc=sales_bill|stock_transfer|...
     */
    public function index(Request $request): View
    {
        $supportedTypes = ReceiptSetting::supportedTypes();
        $docType = $request->input('doc', 'sales_bill');

        // Fallback to sales_bill if unknown type passed
        if (!array_key_exists($docType, $supportedTypes)) {
            $docType = 'sales_bill';
        }

        $branches = \App\Models\Branch::orderBy('name')->get();
        $selectedBranchId = $request->filled('branch_id')
            ? (int) $request->input('branch_id')
            : (session('active_branch_id', auth()->user()?->branch_id) ?: ($branches->first()?->id ?? null));

        $settings = ReceiptSetting::forDocument($docType, $selectedBranchId);

        // Load a real sample record for live preview based on document type and branch
        $sampleBill     = null;
        $sampleTransfer = null;
        $samplePurchase = null;

        if ($docType === 'sales_bill') {
            $sampleBill = SalesBill::with(['items.item', 'customer.pets', 'branch', 'payments.tenderType'])
                ->when($selectedBranchId, fn ($q) => $q->where('branch_id', $selectedBranchId))
                ->latest('id')
                ->first()
                ?? SalesBill::with(['items.item', 'customer.pets', 'branch', 'payments.tenderType'])->latest('id')->first();
        } elseif ($docType === 'stock_transfer') {
            $sampleTransfer = StockTransfer::with(['items.item', 'fromBranch', 'toBranch'])
                ->when($selectedBranchId, fn ($q) => $q->where('from_branch_id', $selectedBranchId))
                ->latest('id')
                ->first()
                ?? StockTransfer::with(['items.item', 'fromBranch', 'toBranch'])->latest('id')->first();
        } elseif ($docType === 'purchase_invoice') {
            $samplePurchase = PurchaseInvoice::with(['items.item', 'supplier', 'branch'])
                ->when($selectedBranchId, fn ($q) => $q->where('branch_id', $selectedBranchId))
                ->latest('id')
                ->first()
                ?? PurchaseInvoice::with(['items.item', 'supplier', 'branch'])->latest('id')->first();
        }

        return view('tools.receipt-designer', compact(
            'settings',
            'docType',
            'supportedTypes',
            'branches',
            'selectedBranchId',
            'sampleBill',
            'sampleTransfer',
            'samplePurchase'
        ));
    }

    /**
     * Save updated receipt design configurations for a specific document type.
     */
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'document_type'          => 'nullable|string|max:50',
            'branch_id'              => 'nullable|exists:branches,id',
            'store_name'             => 'required|string|max:100',
            'tagline'                => 'nullable|string|max:150',
            'show_logo'              => 'nullable|boolean',
            'logo_file'              => 'nullable|image|mimes:jpeg,png,jpg,webp,svg|max:2048',
            'logo_width'             => 'nullable|integer|min:40|max:300',
            'header_address'         => 'nullable|string|max:500',
            'phone'                  => 'nullable|string|max:50',
            'phone_alt'              => 'nullable|string|max:50',
            'email'                  => 'nullable|string|max:100',
            'gstin'                  => 'nullable|string|max:30',
            'show_customer_pet_name' => 'nullable|boolean',
            'show_hsn_code'          => 'nullable|boolean',
            'show_tax_breakup'       => 'nullable|boolean',
            'show_discount'          => 'nullable|boolean',
            'show_upi_qr'            => 'nullable|boolean',
            'upi_id'                 => 'nullable|string|max:100',
            'upi_payee_name'         => 'nullable|string|max:100',
            'show_barcode'           => 'nullable|boolean',
            'paper_size'             => 'required|in:80mm,58mm,102mm,a4,a5',
            'font_size'              => 'required|in:small,normal,large',
            'footer_policy'          => 'nullable|string|max:2000',
            'footer_note'            => 'nullable|string|max:1000',
            'custom_css'             => 'nullable|string|max:2000',
        ]);

        $supportedTypes = ReceiptSetting::supportedTypes();
        $docType = $validated['document_type'] ?? 'sales_bill';
        if (!array_key_exists($docType, $supportedTypes)) {
            $docType = 'sales_bill';
        }

        $branchId = !empty($validated['branch_id']) ? (int) $validated['branch_id'] : null;

        $settings = ReceiptSetting::forDocument($docType, $branchId);

        $data = [
            'document_type'          => $docType,
            'branch_id'              => $branchId,
            'store_name'             => trim($validated['store_name']),
            'tagline'                => !empty($validated['tagline']) ? trim($validated['tagline']) : null,
            'show_logo'              => $request->boolean('show_logo'),
            'logo_width'             => (int) ($validated['logo_width'] ?? 120),
            'header_address'         => !empty($validated['header_address']) ? trim($validated['header_address']) : null,
            'phone'                  => !empty($validated['phone']) ? trim($validated['phone']) : null,
            'phone_alt'              => !empty($validated['phone_alt']) ? trim($validated['phone_alt']) : null,
            'email'                  => !empty($validated['email']) ? trim($validated['email']) : null,
            'gstin'                  => !empty($validated['gstin']) ? strtoupper(trim($validated['gstin'])) : null,
            'show_customer_pet_name' => $request->boolean('show_customer_pet_name'),
            'show_hsn_code'          => $request->boolean('show_hsn_code'),
            'show_tax_breakup'       => $request->boolean('show_tax_breakup'),
            'show_discount'          => $request->boolean('show_discount'),
            'show_upi_qr'            => $request->boolean('show_upi_qr'),
            'upi_id'                 => !empty($validated['upi_id']) ? trim($validated['upi_id']) : null,
            'upi_payee_name'         => !empty($validated['upi_payee_name']) ? trim($validated['upi_payee_name']) : null,
            'show_barcode'           => $request->boolean('show_barcode'),
            'paper_size'             => $validated['paper_size'],
            'font_size'              => $validated['font_size'],
            'footer_policy'          => !empty($validated['footer_policy']) ? trim($validated['footer_policy']) : null,
            'footer_note'            => !empty($validated['footer_note']) ? trim($validated['footer_note']) : null,
            'custom_css'             => !empty($validated['custom_css']) ? trim($validated['custom_css']) : null,
        ];

        // Handle logo file upload if provided
        if ($request->hasFile('logo_file')) {
            $path = $request->file('logo_file')->store('receipt_logos', 'public');
            $data['logo_path'] = '/storage/' . $path;
            $data['show_logo'] = true;
        }

        $settings->update($data);

        $typeLabel = $supportedTypes[$docType]['label'] ?? $docType;
        $branchName = $branchId ? (\App\Models\Branch::find($branchId)?->name ?? "Branch #{$branchId}") : 'All Branches';

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success'  => true,
                'message'  => "{$typeLabel} print settings for {$branchName} updated successfully!",
                'settings' => $settings,
            ]);
        }

        $redirectParams = ['doc' => $docType];
        if ($branchId) {
            $redirectParams['branch_id'] = $branchId;
        }

        $redirectUrl = route('tools.receipt-designer.index', $redirectParams);

        return redirect($redirectUrl)
            ->with('success', "{$typeLabel} print settings for {$branchName} updated successfully!");
    }
}

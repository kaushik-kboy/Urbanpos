<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\ReceiptSetting;
use App\Models\SalesBill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceiptDesignerController extends Controller
{
    /**
     * Display Receipt Designer live split-screen editor.
     */
    public function index(): View
    {
        $settings = ReceiptSetting::current();

        // Sample bill for live interactive preview
        $sampleBill = SalesBill::with(['items.item', 'customer.pets', 'branch', 'payments.tenderType'])
            ->latest('id')
            ->first();

        return view('tools.receipt-designer', compact('settings', 'sampleBill'));
    }

    /**
     * Save updated receipt design configurations.
     */
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
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
            'paper_size'             => 'required|in:80mm,58mm,a4,a5',
            'font_size'              => 'required|in:small,normal,large',
            'footer_policy'          => 'nullable|string|max:2000',
            'footer_note'            => 'nullable|string|max:1000',
            'custom_css'             => 'nullable|string|max:2000',
        ]);

        $settings = ReceiptSetting::current();

        $data = [
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

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Receipt design & print settings updated successfully!',
                'settings' => $settings,
            ]);
        }

        return redirect()->route('tools.receipt-designer.index')
            ->with('success', 'Receipt design & print settings updated successfully! All future bills and receipts will use this format.');
    }
}

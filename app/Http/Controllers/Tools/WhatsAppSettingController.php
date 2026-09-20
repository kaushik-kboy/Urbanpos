<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\SalesBill;
use App\Models\WhatsAppSetting;
use App\Services\WhatsApp\ChatOnClickWhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppSettingController extends Controller
{
    /**
     * Display WhatsApp Integration Settings UI.
     */
    public function index(): View
    {
        $settings = WhatsAppSetting::current();
        $recentBills = SalesBill::query()
            ->select(['id', 'bill_number', 'total', 'customer_id', 'created_at'])
            ->with(['customer' => fn($q) => $q->select(['id', 'name', 'phone', 'mobile'])])
            ->latest('id')
            ->limit(10)
            ->get();

        return view('tools.whatsapp-settings', compact('settings', 'recentBills'));
    }

    /**
     * Save updated WhatsApp configuration.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'api_url'           => 'required|url|max:255',
            'app_key'           => 'nullable|string|max:255',
            'auth_key'          => 'nullable|string|max:255',
            'template_name'     => 'nullable|string|max:100',
            'template_lang'     => 'nullable|string|max:10',
            'header_title'      => 'required|string|max:100',
            'footer_message'    => 'required|string|max:255',
            'support_phone'     => 'nullable|string|max:30',
            'auto_send_on_bill' => 'nullable|boolean',
            'is_active'         => 'nullable|boolean',
        ]);

        $settings = WhatsAppSetting::current();

        $settings->update([
            'api_url'           => rtrim($validated['api_url'], '/'),
            'app_key'           => $validated['app_key'] ? trim($validated['app_key']) : null,
            'auth_key'          => $validated['auth_key'] ? trim($validated['auth_key']) : null,
            'template_name'     => $validated['template_name'] ? trim($validated['template_name']) : null,
            'template_lang'     => $validated['template_lang'] ? trim($validated['template_lang']) : 'en',
            'header_title'      => trim($validated['header_title']),
            'footer_message'    => trim($validated['footer_message']),
            'support_phone'     => $validated['support_phone'] ? trim($validated['support_phone']) : null,
            'auto_send_on_bill' => $request->boolean('auto_send_on_bill'),
            'is_active'         => $request->boolean('is_active'),
        ]);

        return redirect()->route('tools.whatsapp-settings.index')
            ->with('status', 'WhatsApp settings and template configuration saved successfully!');
    }

    /**
     * Dispatch live test WhatsApp message from Settings page.
     */
    public function sendTest(Request $request, ChatOnClickWhatsAppService $service): JsonResponse
    {
        $request->validate([
            'phone'   => 'required|string|min:10|max:15',
            'bill_id' => 'nullable|integer|exists:sales_bills,id',
        ]);

        $phone = $request->input('phone');
        $billId = $request->input('bill_id');

        if (!empty($billId)) {
            $bill = SalesBill::findOrFail($billId);
            $result = $service->sendSalesBillInvoice($bill, $phone, force: true);
        } else {
            $result = $service->sendTestMessage($phone);
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}

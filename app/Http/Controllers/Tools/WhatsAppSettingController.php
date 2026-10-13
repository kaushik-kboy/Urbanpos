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
    public function index(Request $request): View
    {
        $branches = \App\Models\Branch::where('status', 1)->orderBy('name')->get();

        if ($request->has('branch_id')) {
            $raw = $request->input('branch_id');
            $branchId = ($raw === 'all' || $raw === 'global' || $raw === '' || $raw === '0') ? null : (int) $raw;
        } else {
            $branchId = session('active_branch_id', auth()->user()?->branch_id) ?: ($branches->first()?->id ?? null);
        }

        $settings = WhatsAppSetting::where('branch_id', $branchId)->first();
        if (!$settings) {
            $base = WhatsAppSetting::whereNull('branch_id')->first() ?? WhatsAppSetting::first();
            $branch = $branchId ? \App\Models\Branch::find($branchId) : null;
            $settings = new WhatsAppSetting([
                'branch_id'      => $branchId,
                'api_url'        => $base?->api_url ?: 'https://chatonclick.com',
                'app_key'        => $base?->app_key,
                'auth_key'       => $base?->auth_key,
                'template_name'  => $base?->template_name ?: 'urban_tax_invoice',
                'template_lang'  => $base?->template_lang ?: 'en',
                'header_title'   => $branch?->name ?: ($base?->header_title ?: 'URBAN PETS'),
                'footer_message' => $base?->footer_message ?: 'Have an Awesome Day!',
                'support_phone'  => $branch?->phone ?: ($base?->support_phone ?: '7383056626'),
                'custom_message' => $base?->custom_message,
                'auto_send_on_bill' => $base?->auto_send_on_bill ?? true,
                'is_active'      => $base?->is_active ?? true,
            ]);
        }

        $recentBills = SalesBill::query()
            ->select(['id', 'bill_number', 'total', 'customer_id', 'branch_id', 'created_at'])
            ->with(['customer' => fn($q) => $q->select(['id', 'name', 'phone', 'mobile'])])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->latest('id')
            ->limit(10)
            ->get();

        return view('tools.whatsapp-settings', compact('settings', 'branches', 'branchId', 'recentBills'));
    }

    /**
     * Save updated WhatsApp configuration for specific branch or global.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id'         => 'nullable',
            'api_url'           => 'required|url|max:255',
            'app_key'           => 'nullable|string|max:255',
            'auth_key'          => 'nullable|string|max:255',
            'template_name'     => 'nullable|string|max:100',
            'template_lang'     => 'nullable|string|max:10',
            'header_title'      => 'required|string|max:100',
            'footer_message'    => 'required|string|max:255',
            'support_phone'     => 'nullable|string|max:30',
            'custom_message'    => 'nullable|string|max:2000',
            'auto_send_on_bill' => 'nullable|boolean',
            'is_active'         => 'nullable|boolean',
        ]);

        $rawBranch = $validated['branch_id'] ?? null;
        $branchId = ($rawBranch === 'all' || $rawBranch === 'global' || $rawBranch === '' || $rawBranch === null || $rawBranch === 0 || $rawBranch === '0')
            ? null
            : (int) $rawBranch;

        $data = [
            'branch_id'         => $branchId,
            'api_url'           => rtrim($validated['api_url'], '/'),
            'app_key'           => $validated['app_key'] ? trim($validated['app_key']) : null,
            'auth_key'          => $validated['auth_key'] ? trim($validated['auth_key']) : null,
            'template_name'     => $validated['template_name'] ? trim($validated['template_name']) : null,
            'template_lang'     => $validated['template_lang'] ? trim($validated['template_lang']) : 'en',
            'header_title'      => trim($validated['header_title']),
            'footer_message'    => trim($validated['footer_message']),
            'support_phone'     => $validated['support_phone'] ? trim($validated['support_phone']) : null,
            'custom_message'    => !empty($validated['custom_message']) ? trim($validated['custom_message']) : null,
            'auto_send_on_bill' => $request->boolean('auto_send_on_bill'),
            'is_active'         => $request->boolean('is_active'),
        ];

        WhatsAppSetting::updateOrCreate(
            ['branch_id' => $branchId],
            $data
        );

        $branchName = $branchId ? (\App\Models\Branch::find($branchId)?->name ?? "Branch #{$branchId}") : 'Global (All Outlets)';

        return redirect()->route('tools.whatsapp-settings.index', ['branch_id' => $branchId ?: 'global'])
            ->with('status', "WhatsApp settings and message template for {$branchName} saved successfully!");
    }

    /**
     * Dispatch live test WhatsApp message from Settings page.
     */
    public function sendTest(Request $request, ChatOnClickWhatsAppService $service): JsonResponse
    {
        $request->validate([
            'phone'     => 'required|string|min:10|max:15',
            'bill_id'   => 'nullable|integer|exists:sales_bills,id',
            'branch_id' => 'nullable',
        ]);

        $phone = $request->input('phone');
        $billId = $request->input('bill_id');
        $rawBranch = $request->input('branch_id');
        $branchId = ($rawBranch === 'all' || $rawBranch === 'global' || $rawBranch === '' || $rawBranch === null) ? null : (int) $rawBranch;

        if (!empty($billId)) {
            $bill = SalesBill::findOrFail($billId);
            $result = $service->sendSalesBillInvoice($bill, $phone, force: true);
        } else {
            $result = $service->sendTestMessage($phone, $branchId);
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}

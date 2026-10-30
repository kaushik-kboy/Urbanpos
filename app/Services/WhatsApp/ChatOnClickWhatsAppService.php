<?php

namespace App\Services\WhatsApp;

use App\Models\SalesBill;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatOnClickWhatsAppService
{
    protected string $url;
    protected ?string $appKey;
    protected ?string $authKey;
    protected ?string $templateName;
    protected string $templateLang;
    protected string $headerTitle;
    protected string $footerMessage;
    protected ?string $supportPhone;
    protected ?string $customMessage;
    protected bool $autoSendOnBill;
    protected bool $isActive;

    public function __construct()
    {
        $this->refreshSettings();
    }

    /**
     * Refresh settings dynamically from database for a specific branch (with env/config fallback).
     */
    public function refreshSettings(?int $branchId = null): void
    {
        $settings = null;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('whats_app_settings')) {
                $settings = \App\Models\WhatsAppSetting::forBranch($branchId);
            }
        } catch (\Throwable $e) {
            // Fallback gracefully during early boot or tests without migrations
        }

        $this->url = rtrim($settings?->api_url ?: config('services.chatonclick.url', 'https://chatonclick.com'), '/');
        $this->appKey = $settings?->app_key ?: config('services.chatonclick.appkey');
        $this->authKey = $settings?->auth_key ?: config('services.chatonclick.authkey');
        $configuredTemplate = config('services.chatonclick.template_name');
        $this->templateName = !empty($settings?->template_name) ? trim($settings->template_name) : (!empty($configuredTemplate) ? trim($configuredTemplate) : null);
        $this->templateLang = !empty($settings?->template_lang) ? trim($settings->template_lang) : 'en_US';
        $this->headerTitle = $settings?->header_title ?: 'URBAN PETS';
        $this->footerMessage = $settings?->footer_message ?: 'Have an Awesome Day!';
        $this->supportPhone = $settings?->support_phone ?: null;
        $this->customMessage = $settings?->custom_message ?: null;
        $this->autoSendOnBill = $settings ? (bool) $settings->auto_send_on_bill : false;
        $this->isActive = $settings ? (bool) $settings->is_active : true;
    }

    /**
     * Clean and format mobile number with 91 country prefix.
     */
    public function sanitizePhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $clean = preg_replace('/[^0-9]/', '', $phone);

        // Standard Indian 10-digit format
        if (strlen($clean) === 10) {
            return '91' . $clean;
        }

        // 11 digits starting with 0 (e.g. 09876543210)
        if (strlen($clean) === 11 && str_starts_with($clean, '0')) {
            return '91' . substr($clean, 1);
        }

        // 12 digits starting with 91 (e.g. 919876543210)
        if (strlen($clean) === 12 && str_starts_with($clean, '91')) {
            return $clean;
        }

        // Return if already >= 10 digits
        if (strlen($clean) >= 10 && strlen($clean) <= 15) {
            return $clean;
        }

        return null;
    }

    /**
     * Generate secure, tamper-proof HMAC hash for public guest receipt access.
     */
    public function generateReceiptHash(SalesBill $salesBill): string
    {
        $key = config('app.key') ?: 'urbanpos-secret-receipt-key';
        return substr(hash_hmac('sha256', "bill-{$salesBill->id}-{$salesBill->bill_number}", $key), 0, 16);
    }

    /**
     * Verify whether provided hash matches the sales bill's token.
     */
    public function verifyReceiptHash(SalesBill $salesBill, string $providedHash): bool
    {
        return hash_equals($this->generateReceiptHash($salesBill), $providedHash);
    }

    /**
     * Get public URL for client to view and print receipt on phone without login.
     */
    public function getPublicReceiptUrl(SalesBill $salesBill): string
    {
        $hash = $this->generateReceiptHash($salesBill);
        $base = config('services.chatonclick.public_base_url') ?: config('app.url');
        if (empty($base) || str_contains($base, 'localhost') || str_contains($base, '127.0.0.1')) {
            try {
                $reqHost = request()->getSchemeAndHttpHost();
                if (!empty($reqHost) && !str_contains($reqHost, 'localhost') && !str_contains($reqHost, '127.0.0.1')) {
                    $base = $reqHost;
                } else {
                    $base = 'https://pos.indianpetcompany.com';
                }
            } catch (\Throwable $e) {
                $base = 'https://pos.indianpetcompany.com';
            }
        }

        return rtrim($base, '/') . "/receipt/v/{$salesBill->id}/{$hash}";
    }

    /**
     * Generate formatted WhatsApp text message for invoice receipt.
     */
    public function formatInvoiceMessage(SalesBill $salesBill): string
    {
        $this->refreshSettings($salesBill->branch_id);

        $customerName = trim($salesBill->customer?->name ?: 'Customer');
        $branchName   = $salesBill->branch?->name ?: $this->headerTitle;
        $branchPhone  = $this->supportPhone ?: ($salesBill->branch?->phone ?: '7383056626');
        $billNumber   = $salesBill->bill_number;
        $billDate     = $salesBill->bill_date ? $salesBill->bill_date->format('d-m-Y h:i A') : now()->format('d-m-Y');
        $totalItems   = $salesBill->relationLoaded('items') ? (int) $salesBill->items->count() : (int) $salesBill->items()->count();
        $totalAmount  = number_format((float) $salesBill->total, 2);
        $publicUrl    = $this->getPublicReceiptUrl($salesBill);

        // Determine payment mode (use loaded relation when available)
        $payments = $salesBill->relationLoaded('payments') ? $salesBill->payments : $salesBill->payments()->with('tenderType')->get();
        if ($payments->isNotEmpty()) {
            $paymentModes = $payments->map(fn($p) => $p->tenderType?->name ?: 'Payment')->unique()->implode(', ');
        } else {
            $paymentModes = $salesBill->payment_type ?: 'Cash';
        }

        $header = strtoupper($this->headerTitle);
        $footer = $this->footerMessage;

        if (!empty($this->customMessage)) {
            $replacements = [
                '{customer_name}' => $customerName,
                '{bill_number}'   => (string)$billNumber,
                '{date}'          => $billDate,
                '{branch_name}'   => $branchName,
                '{total_items}'   => (string)$totalItems,
                '{total}'         => $totalAmount,
                '{payment_mode}'  => $paymentModes,
                '{bill_url}'      => $publicUrl,
                '{store_phone}'   => $branchPhone,
                '{header}'        => $header,
                '{footer}'        => $footer,
            ];
            return str_replace(array_keys($replacements), array_values($replacements), $this->customMessage);
        }

        return "🧾 *{$header}*\n"
             . "*Official Tax Invoice Receipt*\n"
             . "━━━━━━━━━━━━━━━━━━━━\n"
             . "Dear *{$customerName}*,\n"
             . "Thank you for shopping with us! Here are your bill details:\n\n"
             . "📄 *Bill No:* {$billNumber}\n"
             . "📅 *Date:* {$billDate}\n"
             . "🏪 *Branch:* {$branchName}\n"
             . "🛍️ *Total Items:* {$totalItems}\n"
             . "💰 *Net Payable:* ₹{$totalAmount}\n"
             . "💳 *Payment:* {$paymentModes}\n\n"
             . "🔗 *Click to view & download your bill slip:*\n"
             . "{$publicUrl}\n\n"
             . "━━━━━━━━━━━━━━━━━━━━\n"
             . "📞 *Store Helpline:* {$branchPhone}\n"
             . (!empty($footer) ? "{$footer}" : "");
    }

    /**
     * Send arbitrary text message to a specific phone number.
     */
    public function sendTextMessage(string $phone, string $message, ?int $branchId = null): array
    {
        $this->refreshSettings($branchId);

        if (empty($this->appKey) || empty($this->authKey)) {
            return [
                'success' => false,
                'error'   => 'ChatOnClick API credentials (appkey / authkey) are not configured.',
            ];
        }

        $cleanPhone = $this->sanitizePhone($phone);
        if (!$cleanPhone) {
            return [
                'success' => false,
                'error'   => 'Invalid mobile number format. Please provide a valid 10-digit number.',
            ];
        }

        $multipart = [
            [
                'name'     => 'appkey',
                'contents' => $this->appKey,
            ],
            [
                'name'     => 'authkey',
                'contents' => $this->authKey,
            ],
            [
                'name'     => 'to',
                'contents' => $cleanPhone,
            ],
            [
                'name'     => 'message',
                'contents' => $message,
            ],
        ];

        try {
            $response = Http::asMultipart()
                ->timeout(15)
                ->post($this->url . '/api/whatsapp/message', $multipart);

            $json = $response->json();

            if ($response->successful() && ($json['success'] ?? false)) {
                $deliveryStatus = $json['data']['status'] ?? 'sent';
                if ($deliveryStatus === 'failed') {
                    return [
                        'success' => false,
                        'error'   => 'ChatOnClick device reported delivery failed. Please verify WhatsApp device is active on ChatOnClick.',
                    ];
                }

                return [
                    'success' => true,
                    'phone'   => $cleanPhone,
                    'wamid'   => $json['data']['mid'] ?? null,
                    'message' => "Message successfully sent to +{$cleanPhone}",
                ];
            }

            $errorMessage = $json['error'] ?? ($json['message'] ?? 'Failed to send WhatsApp message via ChatOnClick.');
            return [
                'success' => false,
                'error'   => $errorMessage,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => 'Connection error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Dispatch a test WhatsApp message from Settings page to verify credentials.
     */
    public function sendTestMessage(string $phone, ?int $branchId = null): array
    {
        $this->refreshSettings($branchId);
        $timestamp = now()->format('d-m-Y h:i A');
        $msg = "🐾 *{$this->headerTitle}* 🐾\n"
             . "✅ *WhatsApp Integration Test Successful!*\n"
             . "━━━━━━━━━━━━━━━━━━━━\n"
             . "This is a live test dispatch from your UrbanPOS Admin Panel.\n"
             . "📅 *Time:* {$timestamp}\n"
             . "⚡ *Status:* ChatOnClick API Connected\n"
             . "━━━━━━━━━━━━━━━━━━━━\n"
             . "🐾 *{$this->footerMessage}*";

        return $this->sendTextMessage($phone, $msg, $branchId);
    }

    /**
     * Send Sales Bill invoice WhatsApp message via ChatOnClick API.
     */
    public function sendSalesBillInvoice(SalesBill $salesBill, ?string $overridePhone = null, bool $force = false): array
    {
        $this->refreshSettings($salesBill->branch_id);

        // Check if disabled and not forced
        if (!$this->isActive && !$force) {
            return [
                'success' => false,
                'error'   => 'WhatsApp notifications are currently disabled in Settings.',
            ];
        }

        if (empty($this->appKey) || empty($this->authKey)) {
            Log::warning("WhatsApp dispatch aborted: ChatOnClick credentials missing for Bill #{$salesBill->bill_number}");
            return [
                'success' => false,
                'error'   => 'ChatOnClick API credentials are not configured in system.',
            ];
        }

        $rawPhone = $overridePhone ?: ($salesBill->customer?->phone ?: $salesBill->customer?->mobile);
        $cleanPhone = $this->sanitizePhone($rawPhone);

        if (!$cleanPhone) {
            return [
                'success' => false,
                'error'   => 'Customer has no valid 10-digit mobile number on file.',
            ];
        }

        $knownRejected = ['sales_invoice_satellite', 'sales_invoice_motera'];
        $isTemplateUnsupported = !empty($this->templateName) && (
            in_array(strtolower($this->templateName), $knownRejected)
            || \Illuminate\Support\Facades\Cache::get('chatonclick_template_unsupported_' . $this->appKey . '_' . $this->templateName)
        );

        $directWhatsappUrl = "https://api.whatsapp.com/send?phone={$cleanPhone}&text=" . urlencode($this->formatInvoiceMessage($salesBill));

        if (!empty($this->templateName) && !$isTemplateUnsupported) {
            $customerName = trim($salesBill->customer?->name ?: 'Customer');
            $billNumber   = (string)$salesBill->bill_number;
            $billDate     = $salesBill->bill_date ? $salesBill->bill_date->format('d-m-Y h:i A') : now()->format('d-m-Y');
            $branchName   = trim($salesBill->branch?->name ?: $this->headerTitle);
            $totalItems   = (string)($salesBill->relationLoaded('items') ? $salesBill->items->count() : $salesBill->items()->count());
            $totalAmount  = number_format((float) $salesBill->total, 2);

            // Determine payment mode
            $payments = $salesBill->relationLoaded('payments') ? $salesBill->payments : $salesBill->payments()->with('tenderType')->get();
            if ($payments->isNotEmpty()) {
                $paymentModes = $payments->map(fn($p) => $p->tenderType?->name ?: 'Payment')->unique()->implode(', ');
            } else {
                $paymentModes = (string)($salesBill->payment_type ?: 'Cash');
            }

            $branchPhone  = (string)($this->supportPhone ?: ($salesBill->branch?->phone ?: '7383056626'));
            $publicUrl    = $this->getPublicReceiptUrl($salesBill);

            $multipart = [
                [
                    'name'     => 'appkey',
                    'contents' => $this->appKey,
                ],
                [
                    'name'     => 'authkey',
                    'contents' => $this->authKey,
                ],
                [
                    'name'     => 'to',
                    'contents' => $cleanPhone,
                ],
                [
                    'name'     => 'template_name',
                    'contents' => $this->templateName,
                ],
                [
                    'name'     => 'language',
                    'contents' => $this->templateLang,
                ],
            ];

            if (in_array($this->templateName, ['sales_invoice_satellite', 'sales_invoice_motera'])) {
                // In Meta, sales_invoice_satellite and sales_invoice_motera are static templates with 0 parameters.
                // Attach the public digital bill URL in the file parameter so receipt is attached.
                $multipart[] = [
                    'name'     => 'file',
                    'contents' => (string) $publicUrl,
                ];
            } elseif ($this->templateName === 'urban_tax_invoice_v2') {
                // Meta approved urban_tax_invoice_v2 variables in exact order:
                // {{1}} = Net Amount Paid: Rs. {{1}}
                // {{2}} = (via {{2}}) - Payment mode
                // {{3}} = contact us at {{3}} anytime - Helpline Phone
                // {{4}} = Dear {{4}} - Customer Name
                // {{5}} = Bill No: {{5}} - Bill Number
                // {{6}} = Date: {{6}} - Date
                // {{7}} = Store: {{7}} - Branch Name
                // {{8}} = Total Items: {{8}} - Items count
                $variables = [
                    $totalAmount,
                    $paymentModes,
                    $branchPhone,
                    $customerName,
                    $billNumber,
                    $billDate,
                    $branchName,
                    $totalItems,
                ];
                foreach ($variables as $var) {
                    $multipart[] = [
                        'name'     => 'variables[]',
                        'contents' => (string) $var,
                    ];
                }

                // Dynamic Button URL parameter (URL in template: https://pos.indianpetcompany.com/guest/receipt/{{1}})
                $multipart[] = [
                    'name'     => 'button_url',
                    'contents' => (string) $salesBill->id,
                ];
            } else {
                // Generic 8 Body variables fallback
                $variables = [
                    $customerName,
                    $billNumber,
                    $billDate,
                    $branchName,
                    $totalItems,
                    $totalAmount,
                    $paymentModes,
                    $branchPhone,
                ];
                foreach ($variables as $var) {
                    $multipart[] = [
                        'name'     => 'variables[]',
                        'contents' => (string) $var,
                    ];
                }

                // Dynamic Button URL parameter
                $multipart[] = [
                    'name'     => 'button_url',
                    'contents' => (string) $publicUrl,
                ];
            }
        } else {
            $message = $this->formatInvoiceMessage($salesBill);
            $multipart = [
                [
                    'name'     => 'appkey',
                    'contents' => $this->appKey,
                ],
                [
                    'name'     => 'authkey',
                    'contents' => $this->authKey,
                ],
                [
                    'name'     => 'to',
                    'contents' => $cleanPhone,
                ],
                [
                    'name'     => 'message',
                    'contents' => $message,
                ],
            ];
        }

        try {
            $response = Http::asMultipart()
                ->timeout(15)
                ->post($this->url . '/api/whatsapp/message', $multipart);

            $json = $response->json();

            // Automatic fallback: if template was rejected (not found / unapproved on device), retry as direct message
            if (!empty($this->templateName) && !$isTemplateUnsupported && (!$response->successful() || !($json['success'] ?? false) || ($json['data']['status'] ?? '') === 'failed')) {
                $templateErr = $json['error'] ?? ($json['message'] ?? 'Template rejected');
                if (str_contains(strtolower((string)$templateErr), 'template not found') || str_contains(strtolower((string)$templateErr), 'not approved')) {
                    \Illuminate\Support\Facades\Cache::put('chatonclick_template_unsupported_' . $this->appKey . '_' . $this->templateName, true, 86400);
                }
                Log::warning("WhatsApp template '{$this->templateName}' rejected by device ({$templateErr}). Falling back to direct formatted text message for Bill #{$salesBill->bill_number}.");

                $directMessage = $this->formatInvoiceMessage($salesBill);
                $fallbackResp = Http::asMultipart()
                    ->timeout(15)
                    ->post($this->url . '/api/whatsapp/message', [
                        ['name' => 'appkey', 'contents' => $this->appKey],
                        ['name' => 'authkey', 'contents' => $this->authKey],
                        ['name' => 'to', 'contents' => $cleanPhone],
                        ['name' => 'message', 'contents' => $directMessage],
                    ]);
                $fallbackJson = $fallbackResp->json();
                if ($fallbackResp->successful() && ($fallbackJson['success'] ?? false) && ($fallbackJson['data']['status'] ?? '') !== 'failed') {
                    Log::info("WhatsApp bill dispatched via direct message fallback for Bill #{$salesBill->bill_number} to {$cleanPhone}");
                    return [
                        'success'             => true,
                        'phone'               => $cleanPhone,
                        'wamid'               => $fallbackJson['data']['mid'] ?? null,
                        'message'             => "WhatsApp bill successfully sent to +{$cleanPhone}",
                        'direct_whatsapp_url' => $directWhatsappUrl,
                    ];
                }
            }

            if ($response->successful() && ($json['success'] ?? false)) {
                $deliveryStatus = $json['data']['status'] ?? 'sent';
                if ($deliveryStatus === 'failed') {
                    $templateErr = !empty($this->templateName) 
                        ? "Template '{$this->templateName}' failed to deliver on ChatOnClick. Please verify the template is approved on ChatOnClick, or remove Template Name from Settings to use Direct Message mode."
                        : "ChatOnClick reported message dispatch failed.";
                    Log::warning("WhatsApp dispatch failed on ChatOnClick: " . $templateErr);
                    return [
                        'success'             => false,
                        'error'               => $templateErr,
                        'phone'               => $cleanPhone,
                        'direct_whatsapp_url' => $directWhatsappUrl,
                    ];
                }

                Log::info("WhatsApp bill dispatched for Bill #{$salesBill->bill_number} to {$cleanPhone}", [
                    'wamid' => $json['data']['mid'] ?? null,
                ]);

                // If a Meta template was dispatched, also send itemized text message so customer receives real breakdown
                if (!empty($this->templateName) && !$isTemplateUnsupported) {
                    try {
                        $directMsg = $this->formatInvoiceMessage($salesBill);
                        \Illuminate\Support\Facades\Http::asMultipart()->timeout(10)->post($this->url . '/api/whatsapp/message', [
                            ['name' => 'appkey', 'contents' => $this->appKey],
                            ['name' => 'authkey', 'contents' => $this->authKey],
                            ['name' => 'to', 'contents' => $cleanPhone],
                            ['name' => 'message', 'contents' => $directMsg],
                        ]);
                    } catch (\Throwable $te) {}
                }

                return [
                    'success'             => true,
                    'phone'               => $cleanPhone,
                    'wamid'               => $json['data']['mid'] ?? null,
                    'message'             => "WhatsApp bill successfully sent to +{$cleanPhone}",
                    'direct_whatsapp_url' => $directWhatsappUrl,
                ];
            }

            $errorMessage = $json['error'] ?? ($json['message'] ?? 'Failed to send WhatsApp message via ChatOnClick.');
            Log::error("ChatOnClick API error for Bill #{$salesBill->bill_number}: {$errorMessage}", [
                'status'   => $response->status(),
                'response' => $json,
            ]);

            return [
                'success'             => false,
                'error'               => $errorMessage,
                'phone'               => $cleanPhone,
                'direct_whatsapp_url' => $directWhatsappUrl,
            ];
        } catch (\Throwable $e) {
            Log::error("ChatOnClick HTTP exception for Bill #{$salesBill->bill_number}: " . $e->getMessage());

            return [
                'success'             => false,
                'error'               => 'Network error connecting to WhatsApp service: ' . $e->getMessage(),
                'phone'               => $cleanPhone,
                'direct_whatsapp_url' => $directWhatsappUrl,
            ];
        }
    }
}

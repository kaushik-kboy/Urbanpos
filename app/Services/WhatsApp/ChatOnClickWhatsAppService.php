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
    protected bool $autoSendOnBill;
    protected bool $isActive;

    public function __construct()
    {
        $this->refreshSettings();
    }

    /**
     * Refresh settings dynamically from database singleton (with env/config fallback).
     */
    public function refreshSettings(): void
    {
        $settings = null;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('whats_app_settings')) {
                $settings = \App\Models\WhatsAppSetting::current();
            }
        } catch (\Throwable $e) {
            // Fallback gracefully during early boot or tests without migrations
        }

        $this->url = rtrim($settings?->api_url ?: config('services.chatonclick.url', 'https://chatonclick.com'), '/');
        $this->appKey = $settings?->app_key ?: config('services.chatonclick.appkey');
        $this->authKey = $settings?->auth_key ?: config('services.chatonclick.authkey');
        $this->templateName = $settings?->template_name ?: config('services.chatonclick.template_name');
        $this->templateLang = $settings?->template_lang ?: config('services.chatonclick.template_lang', 'en');
        $this->headerTitle = $settings?->header_title ?: 'URBAN PETS';
        $this->footerMessage = $settings?->footer_message ?: 'Have an Awesome Day!';
        $this->supportPhone = $settings?->support_phone ?: null;
        $this->autoSendOnBill = $settings ? (bool) $settings->auto_send_on_bill : true;
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
        $appUrl = config('app.url');
        if (!empty($appUrl) && !str_contains($appUrl, 'localhost')) {
            return rtrim($appUrl, '/') . "/receipt/v/{$salesBill->id}/{$hash}";
        }

        return route('sales-bills.public-receipt', [
            'salesBill' => $salesBill->id,
            'hash'      => $hash,
        ]);
    }

    /**
     * Generate formatted WhatsApp text message for invoice receipt.
     */
    public function formatInvoiceMessage(SalesBill $salesBill): string
    {
        $customerName = trim($salesBill->customer?->name ?: 'Customer');
        $branchName   = $salesBill->branch?->name ?: $this->headerTitle;
        $branchPhone  = $this->supportPhone ?: ($salesBill->branch?->phone ?: '7383056626');
        $billNumber   = $salesBill->bill_number;
        $billDate     = $salesBill->bill_date ? $salesBill->bill_date->format('d-M-Y h:i A') : now()->format('d-M-Y');
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

        return "🐾 *{$header}* 🐾\n"
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
             . "🐾 *{$footer}*";
    }

    /**
     * Send arbitrary text message to a specific phone number.
     */
    public function sendTextMessage(string $phone, string $message): array
    {
        $this->refreshSettings();

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
    public function sendTestMessage(string $phone): array
    {
        $timestamp = now()->format('d-M-Y h:i A');
        $msg = "🐾 *{$this->headerTitle}* 🐾\n"
             . "✅ *WhatsApp Integration Test Successful!*\n"
             . "━━━━━━━━━━━━━━━━━━━━\n"
             . "This is a live test dispatch from your UrbanPOS Admin Panel.\n"
             . "📅 *Time:* {$timestamp}\n"
             . "⚡ *Status:* ChatOnClick API Connected\n"
             . "━━━━━━━━━━━━━━━━━━━━\n"
             . "🐾 *{$this->footerMessage}*";

        return $this->sendTextMessage($phone, $msg);
    }

    /**
     * Send Sales Bill invoice WhatsApp message via ChatOnClick API.
     */
    public function sendSalesBillInvoice(SalesBill $salesBill, ?string $overridePhone = null, bool $force = false): array
    {
        $this->refreshSettings();

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

        if (!empty($this->templateName)) {
            $customerName = trim($salesBill->customer?->name ?: 'Customer');
            $billNumber   = $salesBill->bill_number;
            $totalAmount  = number_format((float) $salesBill->total, 2);
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

            // ChatOnClick API expects variables[] array in multipart
            $variables = [$customerName, $billNumber, $totalAmount, $publicUrl];
            foreach ($variables as $var) {
                $multipart[] = [
                    'name'     => 'variables[]',
                    'contents' => (string) $var,
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

            if ($response->successful() && ($json['success'] ?? false)) {
                $deliveryStatus = $json['data']['status'] ?? 'sent';
                if ($deliveryStatus === 'failed') {
                    $templateErr = !empty($this->templateName) 
                        ? "Template '{$this->templateName}' failed to deliver on ChatOnClick. Please verify the template is approved on ChatOnClick, or remove Template Name from Settings to use Direct Message mode."
                        : "ChatOnClick reported message dispatch failed.";
                    Log::warning("WhatsApp dispatch failed on ChatOnClick: " . $templateErr);
                    return [
                        'success' => false,
                        'error'   => $templateErr,
                    ];
                }

                Log::info("WhatsApp bill dispatched for Bill #{$salesBill->bill_number} to {$cleanPhone}", [
                    'wamid' => $json['data']['mid'] ?? null,
                ]);

                return [
                    'success' => true,
                    'phone'   => $cleanPhone,
                    'wamid'   => $json['data']['mid'] ?? null,
                    'message' => "WhatsApp bill successfully sent to +{$cleanPhone}",
                ];
            }

            $errorMessage = $json['error'] ?? ($json['message'] ?? 'Failed to send WhatsApp message via ChatOnClick.');
            Log::error("ChatOnClick API error for Bill #{$salesBill->bill_number}: {$errorMessage}", [
                'status'   => $response->status(),
                'response' => $json,
            ]);

            return [
                'success' => false,
                'error'   => $errorMessage,
            ];
        } catch (\Throwable $e) {
            Log::error("ChatOnClick HTTP exception for Bill #{$salesBill->bill_number}: " . $e->getMessage());

            return [
                'success' => false,
                'error'   => 'Network error connecting to WhatsApp service: ' . $e->getMessage(),
            ];
        }
    }
}

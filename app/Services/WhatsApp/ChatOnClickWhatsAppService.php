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

    public function __construct()
    {
        $this->url = rtrim(config('services.chatonclick.url', 'https://chatonclick.com'), '/');
        $this->appKey = config('services.chatonclick.appkey');
        $this->authKey = config('services.chatonclick.authkey');
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
        $branchName   = $salesBill->branch?->name ?: 'Urban Pets';
        $branchPhone  = $salesBill->branch?->phone ?: '9638455255';
        $billNumber   = $salesBill->bill_number;
        $billDate     = $salesBill->bill_date ? $salesBill->bill_date->format('d-M-Y h:i A') : now()->format('d-M-Y');
        $totalItems   = (int) $salesBill->items()->count();
        $totalAmount  = number_format((float) $salesBill->total, 2);
        $publicUrl    = $this->getPublicReceiptUrl($salesBill);

        // Determine payment mode
        $payments = $salesBill->payments()->with('tenderType')->get();
        if ($payments->isNotEmpty()) {
            $paymentModes = $payments->map(fn($p) => $p->tenderType?->name ?: 'Payment')->unique()->implode(', ');
        } else {
            $paymentModes = $salesBill->payment_type ?: 'Cash';
        }

        return "🐾 *URBAN PETS* 🐾\n"
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
             . "🐾 *Have an Awesome Day!*";
    }

    /**
     * Send Sales Bill invoice WhatsApp message via ChatOnClick API.
     */
    public function sendSalesBillInvoice(SalesBill $salesBill, ?string $overridePhone = null): array
    {
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

        $templateName = config('services.chatonclick.template_name');

        if (!empty($templateName)) {
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
                    'contents' => $templateName,
                ],
                [
                    'name'     => 'language',
                    'contents' => config('services.chatonclick.template_lang', 'en'),
                ],
                [
                    'name'     => 'variables',
                    'contents' => json_encode([$customerName, $billNumber, $totalAmount, $publicUrl]),
                ],
            ];
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

<?php

namespace App\Services\System;

use App\Models\SystemErrorLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ErrorLoggerService
{
    /**
     * Keys to redact from request payloads for privacy and security.
     */
    protected array $redactedKeys = [
        'password',
        'password_confirmation',
        'token',
        'api_key',
        'secret',
        'credit_card',
        '_token',
        'remember_token',
        'auth_key',
    ];

    /**
     * Capture and record an exception into the system_error_logs table.
     */
    public function capture(Throwable $e, ?Request $request = null): ?SystemErrorLog
    {
        try {
            // Avoid logging if database or table is not ready
            if (!Schema::hasTable('system_error_logs')) {
                return null;
            }

            $request = $request ?? request();

            $url = $request ? $request->fullUrl() : 'CLI / Background';
            $path = $request ? $request->path() : '';
            $method = $request ? $request->method() : 'CLI';

            $module = $this->detectModule($path, $e);
            $errorType = class_basename($e);
            $message = $e->getMessage() ?: ('Unhandled ' . $errorType);

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                $validationErrors = $e->validator->errors()->all();
                if (!empty($validationErrors)) {
                    $message = 'Validation Error: ' . implode(' | ', $validationErrors);
                }
            }

            $file = $e->getFile();
            $line = $e->getLine();

            // Sanitize file path for cleaner readability (strip project root if present)
            $basePath = base_path();
            if (str_starts_with($file, $basePath)) {
                $file = ltrim(substr($file, strlen($basePath)), '/\\');
            }

            // User and branch context
            $user = Auth::user();
            $userId = $user?->id;
            $userName = $user?->name;
            $branchId = session('active_branch_id') ?? $user?->branch_id;

            // Redacted request parameters
            $requestData = [];
            if ($request && $request->isMethod('POST', 'PUT', 'PATCH', 'DELETE')) {
                $requestData = $this->sanitizePayload($request->all());
            } elseif ($request && !empty($request->query())) {
                $requestData = ['_query' => $this->sanitizePayload($request->query())];
            }

            // Stack trace truncated to first 60 lines or 60KB
            $trace = $e->getTraceAsString();
            if (strlen($trace) > 60000) {
                $trace = substr($trace, 0, 60000) . "\n... [Trace Truncated]";
            }

            return SystemErrorLog::create([
                'module'       => $module,
                'error_type'   => $errorType,
                'message'      => $message,
                'file'         => $file,
                'line'         => $line,
                'url'          => substr($url, 0, 950),
                'method'       => $method,
                'user_id'      => $userId,
                'user_name'    => $userName,
                'branch_id'    => $branchId,
                'request_data' => !empty($requestData) ? $requestData : null,
                'stack_trace'  => $trace,
                'ip_address'   => $request?->ip(),
                'user_agent'   => substr($request?->userAgent() ?? '', 0, 490),
                'status'       => 'Unresolved',
            ]);
        } catch (Throwable $internalEx) {
            // Absolute fail-safe: Never disrupt user application flow if logger itself fails
            error_log('SystemErrorLogger failed: ' . $internalEx->getMessage());
            return null;
        }
    }

    /**
     * Intelligently detect the module from URL path or exception trace.
     */
    public function detectModule(string $path, Throwable $e): string
    {
        $pathLower = strtolower(trim($path, '/'));

        // Sales module detection
        if (str_starts_with($pathLower, 'sales/sales-bills') || str_contains($pathLower, 'sales-bill')) {
            return 'SalesBill';
        }
        if (str_starts_with($pathLower, 'sales/sales-orders') || str_contains($pathLower, 'sales-order')) {
            return 'SalesOrder';
        }
        if (str_starts_with($pathLower, 'sales/sales-quotations') || str_contains($pathLower, 'sales-quotation')) {
            return 'SalesQuotation';
        }
        if (str_starts_with($pathLower, 'sales/sales-returns') || str_contains($pathLower, 'sales-return')) {
            return 'SalesReturn';
        }
        if (str_starts_with($pathLower, 'sales/sales-delivery-notes') || str_contains($pathLower, 'sales-delivery-note')) {
            return 'SalesDeliveryNote';
        }
        if (str_starts_with($pathLower, 'sales')) {
            return 'Sales';
        }

        // Purchase module detection
        if (str_starts_with($pathLower, 'purchase/purchase-receipt-notes') || str_contains($pathLower, 'purchase-receipt-note') || str_contains($pathLower, 'grn')) {
            return 'PurchaseReceiptNote';
        }
        if (str_starts_with($pathLower, 'purchase/purchase-invoices') || str_contains($pathLower, 'purchase-invoice')) {
            return 'PurchaseInvoice';
        }
        if (str_starts_with($pathLower, 'purchase/purchase-orders') || str_contains($pathLower, 'purchase-order')) {
            return 'PurchaseOrder';
        }
        if (str_starts_with($pathLower, 'purchase/purchase-returns') || str_contains($pathLower, 'purchase-return')) {
            return 'PurchaseReturn';
        }
        if (str_starts_with($pathLower, 'purchase/purchase-indents') || str_contains($pathLower, 'purchase-indent')) {
            return 'PurchaseIndent';
        }
        if (str_starts_with($pathLower, 'purchase')) {
            return 'Purchase';
        }

        // Inventory module detection
        if (str_starts_with($pathLower, 'inventory')) {
            return 'Inventory';
        }

        // GST & Tax Tools
        if (str_starts_with($pathLower, 'tools/gst') || str_contains($pathLower, 'gstr') || str_contains($pathLower, 'einvoice') || str_contains($pathLower, 'eway')) {
            return 'GST';
        }
        if (str_starts_with($pathLower, 'tools')) {
            return 'Tools';
        }

        // Master records
        if (str_starts_with($pathLower, 'master')) {
            return 'Master';
        }

        // Finance & Ledgers
        if (str_starts_with($pathLower, 'finance')) {
            return 'Finance';
        }

        // Reports
        if (str_starts_with($pathLower, 'reports')) {
            return 'Reports';
        }

        // POS
        if (str_starts_with($pathLower, 'pos')) {
            return 'POSTerminal';
        }

        // Fallback: check file name in trace
        $file = $e->getFile();
        if (str_contains($file, 'SalesBill')) return 'SalesBill';
        if (str_contains($file, 'PurchaseReceiptNote')) return 'PurchaseReceiptNote';
        if (str_contains($file, 'Purchase')) return 'Purchase';
        if (str_contains($file, 'Sales')) return 'Sales';
        if (str_contains($file, 'Inventory')) return 'Inventory';
        if (str_contains($file, 'Master')) return 'Master';
        if (str_contains($file, 'Finance')) return 'Finance';

        return 'General';
    }

    /**
     * Recursively sanitize payload to strip sensitive passwords or tokens.
     */
    protected function sanitizePayload(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $val) {
            $keyLower = strtolower((string)$key);
            $shouldRedact = false;
            foreach ($this->redactedKeys as $redact) {
                if (str_contains($keyLower, $redact)) {
                    $shouldRedact = true;
                    break;
                }
            }

            if ($shouldRedact) {
                $clean[$key] = '***REDACTED***';
            } elseif (is_array($val)) {
                $clean[$key] = $this->sanitizePayload($val);
            } else {
                $clean[$key] = $val;
            }
        }
        return $clean;
    }
}

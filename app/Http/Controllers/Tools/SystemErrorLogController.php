<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\SystemErrorLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SystemErrorLogController extends Controller
{
    /**
     * Display the System Error & Exception Hub.
     */
    public function index(Request $request)
    {
        $query = SystemErrorLog::query();

        // 1. Module filter
        $selectedModule = $request->input('module');
        if (!empty($selectedModule) && $selectedModule !== 'all') {
            $query->where('module', $selectedModule);
        }

        // 2. Status filter (default: show all or specific)
        $selectedStatus = $request->input('status', 'all');
        if (!empty($selectedStatus) && $selectedStatus !== 'all') {
            $query->where('status', $selectedStatus);
        }

        // 3. Date range handling
        $datePreset = $request->input('date_preset', '');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        if ($datePreset === 'today') {
            $fromDate = Carbon::today()->toDateString();
            $toDate = Carbon::today()->toDateString();
        } elseif ($datePreset === 'yesterday') {
            $fromDate = Carbon::yesterday()->toDateString();
            $toDate = Carbon::yesterday()->toDateString();
        } elseif ($datePreset === 'last_7_days') {
            $fromDate = Carbon::today()->subDays(6)->toDateString();
            $toDate = Carbon::today()->toDateString();
        } elseif ($datePreset === 'this_month') {
            $fromDate = Carbon::today()->startOfMonth()->toDateString();
            $toDate = Carbon::today()->endOfMonth()->toDateString();
        }

        if (!empty($fromDate)) {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        if (!empty($toDate)) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        // 4. Keyword search
        $search = $request->input('search');
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                  ->orWhere('error_type', 'like', "%{$search}%")
                  ->orWhere('url', 'like', "%{$search}%")
                  ->orWhere('user_name', 'like', "%{$search}%")
                  ->orWhere('file', 'like', "%{$search}%");
            });
        }

        // Clone query for metrics before pagination
        $totalErrors = SystemErrorLog::count();
        $unresolvedCount = SystemErrorLog::where('status', 'Unresolved')->count();
        $todayCount = SystemErrorLog::whereDate('created_at', Carbon::today())->count();

        // Group counts by module
        $moduleStats = SystemErrorLog::select('module', DB::raw('count(*) as total'), DB::raw('sum(case when status = "Unresolved" then 1 else 0 end) as unresolved'))
            ->groupBy('module')
            ->orderByDesc('total')
            ->get();

        // Distinct modules for filter dropdown
        $availableModules = [
            'SalesBill',
            'SalesOrder',
            'SalesQuotation',
            'SalesReturn',
            'SalesDeliveryNote',
            'Sales',
            'PurchaseReceiptNote',
            'PurchaseInvoice',
            'PurchaseOrder',
            'PurchaseReturn',
            'PurchaseIndent',
            'Purchase',
            'Inventory',
            'GST',
            'Tools',
            'Master',
            'Finance',
            'POSTerminal',
            'Reports',
            'General',
        ];

        // Paginated results
        $logs = $query->with(['user', 'branch', 'resolver'])
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('tools.system-error-logs.index', [
            'logs'             => $logs,
            'totalErrors'      => $totalErrors,
            'unresolvedCount'  => $unresolvedCount,
            'todayCount'       => $todayCount,
            'moduleStats'      => $moduleStats,
            'availableModules' => $availableModules,
            'selectedModule'   => $selectedModule,
            'selectedStatus'   => $selectedStatus,
            'fromDate'         => $fromDate,
            'toDate'           => $toDate,
            'datePreset'       => $datePreset,
            'search'           => $search,
        ]);
    }

    /**
     * Return error log details in JSON format for the inspection modal.
     */
    public function show($id): JsonResponse
    {
        $log = SystemErrorLog::with(['user', 'branch', 'resolver'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'log' => [
                'id'               => $log->id,
                'module'           => $log->module,
                'error_type'       => $log->error_type,
                'message'          => $log->message,
                'error_hash'       => $log->error_hash,
                'occurrence_count' => $log->occurrence_count ?? 1,
                'last_seen_at'     => $log->last_seen_at ? $log->last_seen_at->format('d M Y, h:i:s A') : null,
                'file'             => $log->file,
                'line'             => $log->line,
                'url'              => $log->url,
                'method'           => $log->method,
                'user_name'        => $log->user_name ?? ($log->user ? $log->user->name : 'Guest / System'),
                'branch_name'      => $log->branch ? $log->branch->name : 'N/A',
                'ip_address'       => $log->ip_address ?? 'N/A',
                'user_agent'       => $log->user_agent ?? 'N/A',
                'status'           => $log->status,
                'request_data'     => $log->request_data ? json_encode($log->request_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null,
                'stack_trace'      => $log->stack_trace,
                'created_at'       => $log->created_at ? $log->created_at->format('d M Y, h:i:s A') : 'N/A',
                'resolved_at'      => $log->resolved_at ? $log->resolved_at->format('d M Y, h:i:s A') : null,
                'resolver'         => $log->resolver ? $log->resolver->name : null,
            ],
        ]);
    }

    /**
     * Mark an error log as Resolved.
     */
    public function resolve($id, Request $request)
    {
        $log = SystemErrorLog::findOrFail($id);
        $log->update([
            'status'      => 'Resolved',
            'resolved_at' => now(),
            'resolved_by' => Auth::id(),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Error #{$log->id} marked as resolved.",
            ]);
        }

        return redirect()->back()->with('success', "Error #{$log->id} marked as resolved.");
    }

    /**
     * Clear resolved error logs older than X days (default 30 days).
     */
    public function clearOld(Request $request)
    {
        $days = (int)$request->input('days', 30);
        $threshold = Carbon::now()->subDays($days);

        $deleted = SystemErrorLog::where('status', 'Resolved')
            ->where('created_at', '<', $threshold)
            ->delete();

        return redirect()->back()->with('success', "Cleared {$deleted} resolved error records older than {$days} days.");
    }

    /**
     * Export filtered logs to CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = SystemErrorLog::query();

        if ($request->filled('module') && $request->input('module') !== 'all') {
            $query->where('module', $request->input('module'));
        }
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        $filename = 'system_error_logs_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Module', 'Error Type', 'Message', 'Occurrences', 'Last Seen', 'File', 'Line', 'URL', 'Method', 'User', 'Branch', 'Status', 'Date Time']);

            $query->orderBy('created_at', 'desc')->chunk(200, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->id,
                        $row->module,
                        $row->error_type,
                        $row->message,
                        $row->occurrence_count ?? 1,
                        $row->last_seen_at ? $row->last_seen_at->toDateTimeString() : '',
                        $row->file,
                        $row->line,
                        $row->url,
                        $row->method,
                        $row->user_name,
                        $row->branch_id,
                        $row->status,
                        $row->created_at ? $row->created_at->toDateTimeString() : '',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Ingest client-side JavaScript error telemetry.
     */
    public function logClientError(Request $request): JsonResponse
    {
        $message = substr((string)$request->input('message', 'Client JavaScript Error'), 0, 1000);
        $file = substr((string)$request->input('file', 'browser/script.js'), 0, 500);
        $line = (int) $request->input('line', 0);
        $url = substr((string)$request->input('url', $request->fullUrl()), 0, 950);
        $stack = substr((string)$request->input('stack', ''), 0, 50000);
        $extra = $request->input('extra', []);

        $logger = app(\App\Services\System\ErrorLoggerService::class);
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $module = $logger->detectModule($path, new \Exception($message));

        SystemErrorLog::create([
            'module'       => $module,
            'error_type'   => 'ClientJavaScriptError',
            'message'      => $message,
            'file'         => $file,
            'line'         => $line,
            'url'          => $url,
            'method'       => 'BROWSER',
            'user_id'      => Auth::id(),
            'user_name'    => Auth::user()?->name ?? 'Cashier / Client',
            'branch_id'    => session('active_branch_id') ?? Auth::user()?->branch_id,
            'request_data' => !empty($extra) ? $extra : null,
            'stack_trace'  => $stack ?: 'Client Browser JS Trace',
            'ip_address'   => $request->ip(),
            'user_agent'   => substr($request->userAgent() ?? '', 0, 490),
            'status'       => 'Unresolved',
        ]);

        return response()->json(['success' => true]);
    }
}

<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DocumentSequence;
use App\Services\Accounting\DocumentNumberingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentSequenceController extends Controller
{
    public function __construct(
        private DocumentNumberingService $numbering
    ) {}

    /**
     * Display Document Sequences Customizer UI.
     */
    public function index(Request $request): View
    {
        $branches = Branch::where('status', 1)->orderBy('name')->get();

        if ($request->has('branch_id')) {
            $raw = $request->input('branch_id');
            $branchId = ($raw === 'all' || $raw === 'global' || $raw === '' || $raw === '0') ? null : (int) $raw;
        } else {
            $branchId = session('active_branch_id', auth()->user()?->branch_id) ?: ($branches->first()?->id ?? null);
        }

        $definitions = DocumentNumberingService::defaultDefinitions();

        // Fetch existing sequence records for this branch (or global if branchId is null)
        $existingSequences = DocumentSequence::where(function ($q) use ($branchId) {
            if ($branchId) {
                $q->where('branch_id', $branchId);
            } else {
                $q->whereNull('branch_id');
            }
        })->get()->keyBy('document_type');

        // Global fallback sequences if branch doesn't have custom ones yet
        $globalSequences = $branchId 
            ? DocumentSequence::whereNull('branch_id')->get()->keyBy('document_type')
            : collect();

        // Merge defaults with saved database records
        $sequences = [];
        foreach ($definitions as $typeKey => $def) {
            $branchRecord = $existingSequences->get($typeKey);
            $globalRecord = $globalSequences->get($typeKey);
            $record = $branchRecord ?? $globalRecord;
            $previewBranch = $branchId ? $branches->firstWhere('id', $branchId) : null;

            $sequences[$typeKey] = [
                'id'              => $branchRecord?->id ?? $globalRecord?->id,
                'document_type'   => $typeKey,
                'document_title'  => $record?->document_title ?: $def['title'],
                'module'          => $def['module'],
                'prefix'          => $record?->prefix ?: $def['prefix'],
                'suffix'          => $record?->suffix ?: '',
                'padding_zeros'   => $record?->padding_zeros ?: $def['padding'],
                'starting_number' => $record?->starting_number ?: $def['start'],
                'last_number'     => $record?->last_number ?: 0,
                'reset_frequency' => $record?->reset_frequency ?: $def['reset'],
                'is_active'       => $record ? (bool)$record->is_active : true,
                'is_branch_customized' => $existingSequences->has($typeKey),
                'preview'         => $record 
                    ? $record->formatPreview($previewBranch)
                    : (new DocumentSequence([
                        'prefix'          => $def['prefix'],
                        'padding_zeros'   => $def['padding'],
                        'starting_number' => $def['start'],
                        'last_number'     => 0,
                    ]))->formatPreview($previewBranch),
            ];
        }

        return view('tools.document-sequences', compact('sequences', 'branches', 'branchId'));
    }

    /**
     * Save/update a single sequence rule.
     */
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'document_type'   => 'required|string|max:50',
            'prefix'          => 'required|string|max:50',
            'suffix'          => 'nullable|string|max:50',
            'padding_zeros'   => 'required|integer|min:1|max:10',
            'starting_number' => 'required|integer|min:1',
            'last_number'     => 'nullable|integer|min:0',
            'reset_frequency' => 'required|in:financial_year,yearly,monthly,never',
            'branch_id'       => 'nullable',
            'is_active'       => 'nullable|boolean',
        ]);

        $rawBranch = $validated['branch_id'] ?? null;
        $branchId = ($rawBranch === 'all' || $rawBranch === 'global' || $rawBranch === '' || $rawBranch === null || $rawBranch === 0 || $rawBranch === '0')
            ? null
            : (int) $rawBranch;

        $def = DocumentNumberingService::defaultDefinitions()[$validated['document_type']] ?? null;
        $title = $def['title'] ?? ucwords(str_replace('_', ' ', $validated['document_type']));

        $sequence = DocumentSequence::firstOrNew([
            'document_type' => $validated['document_type'],
            'branch_id'     => $branchId,
        ]);

        $sequence->document_title  = $title;
        $sequence->prefix          = trim($validated['prefix']);
        $sequence->suffix          = !empty($validated['suffix']) ? trim($validated['suffix']) : null;
        $sequence->padding_zeros   = (int) $validated['padding_zeros'];
        $sequence->starting_number = (int) $validated['starting_number'];
        $sequence->reset_frequency = $validated['reset_frequency'];
        $sequence->is_active       = $request->boolean('is_active', true);

        if ($request->has('last_number') && $request->input('last_number') !== null && $request->input('last_number') !== '') {
            $sequence->last_number = (int) $request->input('last_number');
        } elseif (!$sequence->exists) {
            $sequence->last_number = 0;
        }

        if (!$sequence->exists) {
            $sequence->series = 'doc:' . $validated['document_type'] . ($branchId ? ":{$branchId}" : '');
            $sequence->last_reset_period = DocumentSequence::getCurrentPeriod($validated['reset_frequency']);
        }

        $sequence->save();

        $branchName = $branchId ? (Branch::find($branchId)?->name ?? "Branch #{$branchId}") : 'Global (All Outlets)';

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "{$sequence->document_title} numbering rule for {$branchName} saved successfully!",
                'preview' => $sequence->formatPreview($branchId ? Branch::find($branchId) : null),
                'sequence' => $sequence,
            ]);
        }

        return redirect()->back()->with('success', "{$sequence->document_title} numbering rule for {$branchName} saved successfully!");
    }

    /**
     * Manually reset sequence counter to starting number.
     */
    public function reset(Request $request, DocumentSequence $sequence): RedirectResponse|JsonResponse
    {
        $sequence->last_number = max(0, (int)$sequence->starting_number - 1);
        $sequence->last_reset_period = DocumentSequence::getCurrentPeriod($sequence->reset_frequency);
        $sequence->save();

        $message = "Counter for {$sequence->document_title} reset to {$sequence->starting_number} successfully!";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'preview' => $sequence->formatPreview(),
            ]);
        }

        return redirect()->back()->with('success', $message);
    }
}

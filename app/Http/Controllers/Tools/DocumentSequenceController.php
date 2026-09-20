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
        $branchId = $request->filled('branch_id') && $request->input('branch_id') !== 'all'
            ? (int) $request->input('branch_id')
            : null;

        $branches = Branch::where('status', 1)->orderBy('name')->get();
        $definitions = DocumentNumberingService::defaultDefinitions();

        // Fetch existing sequence records for this branch / global
        $existingSequences = DocumentSequence::where(function ($q) use ($branchId) {
            if ($branchId) {
                $q->where('branch_id', $branchId);
            } else {
                $q->whereNull('branch_id');
            }
        })->get()->keyBy('document_type');

        // Merge defaults with saved database records
        $sequences = [];
        foreach ($definitions as $typeKey => $def) {
            $record = $existingSequences->get($typeKey);
            $previewBranch = $branchId ? $branches->firstWhere('id', $branchId) : null;

            $sequences[$typeKey] = [
                'id'              => $record?->id,
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
            'reset_frequency' => 'required|in:financial_year,yearly,monthly,never',
            'branch_id'       => 'nullable|integer',
            'is_active'       => 'nullable|boolean',
        ]);

        $branchId = !empty($validated['branch_id']) ? (int) $validated['branch_id'] : null;
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

        if (!$sequence->exists) {
            $sequence->last_number = 0;
            $sequence->series = 'doc:' . $validated['document_type'] . ($branchId ? ":{$branchId}" : '');
            $sequence->last_reset_period = DocumentSequence::getCurrentPeriod($validated['reset_frequency']);
        }

        $sequence->save();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "{$sequence->document_title} numbering rule updated successfully!",
                'preview' => $sequence->formatPreview(),
                'sequence' => $sequence,
            ]);
        }

        return redirect()->back()->with('success', "{$sequence->document_title} numbering rule updated successfully!");
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

<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\FinancialYear;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FinancialYearController extends Controller
{
    use HasPerPage;

    public function __construct(private AuditLogger $auditLogger)
    {
    }

    public function index()
    {
        $financialYears = FinancialYear::orderByDesc('start_date')->paginate($this->perPage());

        return view('master.financial-years.index', compact('financialYears'));
    }

    public function create()
    {
        return view('master.financial-years.create');
    }

    public function show(FinancialYear $financialYear)
    {
        // Management screen, not a public record — "viewing" a Financial Year just means
        // editing it; no separate read-only detail page.
        return redirect()->route('master.financial-years.edit', $financialYear);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        FinancialYear::create($data);

        return redirect()->route('master.financial-years.index')->with('status', 'Financial Year created successfully.');
    }

    public function edit(FinancialYear $financialYear)
    {
        return view('master.financial-years.edit', compact('financialYear'));
    }

    public function update(Request $request, FinancialYear $financialYear)
    {
        if ($financialYear->is_locked) {
            throw ValidationException::withMessages([
                'financial_year' => 'This Financial Year is locked — reopen it before editing its dates.',
            ]);
        }

        $data = $this->validateData($request);
        $financialYear->update($data);

        return redirect()->route('master.financial-years.index')->with('status', 'Financial Year updated successfully.');
    }

    public function lock(FinancialYear $financialYear)
    {
        $oldValues = $financialYear->only(['is_locked']);
        $financialYear->update([
            'is_locked' => true,
            'locked_by_id' => auth()->id(),
            'locked_at' => now(),
        ]);
        $this->auditLogger->log('lock', $financialYear, $oldValues, ['is_locked' => true]);

        return redirect()->route('master.financial-years.index')->with('status', "{$financialYear->name} locked.");
    }

    public function reopen(FinancialYear $financialYear)
    {
        $oldValues = $financialYear->only(['is_locked']);
        $financialYear->update([
            'is_locked' => false,
            'locked_by_id' => null,
            'locked_at' => null,
        ]);
        $this->auditLogger->log('reopen', $financialYear, $oldValues, ['is_locked' => false]);

        return redirect()->route('master.financial-years.index')->with('status', "{$financialYear->name} reopened.");
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ]);
    }
}

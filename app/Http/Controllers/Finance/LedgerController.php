<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    private const GROUPS = [
        'Sundry Debtors', 'Sundry Creditors', 'Cash in Hand', 'Bank Account',
        'Sales Account', 'Purchase Account', 'Duties & Taxes', 'Indirect Income',
        'Indirect Expense', 'Capital Account', 'Fixed Assets', 'Current Liabilities',
    ];

    public function index()
    {
        $ledgers = Ledger::orderBy('ledger_group')->orderBy('name')->paginate(30);
        $ledgers->getCollection()->transform(function (Ledger $ledger) {
            $ledger->current_balance = $ledger->balance();

            return $ledger;
        });

        return view('finance.ledgers.index', compact('ledgers'));
    }

    public function create()
    {
        $groups = array_combine(self::GROUPS, self::GROUPS);

        return view('finance.ledgers.create', compact('groups'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        Ledger::create($data);

        return redirect()->route('finance.ledgers.index')->with('status', 'Ledger created successfully.');
    }

    public function edit(Ledger $ledger)
    {
        $groups = array_combine(self::GROUPS, self::GROUPS);

        return view('finance.ledgers.edit', compact('ledger', 'groups'));
    }

    public function update(Request $request, Ledger $ledger)
    {
        $data = $this->validateData($request);
        $ledger->update($data);

        return redirect()->route('finance.ledgers.index')->with('status', 'Ledger updated successfully.');
    }

    public function destroy(Ledger $ledger)
    {
        if ($ledger->customer_id || $ledger->supplier_id) {
            return redirect()->route('finance.ledgers.index')->with('status', 'Cannot delete a ledger linked to a Customer/Supplier — delete the master record instead.');
        }

        $ledger->delete();

        return redirect()->route('finance.ledgers.index')->with('status', 'Ledger deleted.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ledger_group' => ['required', 'in:'.implode(',', self::GROUPS)],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'opening_balance_type' => ['required', 'in:Debit,Credit'],
            'status' => ['required', 'boolean'],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Till;

use App\Http\Controllers\Controller;
use App\Models\Register;
use App\Models\TillCashMovement;
use App\Models\TillSession;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TillSessionController extends Controller
{
    public function index(Request $request)
    {
        $query = TillSession::with(['register', 'branch', 'user']);

        if ($request->filled('date_from')) {
            $query->whereDate('opened_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('opened_at', '<=', $request->date_to);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('register_id')) {
            $query->where('register_id', $request->register_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tillSessions = $query->latest('opened_at')->paginate(20)->withQueryString();
        $branches = \App\Models\Branch::orderBy('name')->get();
        $registers = Register::orderBy('name')->get();
        $users = \App\Models\User::orderBy('name')->get();

        return view('till.sessions.index', compact('tillSessions', 'branches', 'registers', 'users'));
    }

    public function create()
    {
        $registers = Register::orderBy('name')->pluck('name', 'id');

        return view('till.sessions.open', compact('registers'));
    }

    public function open(Request $request)
    {
        $data = $request->validate([
            'register_id' => ['required', 'exists:registers,id'],
            'opening_cash' => ['required', 'numeric', 'min:0'],
        ]);

        $register = Register::findOrFail($data['register_id']);

        if (TillSession::where('register_id', $register->id)->where('status', 'Open')->exists()) {
            throw ValidationException::withMessages([
                'register_id' => "Register \"{$register->name}\" already has an open till session. Close it before opening a new one.",
            ]);
        }

        $tillSession = TillSession::create([
            'register_id' => $register->id,
            'branch_id' => $register->branch_id,
            'user_id' => $request->user()->id,
            'opening_cash' => $data['opening_cash'],
            'opened_at' => now(),
            'status' => 'Open',
        ]);

        return redirect()->route('till.sessions.show', $tillSession)->with('status', 'Till opened.');
    }

    public function show(TillSession $tillSession)
    {
        $tillSession->load(['register', 'branch', 'user', 'cashMovements.user', 'salesBills']);

        return view('till.sessions.show', compact('tillSession'));
    }

    public function addCashMovement(Request $request, TillSession $tillSession)
    {
        $this->assertOpen($tillSession);

        $data = $request->validate([
            'type' => ['required', 'in:In,Out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        TillCashMovement::create([
            'till_session_id' => $tillSession->id,
            'type' => $data['type'],
            'amount' => $data['amount'],
            'reason' => $data['reason'] ?? null,
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('till.sessions.show', $tillSession)->with('status', "Cash {$data['type']} recorded.");
    }

    public function close(Request $request, TillSession $tillSession)
    {
        $this->assertOpen($tillSession);

        $data = $request->validate([
            'actual_cash' => ['required', 'numeric', 'min:0'],
        ]);

        $expectedCash = $this->computeExpectedCash($tillSession);
        $variance = round((float) $data['actual_cash'] - $expectedCash, 2);

        $tillSession->update([
            'status' => 'Closed',
            'expected_cash' => $expectedCash,
            'actual_cash' => $data['actual_cash'],
            'variance' => $variance,
            'closed_by_id' => $request->user()->id,
            'closed_at' => now(),
        ]);

        return redirect()->route('till.sessions.show', $tillSession)->with('status', 'Till closed.');
    }

    /**
     * Cash-tender sales linked to this session, plus manual cash in/out — the exact
     * inputs spec §14 names for "Close Till: Expected cash." Card/UPI/credit sales never
     * touch the physical drawer, so they're excluded regardless of amount.
     */
    private function computeExpectedCash(TillSession $tillSession): float
    {
        $cashSales = (float) \App\Models\SalesBillPayment::whereHas(
            'salesBill', fn ($q) => $q->where('till_session_id', $tillSession->id)
        )->whereHas(
            'tenderType', fn ($q) => $q->where('type', 'Cash')
        )->sum('amount');

        $cashIn = (float) $tillSession->cashMovements()->where('type', 'In')->sum('amount');
        $cashOut = (float) $tillSession->cashMovements()->where('type', 'Out')->sum('amount');

        return round((float) $tillSession->opening_cash + $cashSales + $cashIn - $cashOut, 2);
    }

    private function assertOpen(TillSession $tillSession): void
    {
        if (! $tillSession->isOpen()) {
            throw ValidationException::withMessages([
                'till_session' => 'This till session is already closed.',
            ]);
        }
    }
}

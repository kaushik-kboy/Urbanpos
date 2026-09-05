<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\Ledger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherController extends Controller
{
    private const MANUAL_TYPES = ['Payment', 'Receipt', 'Journal', 'Contra'];

    public function index()
    {
        $vouchers = JournalEntry::with('branch')
            ->whereIn('voucher_type', self::MANUAL_TYPES)
            ->latest('voucher_date')
            ->paginate(20);

        return view('finance.vouchers.index', compact('vouchers'));
    }

    public function create()
    {
        return view('finance.vouchers.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $voucher = DB::transaction(function () use ($data) {
            $this->assertBalanced($data['lines']);

            $voucher = JournalEntry::create(array_merge($data['header'], [
                'voucher_number' => $this->nextNumber($data['header']['voucher_type']),
                'total_debit' => collect($data['lines'])->sum('debit'),
                'total_credit' => collect($data['lines'])->sum('credit'),
            ]));

            $voucher->lines()->createMany($data['lines']);

            return $voucher;
        });

        return redirect()->route('finance.vouchers.index')->with('status', "Voucher {$voucher->voucher_number} created successfully.");
    }

    public function edit(JournalEntry $voucher)
    {
        $voucher->load('lines');

        return view('finance.vouchers.edit', array_merge(['voucher' => $voucher], $this->formOptions()));
    }

    public function update(Request $request, JournalEntry $voucher)
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data, $voucher) {
            $this->assertBalanced($data['lines']);

            $voucher->update(array_merge($data['header'], [
                'total_debit' => collect($data['lines'])->sum('debit'),
                'total_credit' => collect($data['lines'])->sum('credit'),
            ]));
            $voucher->lines()->delete();
            $voucher->lines()->createMany($data['lines']);
        });

        return redirect()->route('finance.vouchers.index')->with('status', "Voucher {$voucher->voucher_number} updated successfully.");
    }

    public function destroy(JournalEntry $voucher)
    {
        $voucher->delete();

        return redirect()->route('finance.vouchers.index')->with('status', 'Voucher deleted.');
    }

    private function assertBalanced(array $lines): void
    {
        $debit = round(collect($lines)->sum('debit'), 2);
        $credit = round(collect($lines)->sum('credit'), 2);

        if ($debit !== $credit) {
            throw ValidationException::withMessages([
                'lines' => "Voucher does not balance: Total Debit ({$debit}) must equal Total Credit ({$credit}).",
            ]);
        }

        if ($debit <= 0) {
            throw ValidationException::withMessages([
                'lines' => 'Voucher amount must be greater than zero.',
            ]);
        }
    }

    private function nextNumber(string $voucherType): string
    {
        $prefix = match ($voucherType) {
            'Payment' => 'PMT',
            'Receipt' => 'RCT',
            'Contra' => 'CTR',
            default => 'JV',
        };

        $next = (JournalEntry::max('id') ?? 0) + 1;

        return $prefix.'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function formOptions(): array
    {
        return [
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'ledgers' => Ledger::where('status', true)->orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function validateData(Request $request): array
    {
        $header = $request->validate([
            'voucher_type' => ['required', 'in:'.implode(',', self::MANUAL_TYPES)],
            'voucher_date' => ['required', 'date'],
            'branch_id' => ['required', 'exists:branches,id'],
            'narration' => ['nullable', 'string'],
        ]);

        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.ledger_id' => ['required', 'exists:ledgers,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $lines = array_map(fn ($line) => [
            'ledger_id' => $line['ledger_id'],
            'debit' => (float) ($line['debit'] ?? 0),
            'credit' => (float) ($line['credit'] ?? 0),
        ], $validated['lines']);

        return ['header' => $header, 'lines' => $lines];
    }
}

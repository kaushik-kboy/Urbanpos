<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyProgram;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoyaltyProgramController extends Controller
{
    public function index(Request $request)
    {
        $query = LoyaltyProgram::with('rules')->latest();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status') === '1');
        }

        $programs = $query->paginate(15)->withQueryString();

        return view('master.loyalty-programs.index', compact('programs'));
    }

    public function create()
    {
        $program = new LoyaltyProgram([
            'start_date' => now(),
            'min_points_redeem' => 50,
            'amount_per_point' => 1.00,
            'points_per_hundred' => 1.00,
            'roundoff' => true,
            'status' => true,
            'based_on' => 'Bill Amount',
        ]);

        return view('master.loyalty-programs.create', compact('program'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'based_on' => ['required', 'string', 'max:100'],
            'min_points_redeem' => ['required', 'integer', 'min:1'],
            'amount_per_point' => ['required', 'numeric', 'min:0.01'],
            'points_per_hundred' => ['required', 'numeric', 'min:0'],
            'roundoff' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
            'rules' => ['nullable', 'array'],
            'rules.*.min_bill_amount' => ['required_with:rules', 'numeric', 'min:0'],
            'rules.*.max_bill_amount' => ['nullable', 'numeric', 'min:0'],
            'rules.*.points_earned' => ['required_with:rules', 'numeric', 'min:0'],
        ]);

        $validated['roundoff'] = $request->boolean('roundoff', true);
        $validated['status'] = $request->boolean('status', true);

        DB::transaction(function () use ($validated) {
            $program = LoyaltyProgram::create([
                'name' => $validated['name'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? null,
                'based_on' => $validated['based_on'],
                'min_points_redeem' => $validated['min_points_redeem'],
                'amount_per_point' => $validated['amount_per_point'],
                'points_per_hundred' => $validated['points_per_hundred'],
                'roundoff' => $validated['roundoff'],
                'status' => $validated['status'],
            ]);

            if (! empty($validated['rules'])) {
                foreach ($validated['rules'] as $ruleData) {
                    if (isset($ruleData['points_earned']) && (float) $ruleData['points_earned'] > 0) {
                        $program->rules()->create([
                            'min_bill_amount' => $ruleData['min_bill_amount'] ?? 0,
                            'max_bill_amount' => $ruleData['max_bill_amount'] ?: null,
                            'points_earned' => $ruleData['points_earned'],
                        ]);
                    }
                }
            }
        });

        return redirect()->route('master.loyalty-programs.index')
            ->with('status', 'Loyalty Program created successfully.');
    }

    public function edit(LoyaltyProgram $loyaltyProgram)
    {
        $loyaltyProgram->load('rules');

        return view('master.loyalty-programs.edit', ['program' => $loyaltyProgram]);
    }

    public function update(Request $request, LoyaltyProgram $loyaltyProgram)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'based_on' => ['required', 'string', 'max:100'],
            'min_points_redeem' => ['required', 'integer', 'min:1'],
            'amount_per_point' => ['required', 'numeric', 'min:0.01'],
            'points_per_hundred' => ['required', 'numeric', 'min:0'],
            'roundoff' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
            'rules' => ['nullable', 'array'],
            'rules.*.min_bill_amount' => ['required_with:rules', 'numeric', 'min:0'],
            'rules.*.max_bill_amount' => ['nullable', 'numeric', 'min:0'],
            'rules.*.points_earned' => ['required_with:rules', 'numeric', 'min:0'],
        ]);

        $validated['roundoff'] = $request->boolean('roundoff', true);
        $validated['status'] = $request->boolean('status', true);

        DB::transaction(function () use ($loyaltyProgram, $validated) {
            $loyaltyProgram->update([
                'name' => $validated['name'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? null,
                'based_on' => $validated['based_on'],
                'min_points_redeem' => $validated['min_points_redeem'],
                'amount_per_point' => $validated['amount_per_point'],
                'points_per_hundred' => $validated['points_per_hundred'],
                'roundoff' => $validated['roundoff'],
                'status' => $validated['status'],
            ]);

            $loyaltyProgram->rules()->delete();

            if (! empty($validated['rules'])) {
                foreach ($validated['rules'] as $ruleData) {
                    if (isset($ruleData['points_earned']) && (float) $ruleData['points_earned'] > 0) {
                        $loyaltyProgram->rules()->create([
                            'min_bill_amount' => $ruleData['min_bill_amount'] ?? 0,
                            'max_bill_amount' => $ruleData['max_bill_amount'] ?: null,
                            'points_earned' => $ruleData['points_earned'],
                        ]);
                    }
                }
            }
        });

        return redirect()->route('master.loyalty-programs.index')
            ->with('status', 'Loyalty Program updated successfully.');
    }

    public function destroy(LoyaltyProgram $loyaltyProgram)
    {
        $loyaltyProgram->update(['status' => false]);

        return redirect()->route('master.loyalty-programs.index')
            ->with('status', 'Loyalty Program deactivated.');
    }
}

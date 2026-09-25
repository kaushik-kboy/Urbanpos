<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerLoyaltyPoint;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyPointsUpdateController extends Controller
{
    public function __construct(
        private LoyaltyService $loyaltyService
    ) {}
    public function index(Request $request)
    {
        $selectedCustomerId = $request->input('customer_id');
        $selectedCustomer = $selectedCustomerId ? Customer::find($selectedCustomerId) : null;
        $customers = Customer::where('status', true)->orderBy('name')->limit(30)->get(['id', 'name', 'phone']);
        if ($selectedCustomer && !$customers->contains('id', $selectedCustomer->id)) {
            $customers->prepend($selectedCustomer);
        }

        $query = CustomerLoyaltyPoint::with(['customer', 'creator'])
            ->whereIn('type', ['Adjustment_Add', 'Adjustment_Deduct'])
            ->latest();

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        $adjustments = $query->paginate(20)->withQueryString();

        return view('master.loyalty-points.index', compact('customers', 'adjustments'));
    }

    public function customerPoints(Customer $customer): JsonResponse
    {
        $data = $this->loyaltyService->getCustomerLoyalty($customer);

        return response()->json($data);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'direction' => ['required', 'in:Add,Deduct'],
            'points' => ['required', 'numeric', 'min:0.5'],
            'remarks' => ['required', 'string', 'max:255'],
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);

        // If deducting, ensure customer has sufficient balance
        if ($validated['direction'] === 'Deduct') {
            $currentBalance = $customer->loyaltyBalance();
            if ($currentBalance < (float) $validated['points']) {
                return back()->withInput()->withErrors([
                    'points' => "Cannot deduct {$validated['points']} points. Customer only has {$currentBalance} points.",
                ]);
            }
        }

        $this->loyaltyService->manualAdjustment(
            customerId: (int) $validated['customer_id'],
            direction: $validated['direction'],
            points: (float) $validated['points'],
            remarks: $validated['remarks'],
            userId: auth()->id()
        );

        return redirect()->route('master.loyalty-points.index')
            ->with('status', "Loyalty points {$validated['direction']}ed successfully for {$customer->name}.");
    }
}

use App\Services\GST\Gstr1ReportService;
use App\Models\Customer;
use App\Models\SalesBill;

try {
    $user = \App\Models\User::find(677) ?: \App\Models\User::first();
    auth()->login($user);

    echo "=== PHASE 5L: HISTORICAL GSTIN AUDIT ===\n";

    $today = date('Y-m-d');
    $customer = Customer::find(18); // P5 B2B Local Corp
    $originalGstin = $customer->gst_no;
    echo "Original Customer Master GSTIN: {$originalGstin}\n";

    // 1. Check posted bill SB 1 (id=45)
    $sb1 = SalesBill::find(45);
    echo "SB 1 Snapshot GSTIN before master change: {$sb1->customer_gstin}\n";

    // 2. Change customer master GSTIN to GSTIN B
    $modifiedGstin = '24AABCP9999Z9Z9';
    $customer->update(['gst_no' => $modifiedGstin]);
    echo "Updated Customer Master GSTIN to: {$modifiedGstin}\n";

    // 3. Re-run GSTR-1 and check if historical SB 1 preserves original GSTIN A
    $service = new Gstr1ReportService($today, $today);
    $b2b = $service->b2b();
    $b2bRow = collect($b2b['rows'])->firstWhere('ref', 'SB-2026-0735');

    echo "GSTR-1 B2B Report for SB-2026-0735 after master change:\n";
    echo "  Reported GSTIN: {$b2bRow['gstin']}\n";
    echo "  Expected GSTIN: {$originalGstin}\n";

    if ($b2bRow['gstin'] === $originalGstin) {
        echo "CHECK PASS: Historical transaction preserved posting-time GSTIN A, unaffected by customer master change.\n";
    } else {
        echo "CHECK FAIL: Historical transaction GSTIN was mutated by customer master change!\n";
    }

    // 4. Restore original GSTIN
    $customer->update(['gst_no' => $originalGstin]);
    echo "Restored Customer Master GSTIN to: {$customer->fresh()->gst_no}\n";

    echo "\n=== PHASE 5L COMPLETE ===\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}

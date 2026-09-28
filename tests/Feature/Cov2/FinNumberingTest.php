<?php

namespace Tests\Feature\Cov2;

use App\Models\Branch;
use App\Models\DocumentSequence;
use App\Models\ItemStock;
use App\Models\SalesOrder;
use App\Models\StockLedger;
use App\Models\SystemErrorLog;
use App\Services\Accounting\DocumentNumberingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinNumberingTest extends TestCase
{
    use FinHelper;

    private function svc(): DocumentNumberingService
    {
        return app(DocumentNumberingService::class);
    }

    private function order(string $number): SalesOrder
    {
        return SalesOrder::create([
            'order_number' => $number, 'order_date' => now()->toDateString(),
            'customer_id' => $this->cust->id, 'branch_id' => $this->branch->id,
        ]);
    }

    private function seq(string $type, array $over = []): DocumentSequence
    {
        return DocumentSequence::create(array_merge([
            'document_type' => $type, 'document_title' => $type, 'prefix' => 'T-{YEAR}-', 'padding_zeros' => 3,
            'starting_number' => 1, 'last_number' => 0, 'reset_frequency' => 'never', 'is_active' => true,
            'series' => 'doc:'.$type.':'.uniqid(),
        ], $over));
    }

    public function test_next_increments_a_named_series_independently(): void
    {
        $this->assertSame(1, $this->svc()->next('unit:a'));
        $this->assertSame(2, $this->svc()->next('unit:a'));
        $this->assertSame(1, $this->svc()->next('unit:b'));
        $this->assertSame(2, (int) DocumentSequence::where('series', 'unit:a')->value('last_number'));
    }

    public function test_generate_bootstraps_defaults_and_creates_gate_row(): void
    {
        $y = now()->format('Y');
        $this->assertSame("SO-$y-0001", $this->svc()->generate('sales_order', $this->branch->id));
        $this->assertSame("SO-$y-0002", $this->svc()->generate('sales_order', $this->branch->id));

        $seq = DocumentSequence::where('document_type', 'sales_order')->firstOrFail();
        $this->assertSame($this->branch->id, $seq->branch_id);
        $this->assertSame(2, $seq->last_number);
        $this->assertSame('financial_year', $seq->reset_frequency);
        $gate = DB::table('document_sequences')->where('series', 'gate:sales_order')->first();
        $this->assertNotNull($gate);
        $this->assertSame(2, (int) $gate->last_number);
        $this->assertSame(0, (int) $gate->is_active, 'gate rows must never be picked as real sequences');
    }

    public function test_generate_for_unknown_type_derives_prefix_from_name(): void
    {
        $y = now()->format('Y');
        $this->assertSame("GI-$y-0001", $this->svc()->generate('gift_card'));
        $this->assertSame('Gift Card', DocumentSequence::where('document_type', 'gift_card')->value('document_title'));
    }

    public function test_branches_without_branch_token_share_one_number_space(): void
    {
        $b2 = Branch::create(['name' => 'Second', 'code' => 'SEC', 'state' => 'Gujarat']);
        $y = now()->format('Y');
        $this->assertSame("SO-$y-0001", $this->svc()->generate('sales_order', $this->branch->id));
        // second branch gets its own counter row but continues the shared space (no duplicate number)
        $this->assertSame("SO-$y-0002", $this->svc()->generate('sales_order', $b2->id));
        $this->assertSame("SO-$y-0003", $this->svc()->generate('sales_order', $this->branch->id));
        $this->assertSame(2, DocumentSequence::where('document_type', 'sales_order')->count());
    }

    public function test_branch_token_gives_independent_counters_and_no_gate(): void
    {
        $b2 = Branch::create(['name' => 'Second', 'code' => 'SEC', 'state' => 'Gujarat']);
        $this->seq('brdoc', ['prefix' => '{BRANCH}/', 'branch_id' => $this->branch->id, 'padding_zeros' => 3]);
        $this->seq('brdoc', ['prefix' => '{BRANCH}/', 'branch_id' => $b2->id, 'padding_zeros' => 3]);

        $this->assertSame('FIN/001', $this->svc()->generate('brdoc', $this->branch->id));
        $this->assertSame('FIN/002', $this->svc()->generate('brdoc', $this->branch->id));
        $this->assertSame('SEC/001', $this->svc()->generate('brdoc', $b2->id));
        $this->assertNull(DB::table('document_sequences')->where('series', 'gate:brdoc')->first());
    }

    public function test_starting_number_and_suffix_are_honoured(): void
    {
        $this->seq('startdoc', ['prefix' => 'S-', 'suffix' => '/{FY}', 'starting_number' => 100, 'padding_zeros' => 4]);
        $fy = (new DocumentSequence)->resolveTokens('{FY}');
        $this->assertSame("S-0100/$fy", $this->svc()->generate('startdoc'));
        $this->assertSame("S-0101/$fy", $this->svc()->generate('startdoc'));
    }

    public function test_monthly_reset_restarts_counter_in_new_period_only(): void
    {
        $this->seq('mdoc', ['prefix' => 'M-', 'reset_frequency' => 'monthly', 'last_number' => 5, 'last_reset_period' => '2026-01']);
        $this->assertSame('M-006', $this->svc()->generate('mdoc', null, '2026-01-20'));   // same period, keeps counting
        $this->assertSame('M-001', $this->svc()->generate('mdoc', null, '2026-02-03'));   // new month, reset
        $this->assertSame('2026-02', DocumentSequence::where('document_type', 'mdoc')->value('last_reset_period'));
        $this->assertSame('M-002', $this->svc()->generate('mdoc', null, '2026-02-28'));
    }

    public function test_yearly_and_financial_year_resets_and_never(): void
    {
        $this->seq('ydoc', ['prefix' => 'Y-', 'reset_frequency' => 'yearly', 'last_number' => 9, 'last_reset_period' => '2025']);
        $this->assertSame('Y-001', $this->svc()->generate('ydoc', null, '2026-01-01'));

        // financial year: 31 Mar 2026 is still FY 2025-2026, 1 Apr 2026 opens 2026-2027
        $this->seq('fdoc', ['prefix' => 'F-', 'reset_frequency' => 'financial_year', 'last_number' => 0, 'last_reset_period' => '2025-2026']);
        $this->assertSame('F-001', $this->svc()->generate('fdoc', null, '2026-03-31'));
        $this->assertSame('F-002', $this->svc()->generate('fdoc', null, '2026-03-31'));
        $this->assertSame('F-001', $this->svc()->generate('fdoc', null, '2026-04-01'));

        $this->seq('ndoc', ['prefix' => 'N-', 'reset_frequency' => 'never', 'last_number' => 40, 'last_reset_period' => '2020']);
        $this->assertSame('N-041', $this->svc()->generate('ndoc', null, '2030-06-01'));
    }

    public function test_generate_skips_numbers_already_used_in_the_target_table(): void
    {
        $y = now()->format('Y');
        $this->order("SO-$y-0001");
        $this->order("SO-$y-0002");
        $this->order("SO-$y-0003");
        // fresh counter aligns to the latest matching document rather than colliding on the unique key
        $this->assertSame("SO-$y-0004", $this->svc()->generate('sales_order', $this->branch->id));

        // stale counter row (last_number behind reality) still never hands out a used number
        DocumentSequence::where('document_type', 'sales_order')->update(['last_number' => 1]);
        DB::table('document_sequences')->where('series', 'gate:sales_order')->update(['last_number' => 0]);
        $this->order("SO-$y-0004");
        $this->assertSame("SO-$y-0005", $this->svc()->generate('sales_order', $this->branch->id));
    }

    public function test_generate_result_is_unique_in_the_document_table(): void
    {
        $nums = [];
        for ($i = 0; $i < 4; $i++) {
            $n = $this->svc()->generate('sales_order', $this->branch->id);
            $this->order($n);
            $nums[] = $n;
        }
        $this->assertCount(4, array_unique($nums));
    }

    public function test_inactive_sequence_is_ignored_and_default_used(): void
    {
        $this->seq('sales_quotation', ['prefix' => 'OLD-', 'is_active' => false, 'series' => 'doc:sq-old']);
        $y = now()->format('Y');
        $this->assertSame("SQ-$y-0001", $this->svc()->generate('sales_quotation'));
    }

    public function test_preview_never_consumes_a_number(): void
    {
        $y = now()->format('Y');
        $this->assertSame("SO-$y-0001", $this->svc()->preview('sales_order'));
        $this->assertSame("SO-$y-0001", $this->svc()->preview('sales_order'));
        $this->assertSame(0, DocumentSequence::where('document_type', 'sales_order')->count());
        $this->assertSame('DOC-2026-0001', $this->svc()->preview('does_not_exist'));

        $this->seq('pdoc', ['prefix' => 'P-{BRANCH}-', 'branch_id' => $this->branch->id, 'last_number' => 6, 'padding_zeros' => 3]);
        $this->assertSame('P-FIN-007', $this->svc()->preview('pdoc', $this->branch->id));
        $this->assertSame(6, (int) DocumentSequence::where('document_type', 'pdoc')->value('last_number'));
        // branch-specific row is preferred over a global one
        $this->seq('pdoc', ['prefix' => 'G-', 'last_number' => 0, 'series' => 'doc:pdoc-global']);
        $this->assertSame('P-FIN-007', $this->svc()->preview('pdoc', $this->branch->id));
        $this->assertSame('G-001', $this->svc()->preview('pdoc'));
    }

    public function test_next_prefixed_consumes_seeds_from_existing_and_previews(): void
    {
        $this->order('SOX00007');
        // the seed is max(highest suffix already used, highest row id) so the first number is one past that
        $floor = max((int) SalesOrder::max('id'), 7);
        $pad = fn (int $n) => 'SOX'.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
        $this->assertSame($pad($floor + 1), $this->svc()->nextPrefixed('SOX', SalesOrder::class, 'order_number', 5, false));
        $this->assertSame($pad($floor + 1), $this->svc()->nextPrefixed('SOX', SalesOrder::class, 'order_number', 5, false), 'preview does not consume');
        $this->assertSame(0, DB::table('document_sequences')->where('series', 'legacy:SOX:order_number')->count());

        $first = $this->svc()->nextPrefixed('SOX', SalesOrder::class, 'order_number');
        $this->assertSame($pad($floor + 1), $first);
        $this->order($first);
        $this->assertSame($pad($floor + 2), $this->svc()->nextPrefixed('SOX', SalesOrder::class, 'order_number'));
        $this->assertSame($floor + 2, (int) DB::table('document_sequences')->where('series', 'legacy:SOX:order_number')->value('last_number'));
        $this->assertSame($pad($floor + 3), $this->svc()->nextPrefixed('SOX', SalesOrder::class, 'order_number', 5, false));

        // a colliding number already in the table is skipped
        $this->order($pad($floor + 3));
        $this->assertSame($pad($floor + 4), $this->svc()->nextPrefixed('SOX', SalesOrder::class, 'order_number'));
    }

    public function test_reset_is_not_defeated_by_the_shared_gate_and_branches_share_the_new_period(): void
    {
        $b2 = Branch::create(['name' => 'Second', 'code' => 'SEC', 'state' => 'Gujarat']);
        $this->seq('rdoc', ['prefix' => 'R-', 'reset_frequency' => 'monthly', 'last_number' => 0, 'last_reset_period' => '2026-01', 'branch_id' => $this->branch->id, 'series' => 'doc:rdoc:a']);
        $this->seq('rdoc', ['prefix' => 'R-', 'reset_frequency' => 'monthly', 'last_number' => 0, 'last_reset_period' => '2026-01', 'branch_id' => $b2->id, 'series' => 'doc:rdoc:b']);

        $this->assertSame('R-001', $this->svc()->generate('rdoc', $this->branch->id, '2026-01-10'));
        $this->assertSame('R-002', $this->svc()->generate('rdoc', $b2->id, '2026-01-11'));
        $this->assertSame('R-003', $this->svc()->generate('rdoc', $this->branch->id, '2026-01-12'));
        // February: first branch to generate restarts the shared space, the other continues it (still no duplicates)
        $this->assertSame('R-001', $this->svc()->generate('rdoc', $this->branch->id, '2026-02-01'));
        $this->assertSame('R-002', $this->svc()->generate('rdoc', $b2->id, '2026-02-02'));
        $this->assertSame('2026-02', DB::table('document_sequences')->where('series', 'gate:rdoc')->value('last_reset_period'));
    }

    public function test_period_and_token_helpers(): void
    {
        $d = Carbon::parse('2026-02-05');
        $this->assertSame('2026', DocumentSequence::getCurrentPeriod('yearly', $d));
        $this->assertSame('2026-02', DocumentSequence::getCurrentPeriod('monthly', $d));
        $this->assertSame('2025-2026', DocumentSequence::getCurrentPeriod('financial_year', $d));
        $this->assertSame('2026-2027', DocumentSequence::getCurrentPeriod('financial_year', Carbon::parse('2026-04-01')));
        $this->assertSame('continuous', DocumentSequence::getCurrentPeriod('never', $d));

        $seq = new DocumentSequence;
        $this->assertSame('25-26|2025-2026|2526|26|2026|02|2|05', $seq->resolveTokens('{FY}|{FY_LONG}|{FY_NUM}|{YY}|{YEAR}|{MONTH}|{M}|{DAY}', null, $d));
        // branch code falls back to the name's first 3 alphanumerics, then MAIN
        $noCode = new Branch(['name' => 'ab-cd Store']);
        $this->assertSame('ABC', $seq->resolveTokens('{BRANCH}', $noCode));
        $this->assertSame('MAIN', $seq->resolveTokens('{BRANCH}'));
    }

    // ---- Models: ItemStock, SystemErrorLog ---------------------------------

    public function test_item_stock_adjust_posts_a_correction_ledger_row_and_moves_quantity(): void
    {
        $this->seedStock($this->item, 10);
        ItemStock::adjust($this->item->id, $this->branch->id, -3);
        $this->assertEquals(7.0, $this->stockOf($this->item));
        $row = StockLedger::where('item_id', $this->item->id)->where('movement_type', 'CORRECTION')->latest('id')->firstOrFail();
        $this->assertEquals(3.0, (float) $row->qty_out);
        $this->assertEquals(0.0, (float) $row->qty_in);
        $this->assertEquals(7.0, (float) $row->running_balance_qty);
        $this->assertNull($row->reference_type);

        ItemStock::adjust($this->item->id, $this->branch->id, 5);
        $this->assertEquals(12.0, $this->stockOf($this->item));
    }

    public function test_item_stock_relations_and_casts(): void
    {
        $this->seedStock($this->item, 4.5);
        $s = ItemStock::where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame($this->item->id, $s->item->id);
        $this->assertSame($this->branch->id, $s->branch->id);
        $this->assertSame('4.500', (string) $s->quantity);
    }

    public function test_system_error_log_scopes_and_relations(): void
    {
        $mk = fn (array $o) => SystemErrorLog::create(array_merge([
            'module' => 'sales', 'error_type' => 'E', 'message' => 'm', 'error_hash' => md5(uniqid()), 'status' => 'Unresolved',
            'user_id' => $this->owner->id, 'branch_id' => $this->branch->id, 'resolved_by' => $this->owner->id,
            'request_data' => ['a' => 1],
        ], $o));
        $a = $mk(['module' => 'sales', 'status' => 'Unresolved']);
        $b = $mk(['module' => 'purchase', 'status' => 'Resolved']);
        DB::table('system_error_logs')->where('id', $a->id)->update(['created_at' => '2026-01-10 10:00:00']);
        DB::table('system_error_logs')->where('id', $b->id)->update(['created_at' => '2026-03-10 10:00:00']);

        $this->assertSame([$b->id], SystemErrorLog::forModule('purchase')->pluck('id')->all());
        $this->assertCount(2, SystemErrorLog::forModule('all')->get());
        $this->assertCount(2, SystemErrorLog::forModule(null)->get());
        $this->assertSame([$b->id], SystemErrorLog::withStatus('Resolved')->pluck('id')->all());
        $this->assertCount(2, SystemErrorLog::withStatus('all')->get());
        $this->assertSame([$a->id], SystemErrorLog::dateBetween('2026-01-01', '2026-02-01')->pluck('id')->all());
        $this->assertSame([$b->id], SystemErrorLog::dateBetween('2026-02-01', null)->pluck('id')->all());
        $this->assertCount(2, SystemErrorLog::dateBetween(null, null)->get());

        $fresh = SystemErrorLog::find($a->id);
        $this->assertSame(['a' => 1], $fresh->request_data);
        $this->assertSame($this->owner->id, $fresh->user->id);
        $this->assertSame($this->branch->id, $fresh->branch->id);
        $this->assertSame($this->owner->id, $fresh->resolver->id);
    }
}

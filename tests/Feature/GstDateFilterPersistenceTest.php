<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GstDateFilterPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // create user and authenticate
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    public function test_integrations_gst_page_renders_and_links_preserve_dates(): void
    {
        $response = $this->get(route('tools.integrations-gst', [
            'view' => 'returns',
            'from_date' => '2026-10-01',
            'to_date' => '2026-10-31',
        ]));

        $response->assertStatus(200);
        $response->assertSee('value="2026-10-01"', false);
        $response->assertSee('value="2026-10-31"', false);
        $response->assertSee(route('tools.gst.gstr-1.page', ['from_date' => '2026-10-01', 'to_date' => '2026-10-31']));
        $response->assertSee(route('tools.gst.gstr-3b.page', ['from_date' => '2026-10-01', 'to_date' => '2026-10-31']));
        $response->assertSee(route('tools.gst.gstr-2.page', ['from_date' => '2026-10-01', 'to_date' => '2026-10-31']));
    }

    public function test_gstr1_page_preserves_period_and_section_links_include_dates(): void
    {
        $response = $this->get(route('tools.gst.gstr-1.page', [
            'from_date' => '2026-10-01',
            'to_date' => '2026-10-31',
        ]));

        $response->assertStatus(200);
        $response->assertSee('value="2026-10-01"', false);
        $response->assertSee('value="2026-10-31"', false);
        $response->assertSee(route('tools.gst.gstr-1.section', ['section' => 'b2b-hsn', 'from_date' => '2026-10-01', 'to_date' => '2026-10-31']));
        $response->assertSee(route('tools.gst.gstr-3b.page', ['from_date' => '2026-10-01', 'to_date' => '2026-10-31']));
        $response->assertSee(route('tools.gst.gstr-2.page', ['from_date' => '2026-10-01', 'to_date' => '2026-10-31']));
        $response->assertSee(route('tools.integrations-gst', ['view' => 'returns', 'from_date' => '2026-10-01', 'to_date' => '2026-10-31']));
    }

    public function test_gstr1_section_view_preserves_dates_in_back_link(): void
    {
        $response = $this->get(route('tools.gst.gstr-1.section', [
            'section' => 'b2c-hsn',
            'from_date' => '2026-10-01',
            'to_date' => '2026-10-31',
        ]));

        $response->assertStatus(200);
        $response->assertSee(route('tools.gst.gstr-1.page', ['from_date' => '2026-10-01', 'to_date' => '2026-10-31']));
        $response->assertSee('01 Oct 2026 to 31 Oct 2026');
        $response->assertSee(route('tools.gst.gstr-1.section.export', ['section' => 'b2c-hsn', 'from_date' => '2026-10-01', 'to_date' => '2026-10-31']));
        $response->assertDontSee('reports.gst-sales-summary');
    }

    public function test_gstr1_section_excel_export_works_for_all_cards(): void
    {
        $sections = ['b2c-hsn', 'b2b-hsn', 'b2b', 'b2cs', 'b2cl', 'cdnr', 'cdnur', 'nil', 'doc-issued'];

        foreach ($sections as $sec) {
            $response = $this->get(route('tools.gst.gstr-1.section.export', [
                'section' => $sec,
                'from_date' => '2026-10-01',
                'to_date' => '2026-10-31',
            ]));

            $response->assertStatus(200);
            $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
            $disposition = $response->headers->get('Content-Disposition');
            $this->assertStringContainsString("GSTR1_{$sec}_20261001_20261031.csv", $disposition);
        }
    }

    public function test_gstr3b_and_gstr2_views_preserve_dates(): void
    {
        $res3b = $this->get(route('tools.gst.gstr-3b.page', [
            'from_date' => '2026-10-01',
            'to_date' => '2026-10-31',
        ]));
        $res3b->assertStatus(200);
        $res3b->assertSee('value="2026-10-01"', false);
        $res3b->assertSee(route('tools.gst.gstr-1.page', ['from_date' => '2026-10-01', 'to_date' => '2026-10-31']));

        $res2 = $this->get(route('tools.gst.gstr-2.page', [
            'from_date' => '2026-10-01',
            'to_date' => '2026-10-31',
        ]));
        $res2->assertStatus(200);
        $res2->assertSee('value="2026-10-01"', false);
        $res2->assertSee(route('tools.gst.gstr-1.page', ['from_date' => '2026-10-01', 'to_date' => '2026-10-31']));
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time QA-data backfill (see docs/GST-PURCHASE-SUMMARY-EXPORT.md §6):
 * found while building the invoice-wise GST Purchase Summary export that
 * 144,517 of 144,575 purchase_invoice_items rows (99.96%, the entire
 * pre-existing QA seed dataset) have a correct blended `gst_tax_amount` but
 * NEVER had it split into `cgst_amount`/`sgst_amount`/`igst_amount` — those
 * three columns are all 0 even where gst_tax_amount > 0. Confirmed this is a
 * seed-data gap, not a live app bug: PurchaseInvoiceController::computeLines()
 * DOES correctly compute and store the split for every invoice created
 * through the real UI (the ~57 rows created today, right before this backfill
 * was written, already have the split).
 *
 * This derives the missing split from data the row ALREADY has — its own
 * stored gst_tax_amount and its parent invoice's purchase_type — using
 * standard, universal GST math (Local/intra-state = CGST+SGST each half the
 * rate; Interstate = full amount to IGST). Nothing is guessed or invented;
 * only rows where the split is currently entirely zero are touched, so a
 * correctly-split row (old or new) is never overwritten. Idempotent — safe
 * to run more than once, a second run updates 0 rows.
 *
 * QA/test database only — this must never be pointed at production.
 */
class BackfillPurchaseGstSplit extends Command
{
    protected $signature = 'purchase:backfill-gst-split {--dry-run : Report counts without writing}';

    protected $description = 'Backfill missing cgst_amount/sgst_amount/igst_amount on purchase_invoice_items (and parent purchase_invoices totals) from gst_tax_amount + purchase_type, for QA seed data only';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $connectionName = DB::connection()->getName();
        $database = DB::connection()->getDatabaseName();
        $this->info("Connection: {$connectionName} / database: {$database}");

        if (! $this->confirm("This will backfill purchase_invoice_items GST splits on database '{$database}'. Confirm this is the QA/test database, not production.", true)) {
            $this->warn('Aborted.');
            return self::FAILURE;
        }

        $affectedItems = DB::table('purchase_invoice_items as pii')
            ->join('purchase_invoices as pi', 'pi.id', '=', 'pii.purchase_invoice_id')
            ->where('pii.gst_tax_amount', '>', 0)
            ->where('pii.cgst_amount', 0)
            ->where('pii.sgst_amount', 0)
            ->where('pii.igst_amount', 0)
            ->count();

        $this->info("Items with a missing GST split (gst_tax_amount > 0, cgst=sgst=igst=0): {$affectedItems}");

        if ($affectedItems === 0) {
            $this->info('Nothing to backfill.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('Dry run — no rows written.');
            return self::SUCCESS;
        }

        DB::transaction(function () {
            // Local (intra-state): split the already-stored gst_tax_amount
            // evenly into CGST + SGST (standard rule: CGST% = SGST% = rate/2).
            // Interstate: the full gst_tax_amount is IGST.
            $itemsUpdated = DB::update("
                UPDATE purchase_invoice_items pii
                JOIN purchase_invoices pi ON pi.id = pii.purchase_invoice_id
                SET
                    pii.cgst_amount = CASE WHEN pi.purchase_type = 'Local' THEN ROUND(pii.gst_tax_amount / 2, 2) ELSE pii.cgst_amount END,
                    pii.sgst_amount = CASE WHEN pi.purchase_type = 'Local' THEN ROUND(pii.gst_tax_amount / 2, 2) ELSE pii.sgst_amount END,
                    pii.igst_amount = CASE WHEN pi.purchase_type != 'Local' THEN pii.gst_tax_amount ELSE pii.igst_amount END
                WHERE pii.gst_tax_amount > 0 AND pii.cgst_amount = 0 AND pii.sgst_amount = 0 AND pii.igst_amount = 0
            ");
            $this->info("purchase_invoice_items rows updated: {$itemsUpdated}");

            // Re-sum the invoice header's own total_cgst/total_sgst/total_igst
            // from its (now-fixed) line items — but only for invoices whose
            // header totals were ALSO entirely zero (the same gap, one level
            // up). An invoice with an already-correct non-zero header total
            // is left untouched.
            $invoicesUpdated = DB::update("
                UPDATE purchase_invoices pi
                JOIN (
                    SELECT purchase_invoice_id, SUM(cgst_amount) AS c, SUM(sgst_amount) AS s, SUM(igst_amount) AS i
                    FROM purchase_invoice_items
                    GROUP BY purchase_invoice_id
                ) agg ON agg.purchase_invoice_id = pi.id
                SET pi.total_cgst = agg.c, pi.total_sgst = agg.s, pi.total_igst = agg.i
                WHERE pi.total_cgst = 0 AND pi.total_sgst = 0 AND pi.total_igst = 0 AND pi.total_gst > 0
            ");
            $this->info("purchase_invoices header rows updated: {$invoicesUpdated}");
        });

        // Verify: no row should still have gst_tax_amount > 0 with all three
        // split columns at zero.
        $remaining = DB::table('purchase_invoice_items')
            ->where('gst_tax_amount', '>', 0)
            ->where('cgst_amount', 0)->where('sgst_amount', 0)->where('igst_amount', 0)
            ->count();
        $this->info("Remaining un-split items after backfill: {$remaining}");

        return self::SUCCESS;
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Branch;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Find Satellite branch and Motera branch
        $satelliteBranch = Branch::where('name', 'like', '%SATELLITE%')
            ->orWhere('name', 'like', '%URBANPETS SERVICES%')
            ->orWhere('address_line2', 'like', '%SATELLITE%')
            ->first();

        $moteraBranch = Branch::where('name', 'like', '%MOTERA%')
            ->orWhere('address_line2', 'like', '%MOTERA%')
            ->first();

        // 2. Identify target branch for reassigning orphaned GLOBAL records (fallback to Satellite or Motera)
        $targetBranchId = $satelliteBranch?->id ?? $moteraBranch?->id ?? 2;

        // 3. Find Global branch if exists
        $globalBranch = Branch::where('name', 'GLOBAL')->first();

        if ($globalBranch) {
            $globalId = $globalBranch->id;

            // Reassign or clear nullable foreign keys
            if (Schema::hasTable('users') && Schema::hasColumn('users', 'branch_id')) {
                DB::table('users')->where('branch_id', $globalId)->update(['branch_id' => null]);
            }
            if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'branch_id')) {
                DB::table('customers')->where('branch_id', $globalId)->update(['branch_id' => null]);
            }
            if (Schema::hasTable('areas') && Schema::hasColumn('areas', 'branch_id')) {
                DB::table('areas')->where('branch_id', $globalId)->update(['branch_id' => null]);
            }
            if (Schema::hasTable('tender_types') && Schema::hasColumn('tender_types', 'branch_id')) {
                DB::table('tender_types')->where('branch_id', $globalId)->update(['branch_id' => null]);
            }
            if (Schema::hasTable('tender_type_values') && Schema::hasColumn('tender_type_values', 'branch_id')) {
                DB::table('tender_type_values')->where('branch_id', $globalId)->update(['branch_id' => null]);
            }
            if (Schema::hasTable('audit_logs') && Schema::hasColumn('audit_logs', 'branch_id')) {
                DB::table('audit_logs')->where('branch_id', $globalId)->update(['branch_id' => null]);
            }

            // Clean up standalone single-branch dependencies
            if (Schema::hasTable('document_sequences') && Schema::hasColumn('document_sequences', 'branch_id')) {
                DB::table('document_sequences')->where('branch_id', $globalId)->delete();
            }
            if (Schema::hasTable('item_stocks') && Schema::hasColumn('item_stocks', 'branch_id')) {
                DB::table('item_stocks')->where('branch_id', $globalId)->delete();
            }
            if (Schema::hasTable('receipt_settings') && Schema::hasColumn('receipt_settings', 'branch_id')) {
                DB::table('receipt_settings')->where('branch_id', $globalId)->delete();
            }

            // Reassign RESTRICT relational tables to target branch
            $tablesToReassign = [
                'journal_entries',
                'sales_bills',
                'sales_returns',
                'sales_orders',
                'sales_quotations',
                'sales_delivery_notes',
                'bill_settlements',
                'purchase_invoices',
                'purchase_orders',
                'purchase_returns',
                'purchase_receipt_notes',
                'purchase_indents',
                'opening_stocks',
                'stock_updates',
                'damage_stocks',
                'stock_ledger',
                'registers',
                'system_error_logs',
                'till_sessions',
            ];

            foreach ($tablesToReassign as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')) {
                    DB::table($table)->where('branch_id', $globalId)->update(['branch_id' => $targetBranchId]);
                }
            }

            if (Schema::hasTable('stock_transfers')) {
                if (Schema::hasColumn('stock_transfers', 'from_branch_id')) {
                    DB::table('stock_transfers')->where('from_branch_id', $globalId)->update(['from_branch_id' => $targetBranchId]);
                }
                if (Schema::hasColumn('stock_transfers', 'to_branch_id')) {
                    DB::table('stock_transfers')->where('to_branch_id', $globalId)->update(['to_branch_id' => $targetBranchId]);
                }
            }

            // Permanently remove the GLOBAL branch row
            DB::table('branches')->where('id', $globalId)->delete();
        }

        // 4. Update Satellite branch name and ERP code
        if ($satelliteBranch) {
            $satelliteBranch->update([
                'name'     => 'Urban pets- satellite -01',
                'erp_code' => '01',
                'status'   => true,
            ]);
        }

        // 5. Update Motera branch name and ERP code
        if ($moteraBranch) {
            $moteraBranch->update([
                'name'     => 'Urban Pets - motera  -02',
                'erp_code' => '02',
                'status'   => true,
            ]);
        }
    }

    public function down(): void
    {
        // No-op
    }
};

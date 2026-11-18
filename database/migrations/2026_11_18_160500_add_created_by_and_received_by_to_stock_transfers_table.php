<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_transfers', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('stock_transfers', 'received_by')) {
                $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        // Backfill historical transfers from stock_ledger
        try {
            $transfers = DB::table('stock_transfers')->select('id')->get();
            foreach ($transfers as $t) {
                $createdUserId = DB::table('stock_ledger')
                    ->where('reference_type', 'like', '%StockTransfer%')
                    ->where('reference_id', $t->id)
                    ->where('movement_type', 'TRANSFER_OUT')
                    ->whereNotNull('user_id')
                    ->value('user_id');

                $receivedUserId = DB::table('stock_ledger')
                    ->where('reference_type', 'like', '%StockTransfer%')
                    ->where('reference_id', $t->id)
                    ->where('movement_type', 'TRANSFER_IN')
                    ->whereNotNull('user_id')
                    ->value('user_id');

                $updates = [];
                if ($createdUserId) {
                    $updates['created_by'] = $createdUserId;
                }
                if ($receivedUserId) {
                    $updates['received_by'] = $receivedUserId;
                }
                if (!empty($updates)) {
                    DB::table('stock_transfers')->where('id', $t->id)->update($updates);
                }
            }
        } catch (\Throwable $e) {
            // Ignore if tables don't exist yet during initial testing
        }
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            if (Schema::hasColumn('stock_transfers', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
            if (Schema::hasColumn('stock_transfers', 'received_by')) {
                $table->dropForeign(['received_by']);
                $table->dropColumn('received_by');
            }
        });
    }
};

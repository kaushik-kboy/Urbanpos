<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business rule (2026-10-10 client decision): a recount resubmission preserves the
 * parent observation's batch_no by DEFAULT. Changing it requires an explicit,
 * authorized "batch correction" action, and that action must be audited: who, from
 * what, to what, when, why. See URBANPETS_DATABASE_AND_WORKFLOW.md §8 item 17.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_observations', function (Blueprint $table) {
            $table->boolean('batch_correction_applied')->default(false)->after('batch_no');
            $table->string('original_batch_no')->nullable()->after('batch_correction_applied');
            $table->text('batch_correction_reason')->nullable()->after('original_batch_no');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_observations', function (Blueprint $table) {
            $table->dropColumn(['batch_correction_applied', 'original_batch_no', 'batch_correction_reason']);
        });
    }
};

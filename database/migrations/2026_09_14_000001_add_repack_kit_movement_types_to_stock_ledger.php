<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_VALUES = [
        'OPENING', 'PURCHASE_RECEIPT', 'PURCHASE_RETURN', 'SALE', 'SALE_RETURN',
        'TRANSFER_OUT', 'TRANSFER_TRANSIT', 'TRANSFER_IN', 'DAMAGE', 'EXPIRY',
        'SHORTAGE', 'EXCESS', 'CORRECTION',
    ];

    private const NEW_VALUES = [
        'OPENING', 'PURCHASE_RECEIPT', 'PURCHASE_RETURN', 'SALE', 'SALE_RETURN',
        'TRANSFER_OUT', 'TRANSFER_TRANSIT', 'TRANSFER_IN', 'DAMAGE', 'EXPIRY',
        'SHORTAGE', 'EXCESS', 'CORRECTION', 'REPACK', 'KIT_ASSEMBLY', 'KIT_DISASSEMBLY',
    ];

    public function up(): void
    {
        $list = implode(',', array_map(fn ($v) => "'{$v}'", self::NEW_VALUES));
        DB::statement("ALTER TABLE stock_ledger MODIFY movement_type ENUM({$list}) NOT NULL");
    }

    public function down(): void
    {
        $list = implode(',', array_map(fn ($v) => "'{$v}'", self::OLD_VALUES));
        DB::statement("ALTER TABLE stock_ledger MODIFY movement_type ENUM({$list}) NOT NULL");
    }
};

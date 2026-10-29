<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('whats_app_settings')) {
            // Set WhatsApp to Direct Formatted Message mode across all branches and global settings
            // This prevents rejection delays from unapproved Meta templates and delivers receipts instantly
            DB::table('whats_app_settings')
                ->whereIn('template_name', ['sales_invoice_motera', 'sales_invoice_satellite', 'urban_tax_invoice'])
                ->update(['template_name' => null]);

            $cleanTemplate = "🐾 *{header}* 🐾\n"
                . "*Official Tax Invoice Receipt*\n"
                . "━━━━━━━━━━━━━━━━━━━━\n"
                . "Dear *{customer_name}*,\n"
                . "Thank you for shopping with us! Here are your bill details:\n\n"
                . "📄 *Bill No:* {bill_number}\n"
                . "📅 *Date:* {date}\n"
                . "🏪 *Branch:* {branch_name}\n"
                . "🛍️ *Total Items:* {total_items}\n"
                . "💰 *Net Payable:* ₹{total}\n"
                . "💳 *Payment:* {payment_mode}\n\n"
                . "🔗 *Click to view & download your bill slip:\n"
                . "{bill_url}\n\n"
                . "━━━━━━━━━━━━━━━━━━━━\n"
                . "📞 *Store Helpline:* {store_phone}\n"
                . "🐾 *{footer}*";

            DB::table('whats_app_settings')->update(['custom_message' => $cleanTemplate]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};

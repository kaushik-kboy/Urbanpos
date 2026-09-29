use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Branch;
use App\Models\Supplier;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Brand;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\SalesBill;
use App\Models\SalesReturn;
use App\Models\StockTransfer;
use App\Models\DamageStock;
use App\Models\StockUpdate;
use App\Models\StockLedger;
use App\Models\ItemStock;

$data = [
    'users' => User::count(),
    'roles' => DB::table('roles')->pluck('name')->all(),
    'branches' => Branch::pluck('name', 'id')->all(),
    'suppliers' => Supplier::count(),
    'customers' => Customer::count(),
    'items' => Item::count(),
    'items_active' => Item::where('status', 1)->count(),
    'categories' => ItemCategory::count(),
    'brands' => Brand::count(),
    'purchase_invoices' => PurchaseInvoice::count(),
    'purchase_returns' => PurchaseReturn::count(),
    'sales_bills' => SalesBill::count(),
    'sales_returns' => SalesReturn::count(),
    'stock_transfers' => StockTransfer::count(),
    'damage_stocks' => DamageStock::count(),
    'stock_updates' => StockUpdate::count(),
    'stock_ledger_rows' => StockLedger::count(),
    'stock_ledger_movements' => StockLedger::select('movement_type', DB::raw('count(*) as cnt'))->groupBy('movement_type')->pluck('cnt', 'movement_type')->all(),
    'item_stocks' => ItemStock::count(),
    'negative_stocks' => ItemStock::where('quantity', '<', 0)->count(),
    'zero_stocks' => ItemStock::where('quantity', '=', 0)->count(),
    'positive_stocks' => ItemStock::where('quantity', '>', 0)->count(),
    'sample_items' => Item::where('status', 1)->take(5)->pluck('name', 'id')->all(),
    'sample_suppliers' => Supplier::take(5)->pluck('name', 'id')->all(),
    'sample_customers' => Customer::take(5)->pluck('name', 'id')->all(),
];

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

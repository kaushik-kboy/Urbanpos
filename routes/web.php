<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\Master\AreaController;
use App\Http\Controllers\Master\BranchController;
use App\Http\Controllers\Master\BreedController;
use App\Http\Controllers\Master\ColorController;
use App\Http\Controllers\Master\CustomerCategoryController;
use App\Http\Controllers\Master\CustomerController;
use App\Http\Controllers\Master\GstTaxController;
use App\Http\Controllers\Master\ItemCategoryController;
use App\Http\Controllers\Master\ItemCategoryValueController;
use App\Http\Controllers\Master\ItemController;
use App\Http\Controllers\Master\ItemPriceChangeController;
use App\Http\Controllers\Master\PetTypeController;
use App\Http\Controllers\Master\RegisterController;
use App\Http\Controllers\Master\SupplierController;
use App\Http\Controllers\Master\TenderTypeController;
use App\Http\Controllers\Master\TenderTypeValueController;
use App\Http\Controllers\Master\UomController;
use App\Http\Controllers\Master\BrandController;
use App\Http\Controllers\Purchase\PurchaseInvoiceController;
use App\Http\Controllers\Purchase\PurchaseOrderController;
use App\Http\Controllers\Inventory\DamageStockController;
use App\Http\Controllers\Inventory\OpeningStockController;
use App\Http\Controllers\Inventory\StockUpdateController;
use App\Http\Controllers\Sales\SalesBillController;
use App\Http\Controllers\Sales\SalesReturnController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Finance\LedgerController;
use App\Http\Controllers\Finance\VoucherController;
use App\Http\Controllers\Finance\FinanceReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', [HomeController::class, 'index'])->name('home');

Route::middleware('auth')->prefix('master')->name('master.')->group(function () {
    $masterResources = [
        'item-categories' => ItemCategoryController::class,
        'item-category-values' => ItemCategoryValueController::class,
        'brands' => BrandController::class,
        'uoms' => UomController::class,
        'items' => ItemController::class,
        'customer-categories' => CustomerCategoryController::class,
        'customers' => CustomerController::class,
        'areas' => AreaController::class,
        'pet-types' => PetTypeController::class,
        'breeds' => BreedController::class,
        'colors' => ColorController::class,
        'suppliers' => SupplierController::class,
        'gst-taxes' => GstTaxController::class,
        'branches' => BranchController::class,
        'registers' => RegisterController::class,
        'tender-types' => TenderTypeController::class,
        'tender-type-values' => TenderTypeValueController::class,
    ];

    foreach ($masterResources as $uri => $controller) {
        Route::resource($uri, $controller);
        Route::post("{$uri}/import", [$controller, 'import'])->name("{$uri}.import");
        Route::get("{$uri}/import/sample", [$controller, 'importSample'])->name("{$uri}.import-sample");
    }

    Route::get('item-price-change/search', [ItemPriceChangeController::class, 'search'])->name('item-price-change.search');
    Route::get('item-price-change', [ItemPriceChangeController::class, 'index'])->name('item-price-change.index');
    Route::post('item-price-change', [ItemPriceChangeController::class, 'update'])->name('item-price-change.update');
});

Route::middleware('auth')->prefix('purchase')->name('purchase.')->group(function () {
    Route::resource('purchase-orders', PurchaseOrderController::class);
    Route::resource('purchase-invoices', PurchaseInvoiceController::class);
});

Route::middleware('auth')->prefix('inventory')->name('inventory.')->group(function () {
    Route::resource('opening-stocks', OpeningStockController::class);
    Route::resource('damage-stocks', DamageStockController::class);
    Route::resource('stock-updates', StockUpdateController::class);
});

Route::middleware('auth')->prefix('sales')->name('sales.')->group(function () {
    Route::resource('sales-bills', SalesBillController::class);
    Route::resource('sales-returns', SalesReturnController::class);
});

Route::middleware('auth')->prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('sales-summary', [ReportController::class, 'salesSummary'])->name('sales-summary');
    Route::get('billwise-sales', [ReportController::class, 'billwiseSales'])->name('billwise-sales');
    Route::get('gst-sales-summary', [ReportController::class, 'gstSalesSummary'])->name('gst-sales-summary');
    Route::get('purchase-detail', [ReportController::class, 'purchaseDetail'])->name('purchase-detail');
    Route::get('current-stock', [ReportController::class, 'currentStock'])->name('current-stock');
    Route::get('sales-return-summary', [ReportController::class, 'salesReturnSummary'])->name('sales-return-summary');
    Route::get('customer-master', [ReportController::class, 'customerMaster'])->name('customer-master');
    Route::get('customer-pet-details', [ReportController::class, 'customerPetDetails'])->name('customer-pet-details');
});

Route::middleware('auth')->prefix('finance')->name('finance.')->group(function () {
    Route::resource('ledgers', LedgerController::class);
    Route::resource('vouchers', VoucherController::class);

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [FinanceReportController::class, 'index'])->name('index');
        Route::get('general-ledger', [FinanceReportController::class, 'generalLedger'])->name('general-ledger');
        Route::get('day-book', [FinanceReportController::class, 'dayBook'])->name('day-book');
        Route::get('trial-balance', [FinanceReportController::class, 'trialBalance'])->name('trial-balance');
    });
});

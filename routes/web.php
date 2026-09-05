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
    Route::resource('item-categories', ItemCategoryController::class);
    Route::resource('item-category-values', ItemCategoryValueController::class);
    Route::resource('brands', BrandController::class);
    Route::resource('uoms', UomController::class);
    Route::resource('items', ItemController::class);

    Route::resource('customer-categories', CustomerCategoryController::class);
    Route::resource('customers', CustomerController::class);
    Route::resource('areas', AreaController::class);
    Route::resource('pet-types', PetTypeController::class);
    Route::resource('breeds', BreedController::class);
    Route::resource('colors', ColorController::class);

    Route::resource('suppliers', SupplierController::class);
    Route::resource('gst-taxes', GstTaxController::class);
    Route::resource('branches', BranchController::class);
    Route::resource('registers', RegisterController::class);
    Route::resource('tender-types', TenderTypeController::class);
    Route::resource('tender-type-values', TenderTypeValueController::class);
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

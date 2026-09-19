<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\Master\AreaController;
use App\Http\Controllers\Master\BranchController;
use App\Http\Controllers\Master\BreedController;
use App\Http\Controllers\Master\ColorController;
use App\Http\Controllers\Master\CustomerCategoryController;
use App\Http\Controllers\Master\CustomerController;
use App\Http\Controllers\Master\FinancialYearController;
use App\Http\Controllers\Master\GstTaxController;
use App\Http\Controllers\Master\ItemCategoryController;
use App\Http\Controllers\Master\ItemCategoryValueController;
use App\Http\Controllers\Master\ItemController;
use App\Http\Controllers\Master\ItemPriceChangeController;
use App\Http\Controllers\Master\LoyaltyProgramController;
use App\Http\Controllers\Master\LoyaltyPointsUpdateController;
use App\Http\Controllers\Master\PetTypeController;
use App\Http\Controllers\Master\RegisterController;
use App\Http\Controllers\Master\SupplierController;
use App\Http\Controllers\Master\TenderTypeController;
use App\Http\Controllers\Master\TenderTypeValueController;
use App\Http\Controllers\Master\UomController;
use App\Http\Controllers\Master\BrandController;
use App\Http\Controllers\Purchase\PurchaseInvoiceController;
use App\Http\Controllers\Purchase\PurchaseIndentController;
use App\Http\Controllers\Purchase\PurchaseOrderController;
use App\Http\Controllers\Purchase\PurchaseReceiptNoteController;
use App\Http\Controllers\Purchase\PurchaseReturnController;
use App\Http\Controllers\Inventory\DamageStockController;
use App\Http\Controllers\Inventory\OpeningStockController;
use App\Http\Controllers\Inventory\StockUpdateController;
use App\Http\Controllers\Inventory\StockUpdateApprovalController;
use App\Http\Controllers\Inventory\BarcodeController;
use App\Http\Controllers\Inventory\BarcodePrintingController;
use App\Http\Controllers\Inventory\PriceFixingController;
use App\Http\Controllers\Inventory\ChangeSellingController;
use App\Http\Controllers\Inventory\InventoryMoreController;
use App\Http\Controllers\Inventory\StockTransferController;
use App\Http\Controllers\Sales\SalesBillController;
use App\Http\Controllers\Sales\SalesOrderController;
use App\Http\Controllers\Sales\SalesQuotationController;
use App\Http\Controllers\Sales\SalesDeliveryNoteController;
use App\Http\Controllers\Sales\SalesReturnController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Finance\BillSettlementController;
use App\Http\Controllers\Finance\LedgerController;
use App\Http\Controllers\Finance\VoucherController;
use App\Http\Controllers\Finance\FinanceReportController;
use App\Http\Controllers\Master\MasterAuxController;
use App\Http\Controllers\Sales\SalesAuxController;
use App\Http\Controllers\Purchase\PurchaseAuxController;
use App\Http\Controllers\ToolsController;
use Illuminate\Support\Facades\Route;

/**
 * Registers a resource controller's index/create/edit/show routes ungated (any
 * authenticated user), and its store/update/destroy routes behind the matching
 * "{module}.create"/"{module}.edit"/"{module}.cancel" permission plus branch scoping.
 * A plain closure (not a top-level function declaration) — routes/web.php is re-required
 * on every test's fresh application boot, and a `function` declaration here would fatal
 * with "Cannot redeclare" on the second test.
 */
$gatedResource = function (string $uri, string $controller, string $module) {
    Route::resource($uri, $controller)->only(['index', 'create', 'edit', 'show']);

    Route::middleware(['permission:'.$module.'.create', 'branch.access'])
        ->group(fn () => Route::resource($uri, $controller)->only(['store']));

    Route::middleware(['permission:'.$module.'.edit', 'branch.access'])
        ->group(fn () => Route::resource($uri, $controller)->only(['update']));

    Route::middleware(['permission:'.$module.'.cancel', 'branch.access'])
        ->group(fn () => Route::resource($uri, $controller)->only(['destroy']));
};

Route::get('/', function () {
    return redirect()->route('login');
});

Auth::routes();

Route::get('/home', [HomeController::class, 'index'])->name('home');

Route::middleware('auth')->get('switch-branch/{branch}', \App\Http\Controllers\SwitchBranchController::class)->name('switch-branch');

Route::middleware('auth')->prefix('master')->name('master.')->group(function () use ($gatedResource) {
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
        'branches' => BranchController::class,
        'registers' => RegisterController::class,
        'tender-types' => TenderTypeController::class,
        'tender-type-values' => TenderTypeValueController::class,
        'gst-taxes' => GstTaxController::class,
    ];

    Route::get('items/generate-barcode', [ItemController::class, 'generateBarcode'])->name('items.generate-barcode');

    foreach ($masterResources as $uri => $controller) {
        $gatedResource($uri, $controller, $uri);
        Route::middleware(['permission:'.$uri.'.create', 'branch.access'])
            ->post("{$uri}/import", [$controller, 'import'])->name("{$uri}.import");
        Route::get("{$uri}/import/sample", [$controller, 'importSample'])->name("{$uri}.import-sample");
    }

    Route::get('item-price-change/search', [ItemPriceChangeController::class, 'search'])->name('item-price-change.search');
    Route::get('item-price-change', [ItemPriceChangeController::class, 'index'])->name('item-price-change.index');
    Route::middleware('permission:item-price-change.edit')
        ->post('item-price-change', [ItemPriceChangeController::class, 'update'])->name('item-price-change.update');

    // Users & Roles — Owner-only for create/edit/delete; index/create-form/edit-form/show
    // stay open to any authenticated user so staff can at least see who has access.
    $gatedResource('users', \App\Http\Controllers\Master\UserController::class, 'users');

    // Financial Years — no destroy action exists (there's no "financial-years.cancel"
    // permission, and $gatedResource always wires one), so this is a bespoke block
    // instead of $gatedResource, plus two custom lock/reopen actions.
    Route::resource('financial-years', FinancialYearController::class)->only(['index', 'create', 'edit', 'show']);
    Route::middleware(['permission:financial-years.create', 'branch.access'])
        ->group(fn () => Route::resource('financial-years', FinancialYearController::class)->only(['store']));
    Route::middleware(['permission:financial-years.edit', 'branch.access'])
        ->group(fn () => Route::resource('financial-years', FinancialYearController::class)->only(['update']));
    Route::middleware('permission:financial-years.lock')
        ->post('financial-years/{financial_year}/lock', [FinancialYearController::class, 'lock'])->name('financial-years.lock');
    Route::middleware('permission:financial-years.reopen')
        ->post('financial-years/{financial_year}/reopen', [FinancialYearController::class, 'reopen'])->name('financial-years.reopen');

    // Loyalty Programs & Points Update
    $gatedResource('loyalty-programs', LoyaltyProgramController::class, 'loyalty-programs');
    Route::get('loyalty-points', [LoyaltyPointsUpdateController::class, 'index'])->name('loyalty-points.index');
    Route::get('loyalty-points/customer/{customer}', [LoyaltyPointsUpdateController::class, 'customerPoints'])->name('loyalty-points.customer');
    Route::middleware(['permission:loyalty-programs.create', 'branch.access'])
        ->post('loyalty-points', [LoyaltyPointsUpdateController::class, 'store'])->name('loyalty-points.store');

    Route::get('aux/{module}', [MasterAuxController::class, 'renderModule'])->name('aux');
    Route::post('aux/item-ean-upc/update', [MasterAuxController::class, 'updateItemEanUpc'])->name('aux.item-ean-upc.update');
});

Route::middleware('auth')->prefix('purchase')->name('purchase.')->group(function () use ($gatedResource) {
    Route::get('purchase-invoices/item-list', [PurchaseInvoiceController::class, 'itemList'])->name('purchase-invoices.item-list');
    Route::get('purchase-invoices/lookup-item', [PurchaseInvoiceController::class, 'lookupItem'])->name('purchase-invoices.lookup-item');
    Route::get('purchase-invoices/item-details/{item}', [PurchaseInvoiceController::class, 'itemDetails'])->name('purchase-invoices.item-details');
    Route::get('purchase-indents/item-stock', [PurchaseIndentController::class, 'itemStock'])->name('purchase-indents.item-stock');
    Route::get('purchase-indents/{purchase_indent}/print', [PurchaseIndentController::class, 'print'])->name('purchase-indents.print');
    Route::middleware(['permission:purchase-indents.approve', 'branch.access'])
        ->post('purchase-indents/{purchase_indent}/approve', [PurchaseIndentController::class, 'approve'])->name('purchase-indents.approve');
    Route::middleware(['permission:purchase-indents.reject', 'branch.access'])
        ->post('purchase-indents/{purchase_indent}/reject', [PurchaseIndentController::class, 'reject'])->name('purchase-indents.reject');
    $gatedResource('purchase-indents', PurchaseIndentController::class, 'purchase-indents');
    Route::get('purchase-orders/{purchase_order}/print', [PurchaseOrderController::class, 'print'])->name('purchase-orders.print');
    $gatedResource('purchase-orders', PurchaseOrderController::class, 'purchase-orders');
    Route::get('purchase-receipt-notes/{purchaseReceiptNote}/print', [PurchaseReceiptNoteController::class, 'print'])->name('purchase-receipt-notes.print');
    $gatedResource('purchase-receipt-notes', PurchaseReceiptNoteController::class, 'purchase-receipt-notes');
    Route::get('purchase-invoices/{purchase_invoice}/print', [PurchaseInvoiceController::class, 'print'])->name('purchase-invoices.print');
    $gatedResource('purchase-invoices', PurchaseInvoiceController::class, 'purchase-invoices');
    Route::get('purchase-returns/item-list', [PurchaseReturnController::class, 'itemList'])->name('purchase-returns.item-list');
    Route::get('purchase-returns/lookup-item', [PurchaseReturnController::class, 'lookupItem'])->name('purchase-returns.lookup-item');
    Route::get('purchase-returns/invoice-items/{purchaseInvoice}', [PurchaseReturnController::class, 'invoiceItems'])->name('purchase-returns.invoice-items');
    Route::get('purchase-returns/supplier-invoices/{supplier}', [PurchaseReturnController::class, 'supplierInvoices'])->name('purchase-returns.supplier-invoices');
    Route::get('purchase-returns/{purchase_return}/print', [PurchaseReturnController::class, 'print'])->name('purchase-returns.print');
    $gatedResource('purchase-returns', PurchaseReturnController::class, 'purchase-returns');
    Route::get('aux/{module}', [PurchaseAuxController::class, 'renderModule'])->name('aux');
});

Route::middleware('auth')->prefix('inventory')->name('inventory.')->group(function () use ($gatedResource) {
    Route::get('opening-stocks/search-items', [OpeningStockController::class, 'searchItems'])->name('opening-stocks.search-items');
    Route::get('opening-stocks/item-by-code', [OpeningStockController::class, 'getItemByCode'])->name('opening-stocks.item-by-code');
    $gatedResource('opening-stocks', OpeningStockController::class, 'opening-stocks');
    Route::get('damage-stocks/search-items', [DamageStockController::class, 'searchItems'])->name('damage-stocks.search-items');
    Route::get('damage-stocks/item-by-code', [DamageStockController::class, 'getItemByCode'])->name('damage-stocks.item-by-code');
    $gatedResource('damage-stocks', DamageStockController::class, 'damage-stocks');
    $gatedResource('stock-updates', StockUpdateController::class, 'stock-updates');

    // Stock Update Approval
    Route::get('stock-update-approval', [StockUpdateApprovalController::class, 'index'])->name('stock-update-approval.index');
    Route::get('stock-update-approval/{stockUpdate}', [StockUpdateApprovalController::class, 'show'])->name('stock-update-approval.show');
    Route::middleware(['permission:stock-update-approval.approve', 'branch.access'])
        ->post('stock-update-approval/{stockUpdate}/approve', [StockUpdateApprovalController::class, 'approve'])->name('stock-update-approval.approve');
    Route::middleware(['permission:stock-update-approval.reject', 'branch.access'])
        ->post('stock-update-approval/{stockUpdate}/reject', [StockUpdateApprovalController::class, 'reject'])->name('stock-update-approval.reject');

    // Barcode Printing (legacy invoice-based)
    Route::get('barcode-printing', [BarcodePrintingController::class, 'index'])->name('barcode-printing.index');
    Route::get('barcode-printing/search-items', [BarcodePrintingController::class, 'searchItems'])->name('barcode-printing.search-items');
    Route::post('barcode-printing/print', [BarcodePrintingController::class, 'print'])->name('barcode-printing.print');
    // Barcode Printing (Phase 5 — item search + label queue)
    Route::get('barcode', [BarcodeController::class, 'index'])->name('barcode.index');
    Route::get('barcode/print', [BarcodeController::class, 'print'])->name('barcode.print');

    // Price Fixing
    Route::get('price-fixing', [PriceFixingController::class, 'index'])->name('price-fixing.index');
    Route::get('price-fixing/markup-markdown', [PriceFixingController::class, 'markupMarkdown'])->name('price-fixing.markup-markdown');
    Route::get('price-fixing/price-level', [PriceFixingController::class, 'priceLevel'])->name('price-fixing.price-level');
    Route::get('price-fixing/price-level-items', [PriceFixingController::class, 'priceLevelItems'])->name('price-fixing.price-level-items');
    Route::middleware(['permission:price-fixing.apply', 'branch.access'])
        ->post('price-fixing/apply', [PriceFixingController::class, 'apply'])->name('price-fixing.apply');

    // Change Selling
    Route::get('change-selling', [ChangeSellingController::class, 'index'])->name('change-selling.index');
    Route::middleware(['permission:change-selling.edit', 'branch.access'])
        ->post('change-selling', [ChangeSellingController::class, 'update'])->name('change-selling.update');

    // More Operations
    Route::get('repack', [InventoryMoreController::class, 'repack'])->name('repack.index');
    Route::middleware(['permission:repack.create', 'branch.access'])
        ->post('repack', [InventoryMoreController::class, 'processRepack'])->name('repack.process');

    Route::get('kit-preparation', [InventoryMoreController::class, 'kitPreparation'])->name('kit-preparation.index');
    Route::middleware(['permission:kit-preparation.create', 'branch.access'])
        ->post('kit-preparation', [InventoryMoreController::class, 'processKitPreparation'])->name('kit-preparation.process');

    Route::get('kit-unpack', [InventoryMoreController::class, 'kitUnpack'])->name('kit-unpack.index');
    Route::middleware(['permission:kit-unpack.create', 'branch.access'])
        ->post('kit-unpack', [InventoryMoreController::class, 'processKitUnpack'])->name('kit-unpack.process');

    Route::get('price-drop', [InventoryMoreController::class, 'priceDrop'])->name('price-drop.index');
    Route::get('shelf-talker', [InventoryMoreController::class, 'shelfTalker'])->name('shelf-talker.index');
    Route::get('change-serial-no', [InventoryMoreController::class, 'changeSerialNo'])->name('change-serial-no.index');
    Route::post('change-serial-no', [InventoryMoreController::class, 'processChangeSerialNo'])->name('change-serial-no.process');

    // Stock Transfer
    Route::get('stock-transfers/item-list', [StockTransferController::class, 'itemList'])->name('stock-transfers.item-list');
    Route::get('stock-transfers/search-items', [StockTransferController::class, 'searchItems'])->name('stock-transfers.search-items');
    Route::get('stock-transfers/item-by-code', [StockTransferController::class, 'getItemByCode'])->name('stock-transfers.item-by-code');
    Route::get('stock-transfers/pending-receipt', [StockTransferController::class, 'pendingReceipt'])->name('stock-transfers.pending-receipt');
    Route::get('stock-transfers/{stockTransfer}/receive', [StockTransferController::class, 'receiveForm'])->name('stock-transfers.receive-form');
    Route::middleware(['permission:stock-transfers.receive', 'branch.access'])
        ->post('stock-transfers/{stockTransfer}/receive', [StockTransferController::class, 'receive'])->name('stock-transfers.receive');
    Route::middleware(['permission:stock-transfers.cancel', 'branch.access'])
        ->post('stock-transfers/{stockTransfer}/cancel', [StockTransferController::class, 'cancel'])->name('stock-transfers.cancel');
    Route::get('stock-transfers/{stockTransfer}/print', [StockTransferController::class, 'print'])->name('stock-transfers.print');
    Route::resource('stock-transfers', StockTransferController::class)->only(['index', 'create', 'show']);
    Route::middleware(['permission:stock-transfers.create', 'branch.access'])
        ->group(fn () => Route::resource('stock-transfers', StockTransferController::class)->only(['store']));
});

Route::middleware('auth')->prefix('sales')->name('sales.')->group(function () use ($gatedResource) {
    Route::get('sales-bills/item-list', [SalesBillController::class, 'itemList'])->name('sales-bills.item-list');
    Route::get('sales-bills/lookup-item', [SalesBillController::class, 'lookupItem'])->name('sales-bills.lookup-item');
    Route::get('sales-bills/customer-search', [SalesBillController::class, 'customerSearch'])->name('sales-bills.customer-search');
    Route::get('sales-bills/customer-loyalty/{customer}', [SalesBillController::class, 'customerLoyalty'])->name('sales-bills.customer-loyalty');
    Route::get('sales-bills/customer-invoices/{customer}', [SalesBillController::class, 'customerInvoices'])->name('sales-bills.customer-invoices');
    Route::get('sales-bills/{salesBill}/receipt', [SalesBillController::class, 'receipt'])->name('sales-bills.receipt');
    Route::get('sales-bills/{salesBill}/eway-json', [\App\Http\Controllers\Sales\EWayBillController::class, 'downloadJson'])->name('sales-bills.eway-json');
    Route::post('sales-bills/{salesBill}/eway-update', [\App\Http\Controllers\Sales\EWayBillController::class, 'updateDetails'])->name('sales-bills.eway-update');
    $gatedResource('sales-quotations', SalesQuotationController::class, 'sales-quotations');
    $gatedResource('sales-orders', SalesOrderController::class, 'sales-orders');
    Route::get('delivery-notes/{deliveryNote}/print', [SalesDeliveryNoteController::class, 'print'])->name('delivery-notes.print');
    $gatedResource('delivery-notes', SalesDeliveryNoteController::class, 'sales-delivery-notes');
    $gatedResource('sales-bills', SalesBillController::class, 'sales-bills');
    Route::get('sales-returns/customer-bills/{customer}', [SalesReturnController::class, 'customerBills'])->name('sales-returns.customer-bills');
    Route::get('sales-returns/bill-items/{salesBill}', [SalesReturnController::class, 'billItems'])->name('sales-returns.bill-items');
    Route::get('sales-returns/{salesReturn}/print', [SalesReturnController::class, 'print'])->name('sales-returns.print');
    $gatedResource('sales-returns', SalesReturnController::class, 'sales-returns');
    Route::get('aux/{module}', [SalesAuxController::class, 'renderModule'])->name('aux');
});

Route::middleware('auth')->get('pos', [\App\Http\Controllers\Sales\SalesBillController::class, 'posTerminal'])->name('pos.terminal');

Route::middleware('auth')->get('pos-ping', function () {
    return response()->json([
        'status' => 'pong',
        'server_time' => microtime(true),
    ]);
})->name('pos.ping');

Route::middleware('auth')->prefix('tools')->name('tools.')->group(function () {
    Route::get('form-validations', [\App\Http\Controllers\Tools\FormFieldValidationController::class, 'index'])->name('form-validations.index');
    Route::post('form-validations', [\App\Http\Controllers\Tools\FormFieldValidationController::class, 'update'])->name('form-validations.update');
    Route::post('form-validations/reset', [\App\Http\Controllers\Tools\FormFieldValidationController::class, 'reset'])->name('form-validations.reset');
    Route::get('function-keys', [ToolsController::class, 'functionKeysIndex'])->name('function-keys.index');
    Route::post('function-keys', [ToolsController::class, 'functionKeysUpdate'])->name('function-keys.update');
    Route::post('function-keys/reset', [ToolsController::class, 'functionKeysReset'])->name('function-keys.reset');
    Route::get('table-preferences', [\App\Http\Controllers\UserTablePreferenceController::class, 'get'])->name('table-preferences.get');
    Route::post('table-preferences', [\App\Http\Controllers\UserTablePreferenceController::class, 'store'])->name('table-preferences.store');
    Route::post('table-preferences/reset', [\App\Http\Controllers\UserTablePreferenceController::class, 'reset'])->name('table-preferences.reset');
    Route::get('form-preferences', [\App\Http\Controllers\UserFormPreferenceController::class, 'get'])->name('form-preferences.get');
    Route::post('form-preferences', [\App\Http\Controllers\UserFormPreferenceController::class, 'store'])->name('form-preferences.store');
    Route::post('form-preferences/reset', [\App\Http\Controllers\UserFormPreferenceController::class, 'reset'])->name('form-preferences.reset');
    Route::get('system-health', [\App\Http\Controllers\Tools\SystemHealthController::class, 'index'])->name('system-health.index');
    Route::post('system-health/run', [\App\Http\Controllers\Tools\SystemHealthController::class, 'runDiagnostics'])->name('system-health.run');
    Route::post('system-health/backup', [\App\Http\Controllers\Tools\SystemHealthController::class, 'createBackup'])->name('system-health.backup.create');
    Route::get('system-health/backup/download/{filename}', [\App\Http\Controllers\Tools\SystemHealthController::class, 'downloadBackup'])->name('system-health.backup.download');
    Route::delete('system-health/backup/{filename}', [\App\Http\Controllers\Tools\SystemHealthController::class, 'deleteBackup'])->name('system-health.backup.delete');

    // System Error & Exception Hub (Module-wise & Date-wise)
    Route::get('system-error-logs', [\App\Http\Controllers\Tools\SystemErrorLogController::class, 'index'])->name('system-error-logs.index');
    Route::get('system-error-logs/export', [\App\Http\Controllers\Tools\SystemErrorLogController::class, 'export'])->name('system-error-logs.export');
    Route::get('system-error-logs/{id}', [\App\Http\Controllers\Tools\SystemErrorLogController::class, 'show'])->name('system-error-logs.show');
    Route::post('system-error-logs/{id}/resolve', [\App\Http\Controllers\Tools\SystemErrorLogController::class, 'resolve'])->name('system-error-logs.resolve');
    Route::post('system-error-logs/clear-old', [\App\Http\Controllers\Tools\SystemErrorLogController::class, 'clearOld'])->name('system-error-logs.clear-old');
    Route::post('client-error-logs', [\App\Http\Controllers\Tools\SystemErrorLogController::class, 'logClientError'])->name('client-error-logs');

    // POS Feature Health Check (Self-Test Dashboard)
    Route::get('pos-health-check', function () {
        return view('tools.pos-health-check.index');
    })->name('pos-health-check');
    Route::get('eway-update', [\App\Http\Controllers\Sales\EWayBillController::class, 'toolsIndex'])->name('eway-update');
    Route::post('eway-bulk-json', [\App\Http\Controllers\Sales\EWayBillController::class, 'downloadBulkJson'])->name('eway-bulk-json');
    
    // GST E-Filing & E-Invoice Integration Hub (from video 6.mp4)
    Route::get('integrations-gst', [\App\Http\Controllers\GST\EInvoiceDashboardController::class, 'index'])->name('integrations-gst');
    Route::get('einvoice/details/{salesBill}', [\App\Http\Controllers\GST\EInvoiceDashboardController::class, 'billDetails'])->name('einvoice.details');
    Route::post('einvoice/generate-irn', [\App\Http\Controllers\GST\EInvoiceDashboardController::class, 'generateIrn'])->name('einvoice.generate-irn');
    Route::post('einvoice/export-json', [\App\Http\Controllers\GST\EInvoiceDashboardController::class, 'exportJson'])->name('einvoice.export-json');
    Route::get('einvoice/download-errors', [\App\Http\Controllers\GST\EInvoiceDashboardController::class, 'downloadErrors'])->name('einvoice.download-errors');
    Route::post('einvoice/settings', [\App\Http\Controllers\GST\EInvoiceDashboardController::class, 'updateSettings'])->name('einvoice.settings');

    // GSTR Returns Specific Actions (GSTR-1, GSTR-3B, GSTR-9, GSTR-2A, GSTR-2B)
    Route::get('gst/gstr-1', [\App\Http\Controllers\GST\EInvoiceDashboardController::class, 'gstr1View'])->name('gst.gstr-1.page');
    Route::get('gst/gstr-1/{section}', [\App\Http\Controllers\GST\EInvoiceDashboardController::class, 'gstr1SectionView'])->name('gst.gstr-1.section');
    Route::get('gst/gstr-1-details', [\App\Http\Controllers\GST\EInvoiceDashboardController::class, 'gstr1Details'])->name('gst.gstr-1');
    Route::get('gst/gstr-3b-details', [\App\Http\Controllers\GST\EInvoiceDashboardController::class, 'gstr3bDetails'])->name('gst.gstr-3b');
    Route::post('gst/gstr-9-sync', [\App\Http\Controllers\GST\EInvoiceDashboardController::class, 'gstr9Sync'])->name('gst.gstr-9-sync');
    Route::post('gst/gstr-2-upload', [\App\Http\Controllers\GST\EInvoiceDashboardController::class, 'uploadGstr2'])->name('gst.gstr-2-upload');
    Route::get('gst/gstr-2-download', [\App\Http\Controllers\GST\EInvoiceDashboardController::class, 'downloadGstr2'])->name('gst.gstr-2-download');

    Route::get('{module}', [ToolsController::class, 'renderModule'])->name('module');
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
    Route::get('eod', [ReportController::class, 'eod'])->name('eod');
    Route::get('item-master', [ReportController::class, 'itemMaster'])->name('item-master');
    Route::get('supplier-master', [ReportController::class, 'supplierMaster'])->name('supplier-master');
    Route::get('gst-purchase-summary', [ReportController::class, 'gstPurchaseSummary'])->name('gst-purchase-summary');
    Route::get('purchase-order-summary', [ReportController::class, 'purchaseOrderSummary'])->name('purchase-order-summary');
    Route::get('stock-transfer-summary', [ReportController::class, 'stockTransferSummary'])->name('stock-transfer-summary');
    Route::get('damage-stock-summary', [ReportController::class, 'damageStockSummary'])->name('damage-stock-summary');
    Route::get('tender-summary', [ReportController::class, 'tenderSummary'])->name('tender-summary');
    Route::get('audit-logs', [ReportController::class, 'auditLogs'])->name('audit-logs');
    Route::get('customer-loyalty', [FinanceReportController::class, 'customerLoyalty'])->name('customer-loyalty');
    // Phase 5 — new reports
    Route::get('sales-margin-itemwise', [ReportController::class, 'salesMarginItemwise'])->name('sales-margin-itemwise');
    Route::get('sales-margin-category', [ReportController::class, 'salesMarginCategorywise'])->name('sales-margin-category');
    Route::get('quotation-order-summary', [ReportController::class, 'quotationOrderSummary'])->name('quotation-order-summary');
    Route::get('reorder-report', [ReportController::class, 'reorderReport'])->name('reorder-report');
    Route::get('view/{module}', [ReportController::class, 'renderGenericReport'])->name('view');

    // Smart Item & Customer 360° Analytics & Reporting Engine
    Route::get('smart-analytics', [\App\Http\Controllers\Reports\SmartAnalyticsController::class, 'index'])->name('smart-analytics');
    Route::get('smart-analytics/item', [\App\Http\Controllers\Reports\SmartAnalyticsController::class, 'itemAnalytics'])->name('smart-analytics.item');
    Route::get('smart-analytics/customer', [\App\Http\Controllers\Reports\SmartAnalyticsController::class, 'customerAnalytics'])->name('smart-analytics.customer');
    Route::get('smart-analytics/ranking', [\App\Http\Controllers\Reports\SmartAnalyticsController::class, 'rankingAnalytics'])->name('smart-analytics.ranking');
    Route::get('smart-analytics/search-items', [\App\Http\Controllers\Reports\SmartAnalyticsController::class, 'searchItems'])->name('smart-analytics.search-items');
    Route::get('smart-analytics/search-customers', [\App\Http\Controllers\Reports\SmartAnalyticsController::class, 'searchCustomers'])->name('smart-analytics.search-customers');
    Route::get('smart-analytics/export-item', [\App\Http\Controllers\Reports\SmartAnalyticsController::class, 'exportItemCsv'])->name('smart-analytics.export-item');

    // Universal Analytics & Custom Report Studio (Dynamic Report Builder)
    Route::get('analytics-builder', [\App\Http\Controllers\Reports\AnalyticsBuilderController::class, 'index'])->name('analytics-builder');
    Route::post('analytics-builder/generate', [\App\Http\Controllers\Reports\AnalyticsBuilderController::class, 'generate'])->name('analytics-builder.generate');
    Route::post('analytics-builder/save', [\App\Http\Controllers\Reports\AnalyticsBuilderController::class, 'saveReport'])->name('analytics-builder.save');
    Route::delete('analytics-builder/saved/{id}', [\App\Http\Controllers\Reports\AnalyticsBuilderController::class, 'deleteReport'])->name('analytics-builder.delete');
    Route::get('analytics-builder/export', [\App\Http\Controllers\Reports\AnalyticsBuilderController::class, 'exportCsv'])->name('analytics-builder.export');
    Route::get('analytics-builder/search-items', [\App\Http\Controllers\Reports\AnalyticsBuilderController::class, 'searchItems'])->name('analytics-builder.search-items');
    Route::get('analytics-builder/search-suppliers', [\App\Http\Controllers\Reports\AnalyticsBuilderController::class, 'searchSuppliers'])->name('analytics-builder.search-suppliers');
    Route::get('analytics-builder/search-customers', [\App\Http\Controllers\Reports\AnalyticsBuilderController::class, 'searchCustomers'])->name('analytics-builder.search-customers');
    Route::get('analytics-builder/drilldown', [\App\Http\Controllers\Reports\AnalyticsBuilderController::class, 'drilldown'])->name('analytics-builder.drilldown');
});

Route::middleware('auth')->prefix('till')->name('till.')->group(function () {
    Route::get('sessions', [\App\Http\Controllers\Till\TillSessionController::class, 'index'])->name('sessions.index');
    Route::get('sessions/open', [\App\Http\Controllers\Till\TillSessionController::class, 'create'])->name('sessions.create');
    Route::middleware('permission:till.open')
        ->post('sessions/open', [\App\Http\Controllers\Till\TillSessionController::class, 'open'])->name('sessions.open');
    Route::get('sessions/{tillSession}', [\App\Http\Controllers\Till\TillSessionController::class, 'show'])->name('sessions.show');
    Route::middleware('permission:till.open')
        ->post('sessions/{tillSession}/cash-movements', [\App\Http\Controllers\Till\TillSessionController::class, 'addCashMovement'])->name('sessions.cash-movements');
    Route::middleware('permission:till.close')
        ->post('sessions/{tillSession}/close', [\App\Http\Controllers\Till\TillSessionController::class, 'close'])->name('sessions.close');
});

Route::middleware('auth')->prefix('finance')->name('finance.')->group(function () use ($gatedResource) {
    $gatedResource('ledgers', LedgerController::class, 'ledgers');
    $gatedResource('vouchers', VoucherController::class, 'vouchers');
    Route::get('settlements/unpaid-bills', [BillSettlementController::class, 'unpaidBills'])->name('settlements.unpaid-bills');
    $gatedResource('settlements', BillSettlementController::class, 'bill-settlements');

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [FinanceReportController::class, 'index'])->name('index');
        Route::get('general-ledger', [FinanceReportController::class, 'generalLedger'])->name('general-ledger');
        Route::get('day-book', [FinanceReportController::class, 'dayBook'])->name('day-book');
        Route::get('trial-balance', [FinanceReportController::class, 'trialBalance'])->name('trial-balance');
        Route::get('profit-loss', [FinanceReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('cash-bank-book', [FinanceReportController::class, 'cashBankBook'])->name('cash-bank-book');
        Route::get('outstanding-aging', [FinanceReportController::class, 'outstandingAging'])->name('outstanding-aging');
        Route::get('customer-loyalty', [FinanceReportController::class, 'customerLoyalty'])->name('customer-loyalty');
    });
});

Route::middleware('auth')->post('/active-branch', function (\Illuminate\Http\Request $request) {
    $branchId = $request->input('branch_id');
    if ($branchId === 'all' || empty($branchId) || $branchId === '0') {
        session()->forget('active_branch_id');
    } else {
        session(['active_branch_id' => (int) $branchId]);
    }
    return response()->json([
        'status' => 'ok',
        'active_branch_id' => session('active_branch_id', null),
    ]);
})->name('set-active-branch');

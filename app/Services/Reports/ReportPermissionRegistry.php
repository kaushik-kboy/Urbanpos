<?php

namespace App\Services\Reports;

use App\Models\User;
use Illuminate\Support\Str;

class ReportPermissionRegistry
{
    /**
     * Complete list of all distinct reports and their permissions.
     */
    public static function getAllReports(): array
    {
        return [
            'dashboard' => [
                'label' => 'Reports Dashboard',
                'perm_key' => 'reports-dashboard',
                'parent_perm' => 'reports-dashboard',
                'category' => 'General',
                'url' => 'reports',
                'has_export' => true,
            ],
            'analytics-builder' => [
                'label' => 'Custom Report Studio (Builder)',
                'perm_key' => 'reports-analytics-builder',
                'parent_perm' => 'reports-dashboard',
                'category' => 'General',
                'url' => 'reports/analytics-builder',
                'has_export' => true,
            ],
            'smart-analytics' => [
                'label' => 'Smart Item & Customer 360°',
                'perm_key' => 'reports-smart-analytics',
                'parent_perm' => 'reports-dashboard',
                'category' => 'General',
                'url' => 'reports/smart-analytics',
                'has_export' => true,
            ],
            'customer-master' => [
                'label' => 'Customer Master',
                'perm_key' => 'reports-customer-master',
                'parent_perm' => 'reports-sales',
                'category' => 'Masters',
                'url' => 'reports/customer-master',
                'has_export' => true,
            ],
            'uom-vs-item-mapping' => [
                'label' => 'UOM Vs Item Mapping',
                'perm_key' => 'reports-uom-vs-item-mapping',
                'parent_perm' => 'reports-sales',
                'category' => 'Masters',
                'url' => 'reports/view/uom-vs-item-mapping',
                'has_export' => true,
            ],
            'kit-mapping' => [
                'label' => 'Kit Mapping',
                'perm_key' => 'reports-kit-mapping',
                'parent_perm' => 'reports-sales',
                'category' => 'Masters',
                'url' => 'reports/view/kit-mapping',
                'has_export' => true,
            ],
            'customer-parent-list' => [
                'label' => 'Customer Parent List',
                'perm_key' => 'reports-customer-parent-list',
                'parent_perm' => 'reports-sales',
                'category' => 'Masters',
                'url' => 'reports/view/customer-parent-list',
                'has_export' => true,
            ],
            'customer-loyalty' => [
                'label' => 'Customer Loyalty Details',
                'perm_key' => 'reports-customer-loyalty',
                'parent_perm' => 'reports-sales',
                'category' => 'Masters',
                'url' => 'reports/customer-loyalty',
                'has_export' => true,
            ],
            'customer-pet-details' => [
                'label' => 'Customer Pet Details',
                'perm_key' => 'reports-customer-pet-details',
                'parent_perm' => 'reports-sales',
                'category' => 'Masters',
                'url' => 'reports/customer-pet-details',
                'has_export' => true,
            ],
            'supplier-master' => [
                'label' => 'Supplier Master',
                'perm_key' => 'reports-supplier-master',
                'parent_perm' => 'reports-sales',
                'category' => 'Masters',
                'url' => 'reports/supplier-master',
                'has_export' => true,
            ],
            'brand-master' => [
                'label' => 'Brand Master',
                'perm_key' => 'reports-brand-master',
                'parent_perm' => 'reports-sales',
                'category' => 'Masters',
                'url' => 'reports/view/brand-master',
                'has_export' => true,
            ],
            'tax-master' => [
                'label' => 'Tax Master',
                'perm_key' => 'reports-tax-master',
                'parent_perm' => 'reports-sales',
                'category' => 'Masters',
                'url' => 'reports/view/tax-master',
                'has_export' => true,
            ],
            'area' => [
                'label' => 'Area',
                'perm_key' => 'reports-area',
                'parent_perm' => 'reports-sales',
                'category' => 'Masters',
                'url' => 'reports/view/area',
                'has_export' => true,
            ],
            'branch-master' => [
                'label' => 'Branch Master',
                'perm_key' => 'reports-branch-master',
                'parent_perm' => 'reports-sales',
                'category' => 'Masters',
                'url' => 'reports/view/branch-master',
                'has_export' => true,
            ],
            'item-master' => [
                'label' => 'Item Master',
                'perm_key' => 'reports-item-master',
                'parent_perm' => 'reports-sales',
                'category' => 'Masters',
                'url' => 'reports/item-master',
                'has_export' => true,
            ],
            'supplier-vs-items' => [
                'label' => 'Supplier Vs Items',
                'perm_key' => 'reports-supplier-vs-items',
                'parent_perm' => 'reports-sales',
                'category' => 'Masters',
                'url' => 'reports/view/supplier-vs-items',
                'has_export' => true,
            ],
            'price-list' => [
                'label' => 'Price List',
                'perm_key' => 'reports-price-list',
                'parent_perm' => 'reports-sales',
                'category' => 'Masters',
                'url' => 'reports/view/price-list',
                'has_export' => true,
            ],
            'employee-master' => [
                'label' => 'Employee Master',
                'perm_key' => 'reports-employee-master',
                'parent_perm' => 'reports-sales',
                'category' => 'Masters',
                'url' => 'reports/view/employee-master',
                'has_export' => true,
            ],
            'ro-master' => [
                'label' => 'RO Master',
                'perm_key' => 'reports-ro-master',
                'parent_perm' => 'reports-sales',
                'category' => 'Masters',
                'url' => 'reports/view/ro-master',
                'has_export' => true,
            ],
            'purchase-order-summary' => [
                'label' => 'Purchase Order Summary',
                'perm_key' => 'reports-purchase-order-summary',
                'parent_perm' => 'reports-purchase',
                'category' => 'Purchase',
                'url' => 'reports/purchase-order-summary',
                'has_export' => true,
            ],
            'purchase-transit' => [
                'label' => 'Purchase Transit',
                'perm_key' => 'reports-purchase-transit',
                'parent_perm' => 'reports-purchase',
                'category' => 'Purchase',
                'url' => 'reports/view/purchase-transit',
                'has_export' => true,
            ],
            'purchase-order-details' => [
                'label' => 'Purchase Order Details',
                'perm_key' => 'reports-purchase-order-details',
                'parent_perm' => 'reports-purchase',
                'category' => 'Purchase',
                'url' => 'reports/view/purchase-order-details',
                'has_export' => true,
            ],
            'receiptnote-nowise-summary' => [
                'label' => 'ReceiptNote Nowise Summary',
                'perm_key' => 'reports-receiptnote-nowise-summary',
                'parent_perm' => 'reports-purchase',
                'category' => 'Purchase',
                'url' => 'reports/view/receiptnote-nowise-summary',
                'has_export' => true,
            ],
            'receiptnote-nowise-detail' => [
                'label' => 'ReceiptNote Nowise Detail',
                'perm_key' => 'reports-receiptnote-nowise-detail',
                'parent_perm' => 'reports-purchase',
                'category' => 'Purchase',
                'url' => 'reports/view/receiptnote-nowise-detail',
                'has_export' => true,
            ],
            'purchase-summary' => [
                'label' => 'Purchase Summary',
                'perm_key' => 'reports-purchase-summary',
                'parent_perm' => 'reports-purchase',
                'category' => 'Purchase',
                'url' => 'reports/view/purchase-summary',
                'has_export' => true,
            ],
            'purchase-detail' => [
                'label' => 'GST Purchase Detail',
                'perm_key' => 'reports-purchase-detail',
                'parent_perm' => 'reports-purchase',
                'category' => 'Purchase',
                'url' => 'reports/purchase-detail',
                'has_export' => true,
            ],
            'gin-summary' => [
                'label' => 'GIN Summary',
                'perm_key' => 'reports-gin-summary',
                'parent_perm' => 'reports-purchase',
                'category' => 'Purchase',
                'url' => 'reports/view/gin-summary',
                'has_export' => true,
            ],
            'purchase-detail-serial' => [
                'label' => 'Purchase Detail Serial',
                'perm_key' => 'reports-purchase-detail-serial',
                'parent_perm' => 'reports-purchase',
                'category' => 'Purchase',
                'url' => 'reports/view/purchase-detail-serial',
                'has_export' => true,
            ],
            'gin-detail' => [
                'label' => 'GIN Detail',
                'perm_key' => 'reports-gin-detail',
                'parent_perm' => 'reports-purchase',
                'category' => 'Purchase',
                'url' => 'reports/view/gin-detail',
                'has_export' => true,
            ],
            'gst-purchase-summary' => [
                'label' => 'GST Purchase Summary',
                'perm_key' => 'reports-gst-purchase-summary',
                'parent_perm' => 'reports-purchase',
                'category' => 'Purchase',
                'url' => 'reports/gst-purchase-summary',
                'has_export' => true,
            ],
            'po-vs-invoice' => [
                'label' => 'Purchase Order vs Invoice Report',
                'perm_key' => 'reports-po-vs-invoice',
                'parent_perm' => 'reports-purchase',
                'category' => 'Purchase',
                'url' => 'reports/view/po-vs-invoice',
                'has_export' => true,
            ],
            'receiptnote-itemwise' => [
                'label' => 'ReceiptNote Itemwise',
                'perm_key' => 'reports-receiptnote-itemwise',
                'parent_perm' => 'reports-purchase',
                'category' => 'Purchase',
                'url' => 'reports/view/receiptnote-itemwise',
                'has_export' => true,
            ],
            'purchase-pending-cancelled' => [
                'label' => 'Pending/Cancelled Transactions',
                'perm_key' => 'reports-purchase-pending-cancelled',
                'parent_perm' => 'reports-purchase',
                'category' => 'Purchase',
                'url' => 'reports/view/purchase-pending-cancelled',
                'has_export' => true,
            ],
            'purchase-returned-transactions' => [
                'label' => 'Returned Transactions',
                'perm_key' => 'reports-purchase-returned-transactions',
                'parent_perm' => 'reports-purchase',
                'category' => 'Purchase',
                'url' => 'reports/view/purchase-returned-transactions',
                'has_export' => true,
            ],
            'supplierwise-purchase-summary' => [
                'label' => 'Supplierwise Purchase Summary',
                'perm_key' => 'reports-supplierwise-purchase-summary',
                'parent_perm' => 'reports-purchase',
                'category' => 'Purchase',
                'url' => 'reports/view/supplierwise-purchase-summary',
                'has_export' => true,
            ],
            'po-purchase-discrepancy' => [
                'label' => 'PO/Purchase Discrepancy',
                'perm_key' => 'reports-po-purchase-discrepancy',
                'parent_perm' => 'reports-purchase',
                'category' => 'Purchase',
                'url' => 'reports/view/po-purchase-discrepancy',
                'has_export' => true,
            ],
            'supplierwise-purchase-details' => [
                'label' => 'Supplierwise Purchase Details',
                'perm_key' => 'reports-supplierwise-purchase-details',
                'parent_perm' => 'reports-purchase',
                'category' => 'Purchase',
                'url' => 'reports/view/supplierwise-purchase-details',
                'has_export' => true,
            ],
            'datewise-itemwise-purchase' => [
                'label' => 'Datewise Itemwise Consolidated Purchase Report',
                'perm_key' => 'reports-datewise-itemwise-purchase',
                'parent_perm' => 'reports-purchase',
                'category' => 'Purchase',
                'url' => 'reports/view/datewise-itemwise-purchase',
                'has_export' => true,
            ],
            'purchase-register-summary' => [
                'label' => 'Purchase Register Summary',
                'perm_key' => 'reports-purchase-register-summary',
                'parent_perm' => 'reports-purchase',
                'category' => 'Purchase',
                'url' => 'reports/view/purchase-register-summary',
                'has_export' => true,
            ],
            'foot-fall-details' => [
                'label' => 'Foot Fall Details',
                'perm_key' => 'reports-foot-fall-details',
                'parent_perm' => 'reports-audit',
                'category' => 'Audit',
                'url' => 'reports/view/foot-fall-details',
                'has_export' => false,
            ],
            'reprint-count-details' => [
                'label' => 'Reprint Count Details',
                'perm_key' => 'reports-reprint-count-details',
                'parent_perm' => 'reports-audit',
                'category' => 'Audit',
                'url' => 'reports/view/reprint-count-details',
                'has_export' => false,
            ],
            'user-login-summary' => [
                'label' => 'User Login Summary',
                'perm_key' => 'reports-user-login-summary',
                'parent_perm' => 'reports-audit',
                'category' => 'Audit',
                'url' => 'reports/view/user-login-summary',
                'has_export' => false,
            ],
            'audit-logs' => [
                'label' => 'Audit Viewer Report',
                'perm_key' => 'reports-audit-logs',
                'parent_perm' => 'reports-audit',
                'category' => 'Audit',
                'url' => 'reports/audit-logs',
                'has_export' => false,
            ],
            'audit-detail-report' => [
                'label' => 'Audit Detail Report',
                'perm_key' => 'reports-audit-detail-report',
                'parent_perm' => 'reports-audit',
                'category' => 'Audit',
                'url' => 'reports/view/audit-detail-report',
                'has_export' => false,
            ],
            'gst-tax-change-audit' => [
                'label' => 'GST Tax Change Audit Report',
                'perm_key' => 'reports-gst-tax-change-audit',
                'parent_perm' => 'reports-audit',
                'category' => 'Audit',
                'url' => 'reports/view/gst-tax-change-audit',
                'has_export' => false,
            ],
            'service-user-consent' => [
                'label' => 'Service User Consent Summary',
                'perm_key' => 'reports-service-user-consent',
                'parent_perm' => 'reports-audit',
                'category' => 'Audit',
                'url' => 'reports/view/service-user-consent',
                'has_export' => false,
            ],
            'email-audit-viewer' => [
                'label' => 'Email Audit Viewer Report',
                'perm_key' => 'reports-email-audit-viewer',
                'parent_perm' => 'reports-audit',
                'category' => 'Audit',
                'url' => 'reports/view/email-audit-viewer',
                'has_export' => false,
            ],
            'audit-cart-clear' => [
                'label' => 'Audit Report (Cart entry Clear)',
                'perm_key' => 'reports-audit-cart-clear',
                'parent_perm' => 'reports-audit',
                'category' => 'Audit',
                'url' => 'reports/view/audit-cart-clear',
                'has_export' => false,
            ],
            'audit-detail-cart-clear' => [
                'label' => 'Audit Detail (Cart entry Clear)',
                'perm_key' => 'reports-audit-detail-cart-clear',
                'parent_perm' => 'reports-audit',
                'category' => 'Audit',
                'url' => 'reports/view/audit-detail-cart-clear',
                'has_export' => false,
            ],
            'monthly-sales-summary-storewise' => [
                'label' => 'Monthly Sales Summary [Storewise]',
                'perm_key' => 'reports-monthly-sales-summary-storewise',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/monthly-sales-summary-storewise',
                'has_export' => true,
            ],
            'monthly-sales-summary-tillwise' => [
                'label' => 'Monthly Sales Summary [Till Wise]',
                'perm_key' => 'reports-monthly-sales-summary-tillwise',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/monthly-sales-summary-tillwise',
                'has_export' => true,
            ],
            'serial-wise-price-details' => [
                'label' => 'Serial Number Wise Price Details',
                'perm_key' => 'reports-serial-wise-price-details',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/serial-wise-price-details',
                'has_export' => true,
            ],
            'sales-summary' => [
                'label' => 'Daily Sales Summary [Store Wise]',
                'perm_key' => 'reports-sales-summary',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/sales-summary',
                'has_export' => true,
            ],
            'daily-sales-summary-tillwise' => [
                'label' => 'Daily Sales Summary [Till Wise]',
                'perm_key' => 'reports-daily-sales-summary-tillwise',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/daily-sales-summary-tillwise',
                'has_export' => true,
            ],
            'daily-sales-billwise' => [
                'label' => 'Daily Sales [Bill No Wise]',
                'perm_key' => 'reports-daily-sales-billwise',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/daily-sales-billwise',
                'has_export' => true,
            ],
            'billwise-sales' => [
                'label' => 'Billwise Itemwise Sales Detail',
                'perm_key' => 'reports-billwise-sales',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/billwise-sales',
                'has_export' => true,
            ],
            'daily-sales-timefilter' => [
                'label' => 'Daily Sales [Bill No Wise With Timefilter]',
                'perm_key' => 'reports-daily-sales-timefilter',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/daily-sales-timefilter',
                'has_export' => true,
            ],
            'offline-sales-bill-details' => [
                'label' => 'Offline Sales Bill Details',
                'perm_key' => 'reports-offline-sales-bill-details',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/offline-sales-bill-details',
                'has_export' => true,
            ],
            'billwise-sales-serialwise' => [
                'label' => 'Billwise Itemwise Sales Detail Serialwise',
                'perm_key' => 'reports-billwise-sales-serialwise',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/billwise-sales-serialwise',
                'has_export' => true,
            ],
            'gst-sales-summary' => [
                'label' => 'GST Sales Summary',
                'perm_key' => 'reports-gst-sales-summary',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/gst-sales-summary',
                'has_export' => true,
            ],
            'gst-sales-taxwise' => [
                'label' => 'GST Sales Taxwise',
                'perm_key' => 'reports-gst-sales-taxwise',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/gst-sales-taxwise',
                'has_export' => true,
            ],
            'billwise-sales-assembly' => [
                'label' => 'Billwise Itemwise Sales With Assembly Details',
                'perm_key' => 'reports-billwise-sales-assembly',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/billwise-sales-assembly',
                'has_export' => true,
            ],
            'tender-summary' => [
                'label' => 'Tender Type Detail',
                'perm_key' => 'reports-tender-summary',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/tender-summary',
                'has_export' => true,
            ],
            'sales-register-summary' => [
                'label' => 'Sales Register Summary',
                'perm_key' => 'reports-sales-register-summary',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/sales-register-summary',
                'has_export' => true,
            ],
            'offer-claim-report' => [
                'label' => 'Offer Claim Report',
                'perm_key' => 'reports-offer-claim-report',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/offer-claim-report',
                'has_export' => true,
            ],
            'counterwise-sales-report' => [
                'label' => 'Counterwise Sales Report',
                'perm_key' => 'reports-counterwise-sales-report',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/counterwise-sales-report',
                'has_export' => true,
            ],
            'sales-margin-itemwise' => [
                'label' => 'Sales Item Margin',
                'perm_key' => 'reports-sales-margin-itemwise',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/sales-margin-itemwise',
                'has_export' => true,
            ],
            'customerwise-itemwise-sales' => [
                'label' => 'Customerwise Itemwise Sales',
                'perm_key' => 'reports-customerwise-itemwise-sales',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/customerwise-itemwise-sales',
                'has_export' => true,
            ],
            'itemwise-customerwise-sales' => [
                'label' => 'Itemwise Customerwise Sales',
                'perm_key' => 'reports-itemwise-customerwise-sales',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/itemwise-customerwise-sales',
                'has_export' => true,
            ],
            'sales-margin-category' => [
                'label' => 'Categorywise Sales',
                'perm_key' => 'reports-sales-margin-category',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/sales-margin-category',
                'has_export' => true,
            ],
            'categorywise-sales-detail' => [
                'label' => 'Categorywise Sales Detail',
                'perm_key' => 'reports-categorywise-sales-detail',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/categorywise-sales-detail',
                'has_export' => true,
            ],
            'quotation-order-summary' => [
                'label' => 'Quotation Summary',
                'perm_key' => 'reports-quotation-order-summary',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/quotation-order-summary',
                'has_export' => true,
            ],
            'quotation-details' => [
                'label' => 'Quotation Details',
                'perm_key' => 'reports-quotation-details',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/quotation-details',
                'has_export' => true,
            ],
            'sales-order-summary' => [
                'label' => 'Sales Order Summary',
                'perm_key' => 'reports-sales-order-summary',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/sales-order-summary',
                'has_export' => true,
            ],
            'sales-order-detail' => [
                'label' => 'Sales Order Detail',
                'perm_key' => 'reports-sales-order-detail',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/sales-order-detail',
                'has_export' => true,
            ],
            'sales-order-stock-status' => [
                'label' => 'Sales Order Stock Status',
                'perm_key' => 'reports-sales-order-stock-status',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/sales-order-stock-status',
                'has_export' => true,
            ],
            'sales-deliverynote-summary' => [
                'label' => 'Sales DeliveryNote Summary',
                'perm_key' => 'reports-sales-deliverynote-summary',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/sales-deliverynote-summary',
                'has_export' => true,
            ],
            'sales-deliverynote-detail' => [
                'label' => 'Sales DeliveryNote Detail',
                'perm_key' => 'reports-sales-deliverynote-detail',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/sales-deliverynote-detail',
                'has_export' => true,
            ],
            'kitchen-preparation-report' => [
                'label' => 'Kitchen Preparation Report',
                'perm_key' => 'reports-kitchen-preparation-report',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/kitchen-preparation-report',
                'has_export' => true,
            ],
            'delivery-bill-summary' => [
                'label' => 'Delivery Bill Summary Report',
                'perm_key' => 'reports-delivery-bill-summary',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/delivery-bill-summary',
                'has_export' => true,
            ],
            'delivery-bill-detail' => [
                'label' => 'Delivery Bill Detail Report',
                'perm_key' => 'reports-delivery-bill-detail',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/delivery-bill-detail',
                'has_export' => true,
            ],
            'kitchen-preparation-timewise' => [
                'label' => 'Kitchen Preparation Report [Timewise]',
                'perm_key' => 'reports-kitchen-preparation-timewise',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/kitchen-preparation-timewise',
                'has_export' => true,
            ],
            'sales-return-advice-detail' => [
                'label' => 'Sales Return Advice Detail',
                'perm_key' => 'reports-sales-return-advice-detail',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/sales-return-advice-detail',
                'has_export' => true,
            ],
            'buy-back-details' => [
                'label' => 'Buy Back Details',
                'perm_key' => 'reports-buy-back-details',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/buy-back-details',
                'has_export' => true,
            ],
            'sale-return-customerwise' => [
                'label' => 'Sale Return Customerwise Detail',
                'perm_key' => 'reports-sale-return-customerwise',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/sale-return-customerwise',
                'has_export' => true,
            ],
            'sales-return-itemwise' => [
                'label' => 'Sales Return Itemwise',
                'perm_key' => 'reports-sales-return-itemwise',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/sales-return-itemwise',
                'has_export' => true,
            ],
            'sale-return-datewise' => [
                'label' => 'Sale Return Datewise',
                'perm_key' => 'reports-sale-return-datewise',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/sale-return-datewise',
                'has_export' => true,
            ],
            'sale-return-monthwise' => [
                'label' => 'Sale Return Monthwise',
                'perm_key' => 'reports-sale-return-monthwise',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/sale-return-monthwise',
                'has_export' => true,
            ],
            'sales-return-summary' => [
                'label' => 'Sales Return Summary',
                'perm_key' => 'reports-sales-return-summary',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/sales-return-summary',
                'has_export' => true,
            ],
            'sales-cancelled-transactions' => [
                'label' => 'Cancelled Transactions',
                'perm_key' => 'reports-sales-cancelled-transactions',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/sales-cancelled-transactions',
                'has_export' => true,
            ],
            'margin-summary' => [
                'label' => 'Margin Summary',
                'perm_key' => 'reports-margin-summary',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/margin-summary',
                'has_export' => true,
            ],
            'eod' => [
                'label' => 'Session Report',
                'perm_key' => 'reports-eod',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/eod',
                'has_export' => true,
            ],
            'supplier-sales-report' => [
                'label' => 'Supplier Sales Report',
                'perm_key' => 'reports-supplier-sales-report',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/supplier-sales-report',
                'has_export' => true,
            ],
            'supplierwise-sales-details' => [
                'label' => 'Supplierwise Sales Details',
                'perm_key' => 'reports-supplierwise-sales-details',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/supplierwise-sales-details',
                'has_export' => true,
            ],
            'date-timewise-sales' => [
                'label' => 'Date Timewise Sales',
                'perm_key' => 'reports-date-timewise-sales',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/date-timewise-sales',
                'has_export' => true,
            ],
            'consumption-sales-summary' => [
                'label' => 'Consumption Sales Summary',
                'perm_key' => 'reports-consumption-sales-summary',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/consumption-sales-summary',
                'has_export' => true,
            ],
            'consumption-sales-detail' => [
                'label' => 'Consumption Sales Detail',
                'perm_key' => 'reports-consumption-sales-detail',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/consumption-sales-detail',
                'has_export' => true,
            ],
            'areawise-sales-summary' => [
                'label' => 'Areawise Sales Summary',
                'perm_key' => 'reports-areawise-sales-summary',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/areawise-sales-summary',
                'has_export' => true,
            ],
            'monthly-sales-detail' => [
                'label' => 'Monthly Sales Detail',
                'perm_key' => 'reports-monthly-sales-detail',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/monthly-sales-detail',
                'has_export' => true,
            ],
            'non-purchase-customer-list' => [
                'label' => 'Non Purchase Customer List',
                'perm_key' => 'reports-non-purchase-customer-list',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/non-purchase-customer-list',
                'has_export' => true,
            ],
            'counterwise-datewise-sales' => [
                'label' => 'Counterwise Datewise Sales Summary',
                'perm_key' => 'reports-counterwise-datewise-sales',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/counterwise-datewise-sales',
                'has_export' => true,
            ],
            'itemwise-monthly-sales-details' => [
                'label' => 'Itemwise Monthly Sales Qty/Amt Details',
                'perm_key' => 'reports-itemwise-monthly-sales-details',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/itemwise-monthly-sales-details',
                'has_export' => true,
            ],
            'hold-bill-details' => [
                'label' => 'Hold Bill Details',
                'perm_key' => 'reports-hold-bill-details',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/hold-bill-details',
                'has_export' => true,
            ],
            'itemwise-discount-approval' => [
                'label' => 'ItemWise Discount Approval Details',
                'perm_key' => 'reports-itemwise-discount-approval',
                'parent_perm' => 'reports-sales',
                'category' => 'Sales',
                'url' => 'reports/view/itemwise-discount-approval',
                'has_export' => true,
            ],
            'itemwise-stock-statement' => [
                'label' => 'Itemwise Stock Statement',
                'perm_key' => 'reports-itemwise-stock-statement',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/itemwise-stock-statement',
                'has_export' => true,
            ],
            'itemwise-stock-sales-detail' => [
                'label' => 'Itemwise Stock and Sales Detail',
                'perm_key' => 'reports-itemwise-stock-sales-detail',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/itemwise-stock-sales-detail',
                'has_export' => true,
            ],
            'itemwise-stock-sales-transit' => [
                'label' => 'Itemwise Stock and Sales Detail Transit with Transit',
                'perm_key' => 'reports-itemwise-stock-sales-transit',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/itemwise-stock-sales-transit',
                'has_export' => true,
            ],
            'transactionwise-stock-register' => [
                'label' => 'Transactionwise Stock Register',
                'perm_key' => 'reports-transactionwise-stock-register',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/transactionwise-stock-register',
                'has_export' => true,
            ],
            'closing-stock' => [
                'label' => 'Closing Stock',
                'perm_key' => 'reports-closing-stock',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/closing-stock',
                'has_export' => true,
            ],
            'current-stock' => [
                'label' => 'Current Stock Branchwise',
                'perm_key' => 'reports-current-stock',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/current-stock',
                'has_export' => true,
            ],
            'categorywise-datewise-stock' => [
                'label' => 'Categorywise Datewise Stock Report',
                'perm_key' => 'reports-categorywise-datewise-stock',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/categorywise-datewise-stock',
                'has_export' => true,
            ],
            'branchwise-stock-age-analysis' => [
                'label' => 'Branchwise Stock Age Analysis',
                'perm_key' => 'reports-branchwise-stock-age-analysis',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/branchwise-stock-age-analysis',
                'has_export' => true,
            ],
            'categorywise-stock-age-analysis' => [
                'label' => 'Categorywise Stock Age Analysis',
                'perm_key' => 'reports-categorywise-stock-age-analysis',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/categorywise-stock-age-analysis',
                'has_export' => true,
            ],
            'inventory-price-drop' => [
                'label' => 'Price Drop',
                'perm_key' => 'reports-inventory-price-drop',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/inventory-price-drop',
                'has_export' => true,
            ],
            'inventory-price-level-advance' => [
                'label' => 'Price Level Advance',
                'perm_key' => 'reports-inventory-price-level-advance',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/inventory-price-level-advance',
                'has_export' => true,
            ],
            'transfer-out-approval-detail' => [
                'label' => 'Transfer Out Approval Detail',
                'perm_key' => 'reports-transfer-out-approval-detail',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/transfer-out-approval-detail',
                'has_export' => true,
            ],
            'stock-transfer-summary' => [
                'label' => 'Stock TransferOut Summary',
                'perm_key' => 'reports-stock-transfer-summary',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/stock-transfer-summary',
                'has_export' => true,
            ],
            'stock-transferout-detail' => [
                'label' => 'Stock TransferOut Detail',
                'perm_key' => 'reports-stock-transferout-detail',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/stock-transferout-detail',
                'has_export' => true,
            ],
            'itemwise-storewise-transferout' => [
                'label' => 'Itemwise Storewise TransferOut Detail',
                'perm_key' => 'reports-itemwise-storewise-transferout',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/itemwise-storewise-transferout',
                'has_export' => true,
            ],
            'stock-transferin-summary' => [
                'label' => 'Stock TransferIn Summary',
                'perm_key' => 'reports-stock-transferin-summary',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/stock-transferin-summary',
                'has_export' => true,
            ],
            'stock-transferin-detail' => [
                'label' => 'Stock TransferIn Detail',
                'perm_key' => 'reports-stock-transferin-detail',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/stock-transferin-detail',
                'has_export' => true,
            ],
            'itemwise-storewise-transferin' => [
                'label' => 'Itemwise Storewise Transfer In Detail',
                'perm_key' => 'reports-itemwise-storewise-transferin',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/itemwise-storewise-transferin',
                'has_export' => true,
            ],
            'stock-in-transit' => [
                'label' => 'Stock In Transit',
                'perm_key' => 'reports-stock-in-transit',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/stock-in-transit',
                'has_export' => true,
            ],
            'stock-transfer-discrepancy' => [
                'label' => 'Stock Transfer Discrepancy',
                'perm_key' => 'reports-stock-transfer-discrepancy',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/stock-transfer-discrepancy',
                'has_export' => true,
            ],
            'to-vs-tin' => [
                'label' => 'TO Vs TIN',
                'perm_key' => 'reports-to-vs-tin',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/to-vs-tin',
                'has_export' => true,
            ],
            'stock-conversion-report' => [
                'label' => 'Stock Conversion Report',
                'perm_key' => 'reports-stock-conversion-report',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/stock-conversion-report',
                'has_export' => true,
            ],
            'stock-update-detail' => [
                'label' => 'Stock Update Detail',
                'perm_key' => 'reports-stock-update-detail',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/stock-update-detail',
                'has_export' => true,
            ],
            'damage-stock-summary' => [
                'label' => 'Wastage/Damage Stock',
                'perm_key' => 'reports-damage-stock-summary',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/damage-stock-summary',
                'has_export' => true,
            ],
            'categorywise-storewise-stock-summary' => [
                'label' => 'Categorywise Storewise Current Stock Summary',
                'perm_key' => 'reports-categorywise-storewise-stock-summary',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/categorywise-storewise-stock-summary',
                'has_export' => true,
            ],
            'categorywise-storewise-stock-detail' => [
                'label' => 'Categorywise Storewise Current Stock Detail',
                'perm_key' => 'reports-categorywise-storewise-stock-detail',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/categorywise-storewise-stock-detail',
                'has_export' => true,
            ],
            'stock-fastmoving-item' => [
                'label' => 'Stock - Categorwise FastMoving - Item',
                'perm_key' => 'reports-stock-fastmoving-item',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/stock-fastmoving-item',
                'has_export' => true,
            ],
            'stock-slowmoving-item' => [
                'label' => 'Stock - Categorwise - SlowMoving - Item',
                'perm_key' => 'reports-stock-slowmoving-item',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/stock-slowmoving-item',
                'has_export' => true,
            ],
            'wastage-damage-stock-detail' => [
                'label' => 'Wastage/Damage Stock Detail',
                'perm_key' => 'reports-wastage-damage-stock-detail',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/wastage-damage-stock-detail',
                'has_export' => true,
            ],
            'item-age-analysis' => [
                'label' => 'Item Age Analysis',
                'perm_key' => 'reports-item-age-analysis',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/item-age-analysis',
                'has_export' => true,
            ],
            'item-expiry-update-details' => [
                'label' => 'Item Expiry Update Details',
                'perm_key' => 'reports-item-expiry-update-details',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/item-expiry-update-details',
                'has_export' => true,
            ],
            'items-details-in-cart' => [
                'label' => 'Items Details In Cart',
                'perm_key' => 'reports-items-details-in-cart',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/items-details-in-cart',
                'has_export' => true,
            ],
            'stock-reserve-status' => [
                'label' => 'Stock Reserve Status',
                'perm_key' => 'reports-stock-reserve-status',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/stock-reserve-status',
                'has_export' => true,
            ],
            'mbq-detail' => [
                'label' => 'MBQ Detail',
                'perm_key' => 'reports-mbq-detail',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/mbq-detail',
                'has_export' => true,
            ],
            'kit-preparation' => [
                'label' => 'Kit Preparation',
                'perm_key' => 'reports-kit-preparation',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/kit-preparation',
                'has_export' => true,
            ],
            'kit-unpack' => [
                'label' => 'Kit Unpack',
                'perm_key' => 'reports-kit-unpack',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/kit-unpack',
                'has_export' => true,
            ],
            'itemwise-stock-transfer-advice' => [
                'label' => 'Itemwise Stock Transfer Advice',
                'perm_key' => 'reports-itemwise-stock-transfer-advice',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/itemwise-stock-transfer-advice',
                'has_export' => true,
            ],
            'indent-based-replenishment' => [
                'label' => 'Indent Based On Replenishment',
                'perm_key' => 'reports-indent-based-replenishment',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/indent-based-replenishment',
                'has_export' => true,
            ],
            'indent-summary' => [
                'label' => 'Indent Summary',
                'perm_key' => 'reports-indent-summary',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/indent-summary',
                'has_export' => true,
            ],
            'po-replenishment' => [
                'label' => 'PO Replenishment',
                'perm_key' => 'reports-po-replenishment',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/po-replenishment',
                'has_export' => true,
            ],
            'picklist-detail' => [
                'label' => 'Picklist Detail',
                'perm_key' => 'reports-picklist-detail',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/picklist-detail',
                'has_export' => true,
            ],
            'picking-list-discrepancy' => [
                'label' => 'Picking List Discrepancy',
                'perm_key' => 'reports-picking-list-discrepancy',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/picking-list-discrepancy',
                'has_export' => true,
            ],
            'opening-stock-detail' => [
                'label' => 'Opening Stock Detail',
                'perm_key' => 'reports-opening-stock-detail',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/opening-stock-detail',
                'has_export' => true,
            ],
            'repack-summary' => [
                'label' => 'Repack Summary',
                'perm_key' => 'reports-repack-summary',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/repack-summary',
                'has_export' => true,
            ],
            'eancode-detail' => [
                'label' => 'Eancode Detail',
                'perm_key' => 'reports-eancode-detail',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/eancode-detail',
                'has_export' => true,
            ],
            'repack-detail' => [
                'label' => 'Repack Detail',
                'perm_key' => 'reports-repack-detail',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/repack-detail',
                'has_export' => true,
            ],
            'reorder-report' => [
                'label' => 'Re-order Report',
                'perm_key' => 'reports-reorder-report',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/reorder-report',
                'has_export' => true,
            ],
            'issue-date-expiry-details' => [
                'label' => 'Issue Date Expiry Details',
                'perm_key' => 'reports-issue-date-expiry-details',
                'parent_perm' => 'reports-inventory',
                'category' => 'Inventory',
                'url' => 'reports/view/issue-date-expiry-details',
                'has_export' => true,
            ],
            'my-counterwise-sales-summary' => [
                'label' => 'Counterwise Sales Summary',
                'perm_key' => 'reports-my-counterwise-sales-summary',
                'parent_perm' => 'reports-sales',
                'category' => 'My Reports',
                'url' => 'reports/view/my-counterwise-sales-summary',
                'has_export' => true,
            ],
            'my-sales-mis-report' => [
                'label' => 'Sales MIS report',
                'perm_key' => 'reports-my-sales-mis-report',
                'parent_perm' => 'reports-sales',
                'category' => 'My Reports',
                'url' => 'reports/view/my-sales-mis-report',
                'has_export' => true,
            ],
            'my-offtake-dealer-tracker' => [
                'label' => 'Off Take - Dealer Tracker Report',
                'perm_key' => 'reports-my-offtake-dealer-tracker',
                'parent_perm' => 'reports-sales',
                'category' => 'My Reports',
                'url' => 'reports/view/my-offtake-dealer-tracker',
                'has_export' => true,
            ],
            'production-plan' => [
                'label' => 'Production Plan',
                'perm_key' => 'reports-production-plan',
                'parent_perm' => 'reports-inventory',
                'category' => 'Production',
                'url' => 'reports/view/production-plan',
                'has_export' => true,
            ],
            'bom-report' => [
                'label' => 'BOM Report',
                'perm_key' => 'reports-bom-report',
                'parent_perm' => 'reports-inventory',
                'category' => 'Production',
                'url' => 'reports/view/bom-report',
                'has_export' => true,
            ],
            'production-costing-summary' => [
                'label' => 'Production Costing Summary',
                'perm_key' => 'reports-production-costing-summary',
                'parent_perm' => 'reports-inventory',
                'category' => 'Production',
                'url' => 'reports/view/production-costing-summary',
                'has_export' => true,
            ],
            'production-costing-detail' => [
                'label' => 'Production Costing Detail',
                'perm_key' => 'reports-production-costing-detail',
                'parent_perm' => 'reports-inventory',
                'category' => 'Production',
                'url' => 'reports/view/production-costing-detail',
                'has_export' => true,
            ],
            'sales-production-variance' => [
                'label' => 'Sales/Production Variance',
                'perm_key' => 'reports-sales-production-variance',
                'parent_perm' => 'reports-inventory',
                'category' => 'Production',
                'url' => 'reports/view/sales-production-variance',
                'has_export' => true,
            ],
            'serial-number-history' => [
                'label' => 'Serial Number History',
                'perm_key' => 'reports-serial-number-history',
                'parent_perm' => 'reports-dashboard',
                'category' => 'More',
                'url' => 'reports/view/serial-number-history',
                'has_export' => true,
            ],
            'serial-nowise-stock-detail' => [
                'label' => 'Serial No Wise Stock Detail',
                'perm_key' => 'reports-serial-nowise-stock-detail',
                'parent_perm' => 'reports-dashboard',
                'category' => 'More',
                'url' => 'reports/view/serial-nowise-stock-detail',
                'has_export' => true,
            ],
            'offline-exported-reports' => [
                'label' => 'Offline Exported Reports',
                'perm_key' => 'reports-offline-exported-reports',
                'parent_perm' => 'reports-dashboard',
                'category' => 'More',
                'url' => 'reports/view/offline-exported-reports',
                'has_export' => true,
            ],
            'dashboard' => [
                'label' => 'Dashboard',
                'perm_key' => 'reports-dashboard',
                'parent_perm' => 'reports-dashboard',
                'category' => 'More',
                'url' => 'reports',
                'has_export' => true,
            ],
            'monthly-transaction-summary' => [
                'label' => 'Monthly Transaction Summary',
                'perm_key' => 'reports-monthly-transaction-summary',
                'parent_perm' => 'reports-dashboard',
                'category' => 'More',
                'url' => 'reports/view/monthly-transaction-summary',
                'has_export' => true,
            ],
            'general-ledger' => [
                'label' => 'General Ledger',
                'perm_key' => 'reports-general-ledger',
                'parent_perm' => 'reports-finance',
                'category' => 'Finance',
                'url' => 'reports/general-ledger',
                'has_export' => true,
            ],
            'day-book' => [
                'label' => 'Day Book',
                'perm_key' => 'reports-day-book',
                'parent_perm' => 'reports-finance',
                'category' => 'Finance',
                'url' => 'reports/day-book',
                'has_export' => true,
            ],
            'trial-balance' => [
                'label' => 'Trial Balance',
                'perm_key' => 'reports-trial-balance',
                'parent_perm' => 'reports-finance',
                'category' => 'Finance',
                'url' => 'reports/trial-balance',
                'has_export' => true,
            ],
            'profit-loss' => [
                'label' => 'Profit & Loss Statement',
                'perm_key' => 'reports-profit-loss',
                'parent_perm' => 'reports-finance',
                'category' => 'Finance',
                'url' => 'reports/profit-loss',
                'has_export' => true,
            ],
            'cash-bank-book' => [
                'label' => 'Cash & Bank Book',
                'perm_key' => 'reports-cash-bank-book',
                'parent_perm' => 'reports-finance',
                'category' => 'Finance',
                'url' => 'reports/cash-bank-book',
                'has_export' => true,
            ],
            'outstanding-aging' => [
                'label' => 'Outstanding Aging Analysis',
                'perm_key' => 'reports-outstanding-aging',
                'parent_perm' => 'reports-finance',
                'category' => 'Finance',
                'url' => 'reports/outstanding-aging',
                'has_export' => true,
            ],
            'customer-loyalty' => [
                'label' => 'Customer Loyalty Points Report',
                'perm_key' => 'reports-customer-loyalty',
                'parent_perm' => 'reports-finance',
                'category' => 'Finance',
                'url' => 'reports/customer-loyalty',
                'has_export' => true,
            ],
        ];
    }

    /**
     * Build grouped permissions for Role & Permission Master management.
     */
    public static function getPermissionGroups(): array
    {
        $all = self::getAllReports();
        $groups = [];

        $groups['reports-sales-group'] = [
            'label' => 'Reports: Sales, Invoices & Orders',
            'icon' => 'fas fa-shopping-cart text-primary',
            'parent_perm' => 'reports-sales',
            'modules' => [
                'reports-sales' => [
                    'label' => '⭐ ALL Sales Reports (Full Category Access)',
                    'actions' => ['view' => 'Full View Access', 'export' => 'Full Export Access']
                ],
                'reports-monthly-sales-summary-storewise' => [
                    'label' => 'Monthly Sales Summary [Storewise]',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-monthly-sales-summary-tillwise' => [
                    'label' => 'Monthly Sales Summary [Till Wise]',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-serial-wise-price-details' => [
                    'label' => 'Serial Number Wise Price Details',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-sales-summary' => [
                    'label' => 'Daily Sales Summary [Store Wise]',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-daily-sales-summary-tillwise' => [
                    'label' => 'Daily Sales Summary [Till Wise]',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-daily-sales-billwise' => [
                    'label' => 'Daily Sales [Bill No Wise]',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-billwise-sales' => [
                    'label' => 'Billwise Itemwise Sales Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-daily-sales-timefilter' => [
                    'label' => 'Daily Sales [Bill No Wise With Timefilter]',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-offline-sales-bill-details' => [
                    'label' => 'Offline Sales Bill Details',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-billwise-sales-serialwise' => [
                    'label' => 'Billwise Itemwise Sales Detail Serialwise',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-gst-sales-summary' => [
                    'label' => 'GST Sales Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-gst-sales-taxwise' => [
                    'label' => 'GST Sales Taxwise',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-billwise-sales-assembly' => [
                    'label' => 'Billwise Itemwise Sales With Assembly Details',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-tender-summary' => [
                    'label' => 'Tender Type Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-sales-register-summary' => [
                    'label' => 'Sales Register Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-offer-claim-report' => [
                    'label' => 'Offer Claim Report',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-counterwise-sales-report' => [
                    'label' => 'Counterwise Sales Report',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-sales-margin-itemwise' => [
                    'label' => 'Sales Item Margin',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-customerwise-itemwise-sales' => [
                    'label' => 'Customerwise Itemwise Sales',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-itemwise-customerwise-sales' => [
                    'label' => 'Itemwise Customerwise Sales',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-sales-margin-category' => [
                    'label' => 'Categorywise Sales',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-categorywise-sales-detail' => [
                    'label' => 'Categorywise Sales Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-quotation-order-summary' => [
                    'label' => 'Quotation Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-quotation-details' => [
                    'label' => 'Quotation Details',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-sales-order-summary' => [
                    'label' => 'Sales Order Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-sales-order-detail' => [
                    'label' => 'Sales Order Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-sales-order-stock-status' => [
                    'label' => 'Sales Order Stock Status',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-sales-deliverynote-summary' => [
                    'label' => 'Sales DeliveryNote Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-sales-deliverynote-detail' => [
                    'label' => 'Sales DeliveryNote Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-kitchen-preparation-report' => [
                    'label' => 'Kitchen Preparation Report',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-delivery-bill-summary' => [
                    'label' => 'Delivery Bill Summary Report',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-delivery-bill-detail' => [
                    'label' => 'Delivery Bill Detail Report',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-kitchen-preparation-timewise' => [
                    'label' => 'Kitchen Preparation Report [Timewise]',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-sales-return-advice-detail' => [
                    'label' => 'Sales Return Advice Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-buy-back-details' => [
                    'label' => 'Buy Back Details',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-sale-return-customerwise' => [
                    'label' => 'Sale Return Customerwise Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-sales-return-itemwise' => [
                    'label' => 'Sales Return Itemwise',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-sale-return-datewise' => [
                    'label' => 'Sale Return Datewise',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-sale-return-monthwise' => [
                    'label' => 'Sale Return Monthwise',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-sales-return-summary' => [
                    'label' => 'Sales Return Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-sales-cancelled-transactions' => [
                    'label' => 'Cancelled Transactions',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-margin-summary' => [
                    'label' => 'Margin Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-eod' => [
                    'label' => 'Session Report',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-supplier-sales-report' => [
                    'label' => 'Supplier Sales Report',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-supplierwise-sales-details' => [
                    'label' => 'Supplierwise Sales Details',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-date-timewise-sales' => [
                    'label' => 'Date Timewise Sales',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-consumption-sales-summary' => [
                    'label' => 'Consumption Sales Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-consumption-sales-detail' => [
                    'label' => 'Consumption Sales Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-areawise-sales-summary' => [
                    'label' => 'Areawise Sales Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-monthly-sales-detail' => [
                    'label' => 'Monthly Sales Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-non-purchase-customer-list' => [
                    'label' => 'Non Purchase Customer List',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-counterwise-datewise-sales' => [
                    'label' => 'Counterwise Datewise Sales Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-itemwise-monthly-sales-details' => [
                    'label' => 'Itemwise Monthly Sales Qty/Amt Details',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-hold-bill-details' => [
                    'label' => 'Hold Bill Details',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-itemwise-discount-approval' => [
                    'label' => 'ItemWise Discount Approval Details',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
            ]
        ];

        $groups['reports-inventory-group'] = [
            'label' => 'Reports: Stock, Movement & Replenishment',
            'icon' => 'fas fa-boxes text-warning',
            'parent_perm' => 'reports-inventory',
            'modules' => [
                'reports-inventory' => [
                    'label' => '⭐ ALL Inventory Reports (Full Category Access)',
                    'actions' => ['view' => 'Full View Access', 'export' => 'Full Export Access']
                ],
                'reports-itemwise-stock-statement' => [
                    'label' => 'Itemwise Stock Statement',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-itemwise-stock-sales-detail' => [
                    'label' => 'Itemwise Stock and Sales Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-itemwise-stock-sales-transit' => [
                    'label' => 'Itemwise Stock and Sales Detail Transit with Transit',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-transactionwise-stock-register' => [
                    'label' => 'Transactionwise Stock Register',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-closing-stock' => [
                    'label' => 'Closing Stock',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-current-stock' => [
                    'label' => 'Current Stock Branchwise',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-categorywise-datewise-stock' => [
                    'label' => 'Categorywise Datewise Stock Report',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-branchwise-stock-age-analysis' => [
                    'label' => 'Branchwise Stock Age Analysis',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-categorywise-stock-age-analysis' => [
                    'label' => 'Categorywise Stock Age Analysis',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-inventory-price-drop' => [
                    'label' => 'Price Drop',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-inventory-price-level-advance' => [
                    'label' => 'Price Level Advance',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-transfer-out-approval-detail' => [
                    'label' => 'Transfer Out Approval Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-stock-transfer-summary' => [
                    'label' => 'Stock TransferOut Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-stock-transferout-detail' => [
                    'label' => 'Stock TransferOut Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-itemwise-storewise-transferout' => [
                    'label' => 'Itemwise Storewise TransferOut Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-stock-transferin-summary' => [
                    'label' => 'Stock TransferIn Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-stock-transferin-detail' => [
                    'label' => 'Stock TransferIn Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-itemwise-storewise-transferin' => [
                    'label' => 'Itemwise Storewise Transfer In Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-stock-in-transit' => [
                    'label' => 'Stock In Transit',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-stock-transfer-discrepancy' => [
                    'label' => 'Stock Transfer Discrepancy',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-to-vs-tin' => [
                    'label' => 'TO Vs TIN',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-stock-conversion-report' => [
                    'label' => 'Stock Conversion Report',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-stock-update-detail' => [
                    'label' => 'Stock Update Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-damage-stock-summary' => [
                    'label' => 'Wastage/Damage Stock',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-categorywise-storewise-stock-summary' => [
                    'label' => 'Categorywise Storewise Current Stock Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-categorywise-storewise-stock-detail' => [
                    'label' => 'Categorywise Storewise Current Stock Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-stock-fastmoving-item' => [
                    'label' => 'Stock - Categorwise FastMoving - Item',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-stock-slowmoving-item' => [
                    'label' => 'Stock - Categorwise - SlowMoving - Item',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-wastage-damage-stock-detail' => [
                    'label' => 'Wastage/Damage Stock Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-item-age-analysis' => [
                    'label' => 'Item Age Analysis',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-item-expiry-update-details' => [
                    'label' => 'Item Expiry Update Details',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-items-details-in-cart' => [
                    'label' => 'Items Details In Cart',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-stock-reserve-status' => [
                    'label' => 'Stock Reserve Status',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-mbq-detail' => [
                    'label' => 'MBQ Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-kit-preparation' => [
                    'label' => 'Kit Preparation',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-kit-unpack' => [
                    'label' => 'Kit Unpack',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-itemwise-stock-transfer-advice' => [
                    'label' => 'Itemwise Stock Transfer Advice',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-indent-based-replenishment' => [
                    'label' => 'Indent Based On Replenishment',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-indent-summary' => [
                    'label' => 'Indent Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-po-replenishment' => [
                    'label' => 'PO Replenishment',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-picklist-detail' => [
                    'label' => 'Picklist Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-picking-list-discrepancy' => [
                    'label' => 'Picking List Discrepancy',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-opening-stock-detail' => [
                    'label' => 'Opening Stock Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-repack-summary' => [
                    'label' => 'Repack Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-eancode-detail' => [
                    'label' => 'Eancode Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-repack-detail' => [
                    'label' => 'Repack Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-reorder-report' => [
                    'label' => 'Re-order Report',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-issue-date-expiry-details' => [
                    'label' => 'Issue Date Expiry Details',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
            ]
        ];

        $groups['reports-purchase-group'] = [
            'label' => 'Reports: Purchase, Vendor & GRN',
            'icon' => 'fas fa-truck text-success',
            'parent_perm' => 'reports-purchase',
            'modules' => [
                'reports-purchase' => [
                    'label' => '⭐ ALL Purchase Reports (Full Category Access)',
                    'actions' => ['view' => 'Full View Access', 'export' => 'Full Export Access']
                ],
                'reports-purchase-order-summary' => [
                    'label' => 'Purchase Order Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-purchase-transit' => [
                    'label' => 'Purchase Transit',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-purchase-order-details' => [
                    'label' => 'Purchase Order Details',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-receiptnote-nowise-summary' => [
                    'label' => 'ReceiptNote Nowise Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-receiptnote-nowise-detail' => [
                    'label' => 'ReceiptNote Nowise Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-purchase-summary' => [
                    'label' => 'Purchase Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-purchase-detail' => [
                    'label' => 'GST Purchase Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-gin-summary' => [
                    'label' => 'GIN Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-purchase-detail-serial' => [
                    'label' => 'Purchase Detail Serial',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-gin-detail' => [
                    'label' => 'GIN Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-gst-purchase-summary' => [
                    'label' => 'GST Purchase Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-po-vs-invoice' => [
                    'label' => 'Purchase Order vs Invoice Report',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-receiptnote-itemwise' => [
                    'label' => 'ReceiptNote Itemwise',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-purchase-pending-cancelled' => [
                    'label' => 'Pending/Cancelled Transactions',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-purchase-returned-transactions' => [
                    'label' => 'Returned Transactions',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-supplierwise-purchase-summary' => [
                    'label' => 'Supplierwise Purchase Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-po-purchase-discrepancy' => [
                    'label' => 'PO/Purchase Discrepancy',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-supplierwise-purchase-details' => [
                    'label' => 'Supplierwise Purchase Details',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-datewise-itemwise-purchase' => [
                    'label' => 'Datewise Itemwise Consolidated Purchase Report',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-purchase-register-summary' => [
                    'label' => 'Purchase Register Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
            ]
        ];

        $groups['reports-masters-group'] = [
            'label' => 'Reports: Masters, Catalog & Lists',
            'icon' => 'fas fa-database text-info',
            'parent_perm' => 'reports-sales',
            'modules' => [
                'reports-sales' => [
                    'label' => '⭐ ALL Masters Reports (Full Category Access)',
                    'actions' => ['view' => 'Full View Access', 'export' => 'Full Export Access']
                ],
                'reports-customer-master' => [
                    'label' => 'Customer Master',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-uom-vs-item-mapping' => [
                    'label' => 'UOM Vs Item Mapping',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-kit-mapping' => [
                    'label' => 'Kit Mapping',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-customer-parent-list' => [
                    'label' => 'Customer Parent List',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-customer-loyalty' => [
                    'label' => 'Customer Loyalty Details',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-customer-pet-details' => [
                    'label' => 'Customer Pet Details',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-supplier-master' => [
                    'label' => 'Supplier Master',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-brand-master' => [
                    'label' => 'Brand Master',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-tax-master' => [
                    'label' => 'Tax Master',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-area' => [
                    'label' => 'Area',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-branch-master' => [
                    'label' => 'Branch Master',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-item-master' => [
                    'label' => 'Item Master',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-supplier-vs-items' => [
                    'label' => 'Supplier Vs Items',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-price-list' => [
                    'label' => 'Price List',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-employee-master' => [
                    'label' => 'Employee Master',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-ro-master' => [
                    'label' => 'RO Master',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
            ]
        ];

        $groups['reports-finance-group'] = [
            'label' => 'Reports: Finance, Ledgers & Profit/Loss',
            'icon' => 'fas fa-file-invoice-dollar text-info',
            'parent_perm' => 'reports-finance',
            'modules' => [
                'reports-finance' => [
                    'label' => '⭐ ALL Finance Reports (Full Category Access)',
                    'actions' => ['view' => 'Full View Access', 'export' => 'Full Export Access']
                ],
                'reports-general-ledger' => [
                    'label' => 'General Ledger',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-day-book' => [
                    'label' => 'Day Book',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-trial-balance' => [
                    'label' => 'Trial Balance',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-profit-loss' => [
                    'label' => 'Profit & Loss Statement',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-cash-bank-book' => [
                    'label' => 'Cash & Bank Book',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-outstanding-aging' => [
                    'label' => 'Outstanding Aging Analysis',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-customer-loyalty' => [
                    'label' => 'Customer Loyalty Points Report',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
            ]
        ];

        $groups['reports-audit-group'] = [
            'label' => 'Reports: Audit Trail, Security & Logs',
            'icon' => 'fas fa-shield-alt text-danger',
            'parent_perm' => 'reports-audit',
            'modules' => [
                'reports-audit' => [
                    'label' => '⭐ ALL Audit Reports (Full Category Access)',
                    'actions' => ['view' => 'Full View Access']
                ],
                'reports-foot-fall-details' => [
                    'label' => 'Foot Fall Details',
                    'actions' => ['view' => 'View']
                ],
                'reports-reprint-count-details' => [
                    'label' => 'Reprint Count Details',
                    'actions' => ['view' => 'View']
                ],
                'reports-user-login-summary' => [
                    'label' => 'User Login Summary',
                    'actions' => ['view' => 'View']
                ],
                'reports-audit-logs' => [
                    'label' => 'Audit Viewer Report',
                    'actions' => ['view' => 'View']
                ],
                'reports-audit-detail-report' => [
                    'label' => 'Audit Detail Report',
                    'actions' => ['view' => 'View']
                ],
                'reports-gst-tax-change-audit' => [
                    'label' => 'GST Tax Change Audit Report',
                    'actions' => ['view' => 'View']
                ],
                'reports-service-user-consent' => [
                    'label' => 'Service User Consent Summary',
                    'actions' => ['view' => 'View']
                ],
                'reports-email-audit-viewer' => [
                    'label' => 'Email Audit Viewer Report',
                    'actions' => ['view' => 'View']
                ],
                'reports-audit-cart-clear' => [
                    'label' => 'Audit Report (Cart entry Clear)',
                    'actions' => ['view' => 'View']
                ],
                'reports-audit-detail-cart-clear' => [
                    'label' => 'Audit Detail (Cart entry Clear)',
                    'actions' => ['view' => 'View']
                ],
            ]
        ];

        $groups['reports-analytics-group'] = [
            'label' => 'Reports: Intelligence, Custom Builder & 360°',
            'icon' => 'fas fa-chart-pie text-purple',
            'parent_perm' => 'reports-dashboard',
            'modules' => [
                'reports-dashboard' => [
                    'label' => '⭐ ALL General Reports (Full Category Access)',
                    'actions' => ['view' => 'Full View Access', 'export' => 'Full Export Access']
                ],
                'reports-analytics-builder' => [
                    'label' => 'Custom Report Studio (Builder)',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-smart-analytics' => [
                    'label' => 'Smart Item & Customer 360°',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
            ]
        ];

        $groups['reports-my-group'] = [
            'label' => 'Reports: My Custom & Field Trackers',
            'icon' => 'fas fa-folder-open text-primary',
            'parent_perm' => 'reports-sales',
            'modules' => [
                'reports-sales' => [
                    'label' => '⭐ ALL My Reports Reports (Full Category Access)',
                    'actions' => ['view' => 'Full View Access', 'export' => 'Full Export Access']
                ],
                'reports-my-counterwise-sales-summary' => [
                    'label' => 'Counterwise Sales Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-my-sales-mis-report' => [
                    'label' => 'Sales MIS report',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-my-offtake-dealer-tracker' => [
                    'label' => 'Off Take - Dealer Tracker Report',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
            ]
        ];

        $groups['reports-production-group'] = [
            'label' => 'Reports: Manufacturing & BOM Costing',
            'icon' => 'fas fa-industry text-secondary',
            'parent_perm' => 'reports-inventory',
            'modules' => [
                'reports-inventory' => [
                    'label' => '⭐ ALL Production Reports (Full Category Access)',
                    'actions' => ['view' => 'Full View Access', 'export' => 'Full Export Access']
                ],
                'reports-production-plan' => [
                    'label' => 'Production Plan',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-bom-report' => [
                    'label' => 'BOM Report',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-production-costing-summary' => [
                    'label' => 'Production Costing Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-production-costing-detail' => [
                    'label' => 'Production Costing Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-sales-production-variance' => [
                    'label' => 'Sales/Production Variance',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
            ]
        ];

        $groups['reports-more-group'] = [
            'label' => 'Reports: Serialized & Additional Statements',
            'icon' => 'fas fa-ellipsis-h text-dark',
            'parent_perm' => 'reports-dashboard',
            'modules' => [
                'reports-dashboard' => [
                    'label' => '⭐ ALL More Reports (Full Category Access)',
                    'actions' => ['view' => 'Full View Access', 'export' => 'Full Export Access']
                ],
                'reports-serial-number-history' => [
                    'label' => 'Serial Number History',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-serial-nowise-stock-detail' => [
                    'label' => 'Serial No Wise Stock Detail',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-offline-exported-reports' => [
                    'label' => 'Offline Exported Reports',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
                'reports-monthly-transaction-summary' => [
                    'label' => 'Monthly Transaction Summary',
                    'actions' => ['view' => 'View', 'export' => 'Export']
                ],
            ]
        ];

        return $groups;
    }

    /**
     * Check if a user has access to a specific report.
     */
    public static function canAccess(?User $user, string $slugOrUrl, string $action = 'view'): bool
    {
        if (!$user) {
            return false;
        }

        // Super-admin bypass
        if ($user->hasRole(['Owner', 'Admin', 'Super Admin', 'Administrator'])) {
            return true;
        }

        $slug = strtolower(trim($slugOrUrl));
        if (str_starts_with($slug, 'reports/view/')) {
            $slug = substr($slug, strlen('reports/view/'));
        } elseif (str_starts_with($slug, 'reports/')) {
            $slug = substr($slug, strlen('reports/'));
        } elseif ($slug === 'reports') {
            $slug = 'dashboard';
        }

        $reports = self::getAllReports();
        if (!isset($reports[$slug])) {
            return $user->can("reports-dashboard.{$action}") || $user->can("reports-sales.{$action}");
        }

        $meta = $reports[$slug];
        $specificPerm = "{$meta['perm_key']}.{$action}";
        $parentPerm = "{$meta['parent_perm']}.{$action}";

        return $user->can($specificPerm) || $user->can($parentPerm);
    }

    /**
     * Get array of permissions that allow viewing a given URL (used in config/adminlte.php).
     */
    public static function getPermissionsForUrl(string $url): array
    {
        $slug = strtolower(trim($url));
        if (str_starts_with($slug, 'reports/view/')) {
            $slug = substr($slug, strlen('reports/view/'));
        } elseif (str_starts_with($slug, 'reports/')) {
            $slug = substr($slug, strlen('reports/'));
        } elseif ($slug === 'reports') {
            $slug = 'dashboard';
        }

        $reports = self::getAllReports();
        if (!isset($reports[$slug])) {
            return ['reports-dashboard.view', 'reports-sales.view'];
        }

        $meta = $reports[$slug];
        return [
            "{$meta['parent_perm']}.view",
            "{$meta['perm_key']}.view"
        ];
    }

    /**
     * Check if a user has access to any report in a given category.
     */
    public static function hasCategoryAccess(?User $user, string $category): bool
    {
        if (!$user) return false;
        if ($user->hasRole(['Owner', 'Admin', 'Super Admin', 'Administrator'])) return true;

        $all = self::getAllReports();
        foreach ($all as $slug => $meta) {
            if (strcasecmp($meta['category'], $category) === 0) {
                if ($user->can("{$meta['parent_perm']}.view") || $user->can("{$meta['perm_key']}.view")) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check if user has access to at least one report anywhere in the system.
     */
    public static function hasAnyReportAccess(?User $user): bool
    {
        if (!$user) return false;
        if ($user->hasRole(['Owner', 'Admin', 'Super Admin', 'Administrator'])) return true;

        $all = self::getAllReports();
        foreach ($all as $slug => $meta) {
            if ($user->can("{$meta['parent_perm']}.view") || $user->can("{$meta['perm_key']}.view")) {
                return true;
            }
        }
        return false;
    }
}

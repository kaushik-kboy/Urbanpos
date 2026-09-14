<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ToolsController extends Controller
{
    public function renderModule(Request $request, string $module)
    {
        $configs = [
            'userwise-config' => [
                'title' => 'Userwise Configuration',
                'section' => 'Tools > Configuration',
                'icon' => 'fas fa-user-cog',
                'newButton' => 'Add User Config',
                'columns' => ['User Name', 'Branch Access', 'Max Discount %', 'Allow Rate Edit', 'Status'],
            ],
            'business-config' => [
                'title' => 'Business Configuration (System Settings)',
                'section' => 'Tools > Configuration',
                'icon' => 'fas fa-briefcase',
                'newButton' => 'Edit Configuration',
                'columns' => ['Setting Category', 'Parameter Name', 'Current Setting', 'Effective Date', 'Action'],
            ],
            'function-keys' => [
                'title' => 'Function Key Mapping',
                'section' => 'Tools > Configuration',
                'icon' => 'fas fa-keyboard',
                'newButton' => 'Remap Key',
                'columns' => ['Shortcut Key', 'Assigned Function / Action', 'Screen Scope', 'Default Key', 'Status'],
            ],
            'ledger-map' => [
                'title' => 'Ledger Map Configuration',
                'section' => 'Tools > Configuration',
                'icon' => 'fas fa-book',
                'newButton' => 'Add Ledger Mapping',
                'columns' => ['Transaction Type', 'Debit Ledger', 'Credit Ledger', 'Tax Ledger', 'Status'],
            ],
            'category-sequence' => [
                'title' => 'Category Wise Sequence',
                'section' => 'Tools > Configuration',
                'icon' => 'fas fa-sort-numeric-down',
                'newButton' => 'Define Sequence',
                'columns' => ['Category Name', 'Prefix', 'Starting Number', 'Current Counter', 'Status'],
            ],
            'mail-server' => [
                'title' => 'Mail Server Configuration',
                'section' => 'Tools > Configuration',
                'icon' => 'fas fa-envelope-open-text',
                'newButton' => 'Configure SMTP',
                'columns' => ['Host', 'Port', 'Username / From Email', 'Encryption', 'Test Status'],
            ],
            'asset-ledger' => [
                'title' => 'Asset Ledger Map',
                'section' => 'Tools > Configuration',
                'icon' => 'fas fa-landmark',
                'newButton' => 'Map Asset',
                'columns' => ['Asset Category', 'Asset Ledger', 'Depreciation Ledger', 'Status'],
            ],
            'integrations-alert' => [
                'title' => 'GoFrugal Alert Integration',
                'section' => 'Tools > Integrations',
                'icon' => 'fas fa-bell',
                'newButton' => 'New Alert Rule',
                'columns' => ['Alert Type', 'Trigger Event', 'Recipients (SMS/Email)', 'Status'],
            ],
            'integrations-gst' => [
                'title' => 'GST E-Filing & E-Invoice Integration',
                'section' => 'Tools > Integrations',
                'icon' => 'fas fa-file-invoice-dollar',
                'newButton' => 'Configure GSP API',
                'columns' => ['GSTIN', 'GSP Provider', 'API Environment', 'Token Status', 'Last Sync'],
            ],
            'integrations-gosure' => [
                'title' => 'GOFRUGAL GoSure Mobile App Integration',
                'section' => 'Tools > Integrations',
                'icon' => 'fas fa-mobile-alt',
                'newButton' => 'Register Mobile User',
                'columns' => ['Device ID', 'Assigned Staff', 'Sync Frequency', 'Last Sync Timestamp', 'Status'],
            ],
            'year-begin-sequence' => [
                'title' => 'Year Begin Sequence Change',
                'section' => 'Tools > More',
                'icon' => 'fas fa-calendar-alt',
                'newButton' => 'Set Financial Year Prefix',
                'columns' => ['Financial Year', 'Document Type', 'New Prefix', 'Starting Sequence', 'Status'],
            ],
            'session-management' => [
                'title' => 'Session Management (Active Users)',
                'section' => 'Tools > More',
                'icon' => 'fas fa-users-cog',
                'newButton' => null,
                'columns' => ['User', 'Login Time', 'IP Address', 'Branch', 'Last Active', 'Action'],
            ],
            'reprint' => [
                'title' => 'Reprint Manager',
                'section' => 'Tools > More',
                'icon' => 'fas fa-print',
                'newButton' => null,
                'columns' => ['Document Type', 'Doc Number', 'Original Print Date', 'Reprint Count', 'Action'],
            ],
            'multiple-dispatch' => [
                'title' => 'Multiple Dispatch Order Preparation',
                'section' => 'Tools > More',
                'icon' => 'fas fa-boxes',
                'newButton' => 'New Dispatch Batch',
                'columns' => ['Batch No', 'Orders Count', 'Assigned Delivery Vehicle', 'Dispatch Status'],
            ],
            'report-scheduler' => [
                'title' => 'Report Scheduler',
                'section' => 'Tools > More',
                'icon' => 'fas fa-clock',
                'newButton' => 'Schedule Report',
                'columns' => ['Report Name', 'Cron Frequency', 'Recipients Email', 'Format (PDF/Excel)', 'Status'],
            ],
            'eway-update' => [
                'title' => 'E-Way Bill Generation & Update',
                'section' => 'Tools > More',
                'icon' => 'fas fa-road',
                'newButton' => 'Generate E-Way Bill',
                'columns' => ['Doc No', 'Doc Date', 'Customer', 'Transporter Name', 'E-Way Bill No', 'Status'],
            ],
            'barcode-config' => [
                'title' => 'Barcode Config (Label Layout Designer)',
                'section' => 'Tools > More',
                'icon' => 'fas fa-barcode',
                'newButton' => 'Create Template',
                'columns' => ['Template Name', 'Label Width (mm)', 'Label Height (mm)', 'Barcode Type', 'Status'],
            ],
            'service-consent' => [
                'title' => 'Service User Consent Master',
                'section' => 'Tools > More',
                'icon' => 'fas fa-user-shield',
                'newButton' => 'New Consent Form',
                'columns' => ['Customer Name', 'Consent Category', 'Given Timestamp', 'Channel', 'Status'],
            ],
            'master-migration' => [
                'title' => 'Master Data Migration',
                'section' => 'Tools',
                'icon' => 'fas fa-database',
                'newButton' => 'Import Migration File',
                'columns' => ['Job ID', 'File Name', 'Target Master', 'Rows Processed', 'Errors', 'Date'],
            ],
            'manage-subscription' => [
                'title' => 'Manage Subscription & Licenses',
                'section' => 'Tools',
                'icon' => 'fas fa-certificate',
                'newButton' => 'Renew License',
                'columns' => ['Plan Name', 'Registered Company', 'Active Counters', 'Expiry Date', 'Status'],
            ],
        ];

        $config = $configs[$module] ?? [
            'title' => ucwords(str_replace('-', ' ', $module)),
            'section' => 'Tools',
            'icon' => 'fas fa-wrench',
            'newButton' => 'New Config',
            'columns' => ['Parameter', 'Value', 'Status', 'Updated At'],
        ];

        return view('common.module-view', $config);
    }
}

<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Services\Reports\DynamicReportService;
use Illuminate\Http\Request;

$user = User::first();
auth()->login($user);

$service = app(DynamicReportService::class);

$testModules = [
    'itemwise-stock-statement',
    'branchwise-stock-age-analysis',
    'mbq-detail',
    'stock-fast-moving',
    'stock-slow-moving'
];

foreach ($testModules as $slug) {
    $req = Request::create("/reports/inventory/{$slug}", 'GET', [
        'from' => '2026-04-01',
        'to'   => '2026-10-31',
    ]);
    $res = $service->generate($req, $slug);
    echo "Report: {$slug}" . PHP_EOL;
    echo "  - Title: " . $res['title'] . PHP_EOL;
    echo "  - Columns: " . count($res['columns']) . PHP_EOL;
    echo "  - Rows count: " . $res['rows']->count() . PHP_EOL;
    echo "  - KPIs count: " . count($res['kpis'] ?? []) . PHP_EOL;
}

echo "SUCCESS: All Critical Inventory Intelligence Reports tested cleanly!" . PHP_EOL;

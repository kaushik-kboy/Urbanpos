<?php
/**
 * Branch / path coverage summary from a PHPUnit --coverage-php file recorded under Xdebug (--path-coverage).
 *   php scripts/qa/branch_coverage.php storage/coverage/cov_branch.php
 * Reports: business scope (excludes app/Console/Commands one-off import/sync utilities) and everything.
 */
require __DIR__.'/../../vendor/autoload.php';

$file = $argv[1] ?? __DIR__.'/../../storage/coverage/cov_branch.php';
/** @var SebastianBergmann\CodeCoverage\CodeCoverage $cc */
$cc = include $file;
$data = $cc->getData();
$fn = $data->functionCoverage();
$lines = $data->lineCoverage();

$acc = ['biz' => ['br' => 0, 'brHit' => 0, 'pa' => 0, 'paHit' => 0, 'fn' => 0, 'fnHit' => 0],
        'all' => ['br' => 0, 'brHit' => 0, 'pa' => 0, 'paHit' => 0, 'fn' => 0, 'fnHit' => 0]];
$perFile = [];

foreach ($fn as $path => $functions) {
    $norm = str_replace('\\', '/', $path);
    if (! str_contains($norm, '/app/')) {
        continue;
    }
    $isConsole = str_contains($norm, '/app/Console/Commands/');
    foreach ($functions as $name => $info) {
        $branches = $info['branches'] ?? [];
        $paths = $info['paths'] ?? [];
        $brHit = count(array_filter($branches, fn ($b) => ! empty($b['hit'])));
        $paHit = count(array_filter($paths, fn ($p) => ! empty($p['hit'])));
        $fnHit = $brHit > 0 ? 1 : 0;
        foreach (['all', $isConsole ? null : 'biz'] as $scope) {
            if ($scope === null) {
                continue;
            }
            $acc[$scope]['br'] += count($branches);
            $acc[$scope]['brHit'] += $brHit;
            $acc[$scope]['pa'] += count($paths);
            $acc[$scope]['paHit'] += $paHit;
            $acc[$scope]['fn'] += 1;
            $acc[$scope]['fnHit'] += $fnHit;
        }
        if (! $isConsole && count($branches)) {
            $short = substr($norm, strpos($norm, '/app/') + 1);
            $perFile[$short]['br'] = ($perFile[$short]['br'] ?? 0) + count($branches);
            $perFile[$short]['hit'] = ($perFile[$short]['hit'] ?? 0) + $brHit;
        }
    }
}

$pct = fn ($a, $b) => $b ? sprintf('%.1f%% (%d/%d)', 100 * $a / $b, $a, $b) : 'n/a';
foreach (['biz' => 'BUSINESS SCOPE (excl. one-off Console imports)', 'all' => 'ALL app files'] as $k => $label) {
    $a = $acc[$k];
    echo "$label\n  branches: ".$pct($a['brHit'], $a['br'])."\n  paths:    ".$pct($a['paHit'], $a['pa'])."\n  functions with >=1 branch hit: ".$pct($a['fnHit'], $a['fn'])."\n";
}
uasort($perFile, fn ($x, $y) => ($y['br'] - $y['hit']) <=> ($x['br'] - $x['hit']));
echo "\nLargest uncovered-branch files (business scope):\n";
foreach (array_slice($perFile, 0, 15, true) as $f => $v) {
    printf("  %4d missed / %4d  %5.1f%%  %s\n", $v['br'] - $v['hit'], $v['br'], 100 * $v['hit'] / $v['br'], $f);
}

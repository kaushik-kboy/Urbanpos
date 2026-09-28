<?php
require __DIR__.'/../../vendor/autoload.php';
/** @var SebastianBergmann\CodeCoverage\CodeCoverage $cc */
$cc = include __DIR__.'/../../storage/coverage/cov_pcov_full.php';
$report = $cc->getReport();
foreach ($report as $item) {
    if (! $item instanceof SebastianBergmann\CodeCoverage\Node\File) {
        continue;
    }
    foreach ($item->classesAndTraits() as $className => $class) {
        foreach ($class['methods'] as $methodName => $m) {
            if ($m['coverage'] < 100) {
                $uncoveredLines = [];
                foreach (range($m['startLine'], $m['endLine']) as $ln) {
                    $data = $item->lineCoverageData()[$ln] ?? null;
                    if (is_array($data) && count($data) === 0) {
                        $uncoveredLines[] = $ln;
                    }
                }
                echo $item->pathAsString().' | '.$className.'::'.$methodName
                    .' | method lines '.$m['startLine'].'-'.$m['endLine']
                    .' | coverage='.$m['coverage'].'%'
                    .' | UNCOVERED LINES: '.implode(',', $uncoveredLines)."\n";
            }
        }
    }
}

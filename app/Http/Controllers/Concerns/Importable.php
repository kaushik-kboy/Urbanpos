<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Generic CSV/XLS/XLSX import for master modules.
 * Handles both standard sample formats and POS exported CSV/XLS files.
 */
trait Importable
{
    /** Fully qualified Eloquent model class this import writes to. */
    abstract protected function importModel(): string;

    /**
     * Header label => column definition.
     * ['column' => 'field', 'required' => bool, 'cast' => callable, 'aliases' => string[]]
     */
    abstract protected function importColumns(): array;

    /** DB column names used to find an existing row to update (upsert key). */
    abstract protected function importUniqueBy(): array;

    /**
     * Header label => resolver(string $value, array $dataSoFar): array
     */
    protected function importRelations(): array
    {
        return [];
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xls,xlsx'],
        ]);

        $sheets = Excel::toCollection(null, $request->file('file'));
        $rows = $sheets->first();

        if (! $rows || $rows->count() < 1) {
            return back()->with('import_result', [
                'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['The file has no data rows.'], 'error_count' => 1,
            ]);
        }

        $columns = $this->importColumns();
        $relations = $this->importRelations();
        $uniqueBy = $this->importUniqueBy();
        $modelClass = $this->importModel();

        // 1. Locate real header row (handles company title metadata rows)
        $headerRowIndex = 0;
        $headerIndex = [];

        foreach ($rows as $rIdx => $row) {
            $rowValues = array_map(fn ($v) => trim((string) $v), $row->toArray());
            $tempHeaderIndex = [];
            foreach ($rowValues as $colIdx => $label) {
                if ($label !== '') {
                    $tempHeaderIndex[$label] = $colIdx;
                }
            }

            $matchedCount = 0;
            foreach ($columns as $label => $def) {
                $aliases = array_merge([$label], $def['aliases'] ?? [], $this->getGlobalAliases($label));
                foreach ($aliases as $alias) {
                    if (isset($tempHeaderIndex[$alias])) {
                        $matchedCount++;
                        break;
                    }
                }
            }

            if ($matchedCount > 0) {
                $headerRowIndex = $rIdx;
                $headerIndex = $tempHeaderIndex;
                break;
            }
        }

        if (empty($headerIndex)) {
            // Fallback to first row
            foreach ($rows->first() as $i => $label) {
                $labelStr = trim((string) $label);
                if ($labelStr !== '') {
                    $headerIndex[$labelStr] = $i;
                }
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $dataRows = $rows->slice($headerRowIndex + 1);

        foreach ($dataRows as $rowNum => $row) {
            $lineNumber = $headerRowIndex + $rowNum + 2;

            if ($row->every(fn ($v) => trim((string) $v) === '')) {
                continue;
            }

            $data = [];
            $rowError = null;

            foreach ($columns as $label => $def) {
                $aliases = array_merge([$label], $def['aliases'] ?? [], $this->getGlobalAliases($label));
                $value = '';
                foreach ($aliases as $alias) {
                    if (isset($headerIndex[$alias])) {
                        $rawVal = $row[$headerIndex[$alias]] ?? '';
                        $valStr = trim((string) $rawVal);
                        if ($valStr !== '') {
                            $value = $valStr;
                            break;
                        }
                    }
                }

                if (($def['required'] ?? false) && $value === '') {
                    $rowError = "Missing required value for \"{$label}\".";
                    break;
                }

                if ($value === '') {
                    continue;
                }

                $castValue = isset($def['cast']) && is_callable($def['cast'])
                    ? call_user_func($def['cast'], $value)
                    : $value;

                $data[$def['column']] = $castValue;
            }

            if (! $rowError) {
                foreach ($relations as $label => $resolver) {
                    $aliases = array_merge([$label], $this->getGlobalAliases($label));
                    $value = '';
                    foreach ($aliases as $alias) {
                        if (isset($headerIndex[$alias])) {
                            $rawVal = $row[$headerIndex[$alias]] ?? '';
                            $valStr = trim((string) $rawVal);
                            if ($valStr !== '') {
                                $value = $valStr;
                                break;
                            }
                        }
                    }

                    try {
                        $resolved = $resolver($value, $data);
                    } catch (\Throwable $e) {
                        $resolved = ['__error' => $e->getMessage()];
                    }

                    if (is_array($resolved) && array_key_exists('__error', $resolved)) {
                        $rowError = $resolved['__error'];
                        break;
                    }

                    $data = array_merge($data, $resolved ?? []);
                }
            }

            if ($rowError) {
                $errors[] = "Row {$lineNumber}: {$rowError}";
                $skipped++;
                continue;
            }

            // Determine lookup key for upsert
            $lookup = [];
            foreach ($uniqueBy as $col) {
                if (isset($data[$col]) && $data[$col] !== null && $data[$col] !== '') {
                    $lookup[$col] = $data[$col];
                }
            }

            // Fallback lookup by 'name' if primary unique key is absent
            if (empty($lookup) && isset($data['name']) && $data['name'] !== '') {
                $lookup = ['name' => $data['name']];
            }

            if (empty($lookup)) {
                $errors[] = "Row {$lineNumber}: Could not determine a unique key to match this record.";
                $skipped++;
                continue;
            }

            try {
                $existing = $modelClass::where($lookup)->first();

                if ($existing) {
                    $existing->update($data);
                    $updated++;
                } else {
                    $modelClass::create($data);
                    $created++;
                }
            } catch (\Throwable $e) {
                $errors[] = "Row {$lineNumber}: ".$e->getMessage();
                $skipped++;
            }
        }

        return back()->with('import_result', [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 20),
            'error_count' => count($errors),
        ]);
    }

    public function importSample()
    {
        $headers = array_keys($this->importColumns());
        $filename = Str::slug(class_basename($this->importModel())).'-import-sample.csv';

        return response()->streamDownload(function () use ($headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** Global alias mapping to support POS export column headers out of the box */
    protected function getGlobalAliases(string $label): array
    {
        $map = [
            'Name' => ['Item name', 'Customer Name', 'Brand name', 'Supplier', 'Full name', 'Branch', 'Description', 'Item'],
            'EAN/UPC Code' => ['ISBN', 'Unique Barcode Id', 'Barcode', 'EAN', 'UPC', 'Code'],
            'Customer Code' => ['Code', 'CID', 'Customer ID', 'Customer Code'],
            'Cost Price' => ['Pur_net', 'Landing cost', 'Cost Price', 'Cost'],
            'Landing Cost' => ['Landing cost', 'Pur_net', 'Landing Cost'],
            'Sell Price' => ['Selling', 'Selling Price', 'Sell Price', 'Price'],
            'MRP' => ['MRP', 'Mrp', 'Packed price'],
            'Status' => ['Status', 'Customer Status'],
            'Address1' => ['Address', 'Address1', 'Address 1'],
            'Address' => ['Address', 'Address1'],
            'City' => ['Place', 'City'],
            'State' => ['State Name', 'State'],
            'Postal Code' => ['Postal / ZIP code', 'Postal Code', 'ZIP Code', 'Pincode'],
            'GST No' => ['GST No.', 'GST No', 'Tax Reg No'],
            'Brand' => ['Brand name', 'BRANDS', 'Brand'],
            'Supplier' => ['Supplier', 'Supplier Name'],
            'Department' => ['DEPARTMENT', 'Department'],
            'Category' => ['CATEGORY', 'Category'],
            'GST Tax' => ['GST Rate', 'Tax %', 'Tax description', 'GST Tax'],
            'HSN Code' => ['HSN Code', 'HSN'],
            'Title' => ['Title'],
            'Mobile' => ['Mobile', 'Phone'],
            'Phone' => ['Phone', 'Mobile'],
        ];

        return $map[$label] ?? [];
    }

    /** Helper: parse common "yes/no/active/1/0" style values into a boolean. */
    protected function importBool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'yes', 'y', 'true', 'active']);
    }

    /** Helper: parse a numeric value, returning null for blanks. */
    protected function importDecimal(string $value): ?float
    {
        $value = trim(str_replace(',', '', $value));

        return $value === '' ? null : (float) $value;
    }
}

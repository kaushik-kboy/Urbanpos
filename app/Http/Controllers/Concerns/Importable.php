<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Generic CSV/XLS/XLSX import for master modules.
 *
 * Controllers using this trait must implement importModel(), importColumns()
 * and importUniqueBy(). importRelations() is optional, for columns that need
 * to be resolved against another table (e.g. a "Brand" name column resolved
 * to brand_id).
 */
trait Importable
{
    /** Fully qualified Eloquent model class this import writes to. */
    abstract protected function importModel(): string;

    /**
     * Header label => column definition.
     * ['DB Column' => 'field', 'required' => bool, 'cast' => callable(string $value): mixed]
     */
    abstract protected function importColumns(): array;

    /** DB column names used to find an existing row to update (upsert key). */
    abstract protected function importUniqueBy(): array;

    /**
     * Header label => resolver(string $value, array $dataSoFar): array
     * Returning the extra column(s) to merge into the row, e.g. ['brand_id' => 5].
     * Return ['__error' => 'message'] to reject the row.
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

        if (! $rows || $rows->count() < 2) {
            return back()->with('import_result', [
                'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['The file has no data rows.'], 'error_count' => 1,
            ]);
        }

        $headerIndex = [];
        foreach ($rows->first() as $i => $label) {
            $headerIndex[trim((string) $label)] = $i;
        }

        $columns = $this->importColumns();
        $relations = $this->importRelations();
        $uniqueBy = $this->importUniqueBy();
        $modelClass = $this->importModel();

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows->slice(1) as $rowNum => $row) {
            $lineNumber = $rowNum + 2;

            if ($row->every(fn ($v) => trim((string) $v) === '')) {
                continue;
            }

            $data = [];
            $rowError = null;

            foreach ($columns as $label => $def) {
                $index = $headerIndex[$label] ?? null;
                $value = $index !== null ? trim((string) ($row[$index] ?? '')) : '';

                if (($def['required'] ?? false) && $value === '') {
                    $rowError = "Missing required value for \"{$label}\".";
                    break;
                }

                if ($value === '') {
                    // Leave the column out of $data entirely: on create this lets the
                    // database's own ->default(...) apply (an explicit NULL would
                    // violate NOT NULL columns even though they have a default), and
                    // on update it leaves the existing value untouched.
                    continue;
                }

                $data[$def['column']] = isset($def['cast']) && is_callable($def['cast'])
                    ? call_user_func($def['cast'], $value)
                    : $value;
            }

            if (! $rowError) {
                foreach ($relations as $label => $resolver) {
                    $index = $headerIndex[$label] ?? null;
                    $value = $index !== null ? trim((string) ($row[$index] ?? '')) : '';

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

            $lookup = [];
            foreach ($uniqueBy as $col) {
                $lookup[$col] = $data[$col] ?? null;
            }

            if (collect($lookup)->filter(fn ($v) => $v !== null && $v !== '')->isEmpty()) {
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

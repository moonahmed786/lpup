<?php

namespace App\Imports;

use App\Enums\ProductStatus;
use App\Exceptions\ProductImportStopped;
use App\Models\ProductImport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\BeforeImport;

/**
 * Chunked, memory-safe products import.
 *
 * Reads 1,000 rows at a time, validates each row, and performs a raw
 * DB::table()->upsert() keyed on `sku` (idempotent — re-imports update
 * existing rows instead of duplicating). Invalid rows are written to a
 * per-import CSV and counted, but never abort the import.
 */
class ProductsImport implements ToCollection, WithChunkReading, WithEvents, WithHeadingRow
{
    private const CHUNK = 1000;

    private bool $failureHeaderWritten = false;

    public function __construct(private readonly ProductImport $import) {}

    public function chunkSize(): int
    {
        return self::CHUNK;
    }

    /**
     * Capture the total row count before processing so the UI can show a
     * meaningful progress percentage.
     */
    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event): void {
                $totalRows = $event->getReader()->getTotalRows();
                // getTotalRows() includes the heading row; use the first sheet.
                $rows = $totalRows ? max(0, ((int) reset($totalRows)) - 1) : 0;

                $this->import->forceFill(['total_rows' => $rows])->save();
            },
        ];
    }

    public function collection(Collection $rows): void
    {
        $this->ensureNotStopped();

        $valid = [];
        $failures = [];
        $now = now();

        foreach ($rows as $row) {
            $data = $this->normalise($row);
            $error = $this->validateRow($data);

            if ($error !== null) {
                $failures[] = $data + ['error' => $error];

                continue;
            }

            // Keyed by sku so duplicates within the chunk collapse (last wins),
            // which keeps the upsert statement valid.
            $valid[$data['sku']] = [
                'sku' => $data['sku'],
                'name' => $data['name'],
                'quantity' => (int) $data['quantity'],
                'price' => $data['price'] !== null && $data['price'] !== '' ? (float) $data['price'] : null,
                'description' => $data['description'],
                'status' => $data['status'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($valid !== []) {
            DB::table('products')->upsert(
                array_values($valid),
                ['sku'],
                ['name', 'quantity', 'price', 'description', 'status', 'updated_at'],
            );
        }

        if ($failures !== []) {
            $this->writeFailures($failures);
        }

        // Progress: every row in the chunk is "processed" (valid or failed).
        $this->import->increment('processed_rows', $rows->count());
        if ($failures !== []) {
            $this->import->increment('failed_rows', count($failures));
        }

        $this->ensureNotStopped();
    }

    /**
     * @param  Collection<string, mixed>  $row
     * @return array{name: ?string, sku: ?string, quantity: mixed, status: ?string}
     */
    private function normalise(Collection $row): array
    {
        $status = $this->str($row->get('status'));

        return [
            'name' => $this->str($row->get('name')),
            'sku' => $this->str($row->get('sku')),
            'quantity' => $row->get('quantity') ?? $row->get('stock'),
            'price' => $row->get('price'),
            'description' => $this->str($row->get('description')),
            'status' => $status !== null && $status !== '' ? $status : ProductStatus::Draft->value,
        ];
    }

    private function str(mixed $value): ?string
    {
        return $value === null ? null : trim((string) $value);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateRow(array $data): ?string
    {
        if (($data['name'] ?? '') === '' || $data['name'] === null) {
            return 'name is required';
        }

        if (($data['sku'] ?? '') === '' || $data['sku'] === null) {
            return 'sku is required';
        }

        if (! is_numeric($data['quantity']) || (int) $data['quantity'] < 0) {
            return 'quantity must be an integer >= 0';
        }

        if ($data['price'] !== null && $data['price'] !== '' && ! is_numeric($data['price'])) {
            return 'price must be a number';
        }

        if (! in_array($data['status'], ProductStatus::values(), true)) {
            return 'status must be one of: '.implode(', ', ProductStatus::values());
        }

        return null;
    }

    private function ensureNotStopped(): void
    {
        $this->import->refresh();

        if ($this->import->stop_requested_at !== null) {
            throw new ProductImportStopped;
        }
    }

    /**
     * Append invalid rows to the import's failure CSV (created lazily).
     *
     * @param  array<int, array<string, mixed>>  $failures
     */
    private function writeFailures(array $failures): void
    {
        $path = $this->import->error_log_path
            ?? "imports/failures/import_{$this->import->id}.csv";

        $disk = Storage::disk(config('product_import.disk'));

        if ($this->import->error_log_path === null) {
            $this->import->forceFill(['error_log_path' => $path])->save();
        }

        if (! $this->failureHeaderWritten && ! $disk->exists($path)) {
            $disk->put($path, "name,sku,quantity,price,description,status,error\n");
        }

        $this->failureHeaderWritten = true;

        $handle = fopen($disk->path($path), 'a');
        foreach ($failures as $f) {
            fputcsv($handle, [$f['name'], $f['sku'], $f['quantity'], $f['price'], $f['description'], $f['status'], $f['error']]);
        }
        fclose($handle);
    }
}

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
 * Reads 1,000 rows at a time and validates each row. Only brand-new SKUs are
 * inserted; duplicates are treated as failures rather than overwriting data:
 *   - a SKU that already exists in the products table is reported as
 *     "duplicate sku (already exists)" and skipped;
 *   - a SKU that repeats within the same uploaded file is reported as
 *     "duplicate sku in file" and skipped (the first occurrence is inserted).
 * Invalid and duplicate rows are written to a per-import failures CSV and
 * counted, but never abort the import.
 */
class ProductsImport implements ToCollection, WithChunkReading, WithEvents, WithHeadingRow
{
    private const CHUNK = 1000;

    private bool $failureHeaderWritten = false;

    /**
     * SKUs already seen in this import run, used to detect duplicates that
     * repeat within the uploaded file (the instance is reused across chunks).
     *
     * @var array<string, true>
     */
    private array $seenSkus = [];

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

        $candidates = [];
        $failures = [];
        $now = now();

        foreach ($rows as $row) {
            $data = $this->normalise($row);
            $error = $this->validateRow($data);

            if ($error !== null) {
                $failures[] = $data + ['error' => $error];

                continue;
            }

            // A SKU repeated within the uploaded file: keep the first occurrence,
            // report every later one as a failure.
            if (isset($this->seenSkus[$data['sku']])) {
                $failures[] = $data + ['error' => 'duplicate sku in file'];

                continue;
            }

            $this->seenSkus[$data['sku']] = true;
            $candidates[$data['sku']] = $data;
        }

        // Reject candidates whose SKU already exists in the database (carried
        // over from a previous import or chunk) instead of overwriting them.
        if ($candidates !== []) {
            $existing = DB::table('products')
                ->whereIn('sku', array_keys($candidates))
                ->pluck('sku')
                ->all();

            $insert = [];

            foreach ($candidates as $sku => $data) {
                if (in_array($sku, $existing, true)) {
                    $failures[] = $data + ['error' => 'duplicate sku (already exists)'];

                    continue;
                }

                $insert[] = [
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

            if ($insert !== []) {
                DB::table('products')->insert($insert);
            }
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
            $disk->put($path, 'name,sku,quantity,price,description,status,error');
        }

        $this->failureHeaderWritten = true;

        $disk->append($path, $this->csv($failures));
    }

    /**
     * @param  array<int, array<string, mixed>>  $failures
     */
    private function csv(array $failures): string
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($failures as $f) {
            fputcsv($handle, [$f['name'], $f['sku'], $f['quantity'], $f['price'], $f['description'], $f['status'], $f['error']]);
        }

        rewind($handle);
        $csv = rtrim((string) stream_get_contents($handle), "\n");
        fclose($handle);

        return $csv;
    }
}

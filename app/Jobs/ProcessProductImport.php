<?php

namespace App\Jobs;

use App\Enums\ProductImportStatus;
use App\Exceptions\ProductImportStopped;
use App\Imports\ProductsImport;
use App\Models\ProductImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\FailOnTimeout;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Processes an uploaded products file on the queue.
 *
 * The import reader handles rows in chunks, so large CSV/XLSX files do not
 * have to be loaded into memory all at once.
 */
#[Tries(3)]
#[Backoff([10, 30, 60])]
#[Timeout(900)]
#[FailOnTimeout]
class ProcessProductImport implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $importId, public ?string $batchId = null) {}

    public function handle(): void
    {
        $import = ProductImport::find($this->importId);

        if ($import === null) {
            return;
        }

        if ($this->batchId !== null && $import->batch_id !== $this->batchId) {
            return;
        }

        if ($import->stop_requested_at !== null || $import->status === ProductImportStatus::Stopped) {
            $import->forceFill([
                'status' => ProductImportStatus::Stopped,
                'completed_at' => $import->completed_at ?? now(),
            ])->save();

            return;
        }

        $import->forceFill([
            'status' => ProductImportStatus::Processing,
            'started_at' => $import->started_at ?? now(),
            // Retries and manual restarts should begin with fresh counters.
            'processed_rows' => 0,
            'failed_rows' => 0,
            'error_log_path' => null,
        ])->save();

        Storage::disk(config('product_import.disk'))->delete("imports/failures/import_{$import->id}.csv");

        try {
            Excel::import(new ProductsImport($import), $import->path, config('product_import.disk'));
        } catch (ProductImportStopped) {
            $import->refresh()->forceFill([
                'status' => ProductImportStatus::Stopped,
                'completed_at' => now(),
            ])->save();

            return;
        }

        $import->forceFill([
            'status' => ProductImportStatus::Completed,
            'completed_at' => now(),
        ])->save();
    }

    /**
     * Mark the import after the queue has exhausted its retries.
     */
    public function failed(?Throwable $exception): void
    {
        if ($exception instanceof ProductImportStopped) {
            ProductImport::whereKey($this->importId)->update([
                'status' => ProductImportStatus::Stopped->value,
                'completed_at' => now(),
            ]);

            return;
        }

        ProductImport::whereKey($this->importId)->update([
            'status' => ProductImportStatus::Failed->value,
            'completed_at' => now(),
        ]);
    }
}

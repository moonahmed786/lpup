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
 * Uses Laravel 13's first-party queue attributes (read by the queue on
 * dispatch via ReadsQueueAttributes): retry up to 3 times with a 10s/30s/60s
 * progressive backoff, a 15-minute timeout, and treat a timeout as a failure.
 *
 * The actual parsing is delegated to ProductsImport, which reads the file in
 * 1,000-row chunks (synchronously, in this job) and upserts on `sku`, keeping
 * memory flat regardless of file size.
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
            // Reset counters so retries/re-imports start clean.
            'processed_rows' => 0,
            'failed_rows' => 0,
            'error_log_path' => null,
        ])->save();

        Storage::disk('local')->delete("imports/failures/import_{$import->id}.csv");

        try {
            Excel::import(new ProductsImport($import), $import->path, 'local');
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
     * Called after the final retry fails (or on timeout, per #[FailOnTimeout]).
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
        ]);
    }
}

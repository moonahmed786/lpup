<?php

namespace App\Services;

use App\Enums\ProductImportStatus;
use App\Jobs\ProcessProductImport;
use App\Models\ProductImport;
use Illuminate\Support\Str;

class ProductImportService
{
    /**
     * Record an uploaded import and queue it for processing.
     *
     * @param  string  $storedPath  Path on the `local` disk where the upload was saved.
     * @param  string  $originalFilename  Display name shown in the UI.
     */
    public function startImport(string $storedPath, string $originalFilename, ?int $userId = null): ProductImport
    {
        $batchId = (string) Str::uuid();

        $import = ProductImport::create([
            'user_id' => $userId,
            'filename' => $originalFilename,
            'path' => $storedPath,
            'status' => ProductImportStatus::Pending,
            'batch_id' => $batchId,
        ]);

        ProcessProductImport::dispatch($import->id, $batchId);

        return $import;
    }

    public function startExisting(ProductImport $import): ProductImport
    {
        if (! $import->canStart()) {
            return $import;
        }

        $batchId = (string) Str::uuid();

        $import->forceFill([
            'status' => ProductImportStatus::Pending,
            'batch_id' => $batchId,
            'stop_requested_at' => null,
            'completed_at' => null,
        ])->save();

        ProcessProductImport::dispatch($import->id, $batchId);

        return $import->refresh();
    }

    public function requestStop(ProductImport $import): ProductImport
    {
        if (! $import->canStop()) {
            return $import;
        }

        $import->forceFill([
            'status' => $import->status === ProductImportStatus::Pending
                ? ProductImportStatus::Stopped
                : ProductImportStatus::Stopping,
            'stop_requested_at' => now(),
            'completed_at' => $import->status === ProductImportStatus::Pending ? now() : $import->completed_at,
        ])->save();

        return $import->refresh();
    }
}

<?php

namespace App\Services;

use App\Enums\ProductImportStatus;
use App\Jobs\ProcessProductImport;
use App\Models\ProductImport;

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
        $import = ProductImport::create([
            'user_id' => $userId,
            'filename' => $originalFilename,
            'path' => $storedPath,
            'status' => ProductImportStatus::Pending,
        ]);

        ProcessProductImport::dispatch($import->id);

        return $import;
    }
}

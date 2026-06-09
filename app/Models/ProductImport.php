<?php

namespace App\Models;

use App\Enums\ProductImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImport extends Model
{
    protected $fillable = [
        'user_id',
        'filename',
        'path',
        'total_rows',
        'processed_rows',
        'failed_rows',
        'status',
        'error_log_path',
        'batch_id',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'failed_rows' => 'integer',
            'status' => ProductImportStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Completion percentage (0–100), driven by processed vs. total rows.
     */
    public function progress(): int
    {
        if ($this->total_rows <= 0) {
            return $this->status === ProductImportStatus::Completed ? 100 : 0;
        }

        return (int) min(100, floor(($this->processed_rows / $this->total_rows) * 100));
    }
}

<?php

namespace App\Enums;

enum ProductImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Stopping = 'stopping';
    case Stopped = 'stopped';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Filament badge colour for each status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Processing => 'warning',
            self::Stopping => 'warning',
            self::Stopped => 'gray',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }
}

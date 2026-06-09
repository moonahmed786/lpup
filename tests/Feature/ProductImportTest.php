<?php

use App\Enums\ProductImportStatus;
use App\Jobs\ProcessProductImport;
use App\Models\Product;
use App\Models\ProductImport;
use App\Services\ProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\FailOnTimeout;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * Write a CSV fixture to the local disk and return its relative path.
 *
 * @param  array<int, array{0: string, 1: string, 2: int|string, 3: string}>  $rows
 */
function writeImportCsv(array $rows, string $name = 'products.csv'): string
{
    $lines = ['name,sku,quantity,status'];
    foreach ($rows as $r) {
        $lines[] = implode(',', $r);
    }

    $path = "imports/{$name}";
    Storage::disk('local')->put($path, implode("\n", $lines)."\n");

    return $path;
}

/** Generate N valid rows. */
function validRows(int $count): array
{
    $rows = [];
    for ($i = 1; $i <= $count; $i++) {
        $rows[] = ["Product {$i}", "SKU-{$i}", $i, 'active'];
    }

    return $rows;
}

it('creates a pending record and queues the job', function () {
    Queue::fake();
    $path = writeImportCsv(validRows(3));

    $import = app(ProductImportService::class)->startImport($path, 'products.csv', null);

    expect($import->status)->toBe(ProductImportStatus::Pending)
        ->and($import->filename)->toBe('products.csv')
        ->and($import->path)->toBe($path);

    Queue::assertPushed(ProcessProductImport::class, fn ($job) => $job->importId === $import->id);
});

it('processes a file in chunks, updates progress, and upserts products', function () {
    // 2,500 rows exercises multiple 1,000-row chunks.
    $path = writeImportCsv(validRows(2500), 'bulk.csv');
    $import = ProductImport::create([
        'filename' => 'bulk.csv',
        'path' => $path,
        'status' => ProductImportStatus::Pending,
    ]);

    ProcessProductImport::dispatchSync($import->id);
    $import->refresh();

    expect(Product::count())->toBe(2500)
        ->and($import->status)->toBe(ProductImportStatus::Completed)
        ->and($import->processed_rows)->toBe(2500)
        ->and($import->failed_rows)->toBe(0)
        ->and($import->total_rows)->toBe(2500)
        ->and($import->completed_at)->not->toBeNull();
});

it('is idempotent — re-importing updates existing products instead of duplicating', function () {
    $first = writeImportCsv([
        ['Widget', 'SKU-1', 5, 'active'],
        ['Gadget', 'SKU-2', 8, 'draft'],
    ], 'first.csv');
    $importA = ProductImport::create(['filename' => 'first.csv', 'path' => $first, 'status' => ProductImportStatus::Pending]);
    ProcessProductImport::dispatchSync($importA->id);

    expect(Product::count())->toBe(2)
        ->and(Product::where('sku', 'SKU-1')->value('quantity'))->toBe(5);

    // Same SKUs, changed quantities.
    $second = writeImportCsv([
        ['Widget Renamed', 'SKU-1', 99, 'inactive'],
        ['Gadget', 'SKU-2', 8, 'draft'],
    ], 'second.csv');
    $importB = ProductImport::create(['filename' => 'second.csv', 'path' => $second, 'status' => ProductImportStatus::Pending]);
    ProcessProductImport::dispatchSync($importB->id);

    expect(Product::count())->toBe(2) // no duplicates
        ->and(Product::where('sku', 'SKU-1')->value('quantity'))->toBe(99) // updated
        ->and(Product::where('sku', 'SKU-1')->value('name'))->toBe('Widget Renamed');
});

it('counts invalid rows and writes them to a failures CSV without aborting', function () {
    $path = writeImportCsv([
        ['Valid', 'SKU-OK', 10, 'active'],
        ['Bad qty', 'SKU-NEG', -3, 'active'],     // negative quantity
        ['Bad status', 'SKU-BAD', 4, 'nope'],      // invalid status
        ['', 'SKU-NONAME', 1, 'active'],           // missing name
    ], 'mixed.csv');
    $import = ProductImport::create(['filename' => 'mixed.csv', 'path' => $path, 'status' => ProductImportStatus::Pending]);

    ProcessProductImport::dispatchSync($import->id);
    $import->refresh();

    expect(Product::count())->toBe(1)
        ->and($import->status)->toBe(ProductImportStatus::Completed)
        ->and($import->processed_rows)->toBe(4)
        ->and($import->failed_rows)->toBe(3)
        ->and($import->error_log_path)->not->toBeNull();

    expect(Storage::disk('local')->exists($import->error_log_path))->toBeTrue();
    $csv = Storage::disk('local')->get($import->error_log_path);
    expect($csv)->toContain('SKU-NEG')->toContain('SKU-BAD');
});

it('declares the Laravel 13 queue attributes on the job', function () {
    $ref = new ReflectionClass(ProcessProductImport::class);

    expect($ref->getAttributes(Tries::class))->not->toBeEmpty()
        ->and($ref->getAttributes(Backoff::class))->not->toBeEmpty()
        ->and($ref->getAttributes(Timeout::class))->not->toBeEmpty()
        ->and($ref->getAttributes(FailOnTimeout::class))->not->toBeEmpty();

    expect($ref->getAttributes(Tries::class)[0]->newInstance()->tries)->toBe(3);
    expect($ref->getAttributes(Backoff::class)[0]->newInstance()->backoff)->toBe([10, 30, 60]);
});

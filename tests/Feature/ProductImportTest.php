<?php

use App\Enums\ProductImportStatus;
use App\Enums\ProductStatus;
use App\Jobs\ProcessProductImport;
use App\Models\Product;
use App\Models\ProductImport;
use App\Services\ProductImportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\FailOnTimeout;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

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

it('accepts stock as a quantity alias and defaults a missing status', function () {
    Storage::disk('local')->put(
        'imports/stock-format.csv',
        implode("\n", [
            'name,sku,price,stock,description,category',
            'Premium Headphones 000001,SKU-000001,10.74,25,Generated product,Electronics',
        ])."\n",
    );

    $import = ProductImport::create([
        'filename' => 'stock-format.csv',
        'path' => 'imports/stock-format.csv',
        'status' => ProductImportStatus::Pending,
    ]);

    ProcessProductImport::dispatchSync($import->id);
    $import->refresh();

    expect($import->status)->toBe(ProductImportStatus::Completed)
        ->and($import->failed_rows)->toBe(0)
        ->and($import->error_log_path)->toBeNull()
        ->and(Product::where('sku', 'SKU-000001')->value('quantity'))->toBe(25)
        ->and(Product::where('sku', 'SKU-000001')->first()->status)->toBe(ProductStatus::Draft);
});

it('clears stale failure logs when an import is retried', function () {
    $path = writeImportCsv([
        ['Bad qty', 'SKU-NEG', -3, 'active'],
    ], 'retry.csv');

    $import = ProductImport::create([
        'filename' => 'retry.csv',
        'path' => $path,
        'status' => ProductImportStatus::Pending,
    ]);

    ProcessProductImport::dispatchSync($import->id);
    $import->refresh();

    expect($import->failed_rows)->toBe(1)
        ->and($import->error_log_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($import->error_log_path))->toBeTrue();

    Storage::disk('local')->put(
        $path,
        implode("\n", [
            'name,sku,quantity,status',
            'Good Product,SKU-GOOD,3,active',
        ])."\n",
    );

    ProcessProductImport::dispatchSync($import->id);
    $import->refresh();

    expect($import->failed_rows)->toBe(0)
        ->and($import->error_log_path)->toBeNull()
        ->and(Storage::disk('local')->exists("imports/failures/import_{$import->id}.csv"))->toBeFalse();
});

it('can stop a pending import before the queued job processes it', function () {
    $path = writeImportCsv(validRows(3), 'stoppable.csv');
    $import = ProductImport::create([
        'filename' => 'stoppable.csv',
        'path' => $path,
        'status' => ProductImportStatus::Pending,
    ]);

    app(ProductImportService::class)->requestStop($import);

    ProcessProductImport::dispatchSync($import->id);
    $import->refresh();

    expect($import->status)->toBe(ProductImportStatus::Stopped)
        ->and($import->stop_requested_at)->not->toBeNull()
        ->and($import->completed_at)->not->toBeNull()
        ->and(Product::count())->toBe(0);
});

it('can start a stopped import again', function () {
    Queue::fake();

    $path = writeImportCsv(validRows(2), 'restartable.csv');
    $import = ProductImport::create([
        'filename' => 'restartable.csv',
        'path' => $path,
        'status' => ProductImportStatus::Stopped,
        'stop_requested_at' => now(),
        'completed_at' => now(),
    ]);

    app(ProductImportService::class)->startExisting($import);
    $import->refresh();

    expect($import->status)->toBe(ProductImportStatus::Pending)
        ->and($import->stop_requested_at)->toBeNull()
        ->and($import->completed_at)->toBeNull();

    Queue::assertPushed(ProcessProductImport::class, fn ($job) => $job->importId === $import->id);
});

it('ignores stale queued jobs after a stopped import is started again', function () {
    Queue::fake();

    $path = writeImportCsv(validRows(2), 'stale-job.csv');
    $import = app(ProductImportService::class)->startImport($path, 'stale-job.csv');
    $oldBatchId = $import->batch_id;

    app(ProductImportService::class)->requestStop($import);
    app(ProductImportService::class)->startExisting($import->refresh());
    $newBatchId = $import->refresh()->batch_id;

    (new ProcessProductImport($import->id, $oldBatchId))->handle();
    $import->refresh();

    expect(Product::count())->toBe(0)
        ->and($import->status)->toBe(ProductImportStatus::Pending)
        ->and($newBatchId)->not->toBe($oldBatchId);

    (new ProcessProductImport($import->id, $newBatchId))->handle();

    expect(Product::count())->toBe(2)
        ->and($import->refresh()->status)->toBe(ProductImportStatus::Completed);
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

it('keeps product imports restricted to super admins', function () {
    $this->seed(RolePermissionSeeder::class);

    expect(Role::findByName('SuperAdmin', 'api')->hasPermissionTo('imports.upload', 'api'))->toBeTrue()
        ->and(Role::findByName('SuperAdmin', 'api')->hasPermissionTo('imports.start', 'api'))->toBeTrue()
        ->and(Role::findByName('SuperAdmin', 'api')->hasPermissionTo('imports.stop', 'api'))->toBeTrue()
        ->and(Role::findByName('Admin', 'api')->hasPermissionTo('imports.viewAny', 'api'))->toBeTrue()
        ->and(Role::findByName('Admin', 'api')->hasPermissionTo('imports.upload', 'api'))->toBeFalse()
        ->and(Role::findByName('Admin', 'api')->hasPermissionTo('imports.start', 'api'))->toBeFalse()
        ->and(Role::findByName('Admin', 'api')->hasPermissionTo('imports.stop', 'api'))->toBeFalse()
        ->and(Role::findByName('Admin', 'api')->hasPermissionTo('imports.downloadFailures', 'api'))->toBeFalse()
        ->and(Role::findByName('User', 'api')->hasPermissionTo('imports.upload', 'api'))->toBeFalse();
});

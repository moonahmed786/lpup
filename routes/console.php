<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\ClientRepository;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('lpup:install-passport', function (ClientRepository $clients): int {
    $provider = 'users';

    Artisan::call('passport:keys', [
        '--force' => true,
    ]);

    $this->output->write(Artisan::output());

    try {
        $client = $clients->personalAccessClient($provider);
        $this->info("Personal access client already exists: {$client->name} ({$client->id})");

        return 0;
    } catch (RuntimeException) {
        $client = $clients->createPersonalAccessGrantClient('LPUP Personal Access Client', $provider);
        $this->info("Created personal access client: {$client->name} ({$client->id})");

        return 0;
    }
})->purpose('Install Passport keys and the LPUP personal access client');

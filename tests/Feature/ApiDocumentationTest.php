<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serves swagger ui', function () {
    $this->get('/docs/api')
        ->assertOk()
        ->assertSee('SwaggerUIBundle', false);
});

it('serves the openapi specification', function () {
    $this->getJson('/docs/openapi.json')
        ->assertOk()
        ->assertJsonPath('openapi', '3.0.3')
        ->assertJsonPath('components.securitySchemes.cookieAuth.in', 'cookie')
        ->assertJsonStructure([
            'paths' => [
                '/api/login',
                '/api/products',
                '/api/products/{product}',
            ],
        ]);
});

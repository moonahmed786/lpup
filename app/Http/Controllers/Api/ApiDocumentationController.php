<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ApiDocumentationController extends Controller
{
    public function index(): View
    {
        return view('docs.api');
    }

    public function openApi(): JsonResponse
    {
        return response()->json([
            'openapi' => '3.0.3',
            'info' => [
                'title' => config('app.name').' API',
                'version' => '1.0.0',
            ],
            'servers' => [
                ['url' => url('/')],
            ],
            'components' => [
                'securitySchemes' => [
                    'cookieAuth' => [
                        'type' => 'apiKey',
                        'in' => 'cookie',
                        'name' => config('api.auth_cookie.name'),
                    ],
                ],
                'schemas' => $this->schemas(),
            ],
            'paths' => $this->paths(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function schemas(): array
    {
        return [
            'LoginRequest' => [
                'type' => 'object',
                'required' => ['email', 'password'],
                'properties' => [
                    'email' => ['type' => 'string', 'format' => 'email'],
                    'password' => ['type' => 'string', 'format' => 'password'],
                ],
            ],
            'ProductRequest' => [
                'type' => 'object',
                'required' => ['name', 'sku', 'quantity', 'status'],
                'properties' => [
                    'name' => ['type' => 'string', 'maxLength' => 255],
                    'sku' => ['type' => 'string', 'maxLength' => 255],
                    'quantity' => ['type' => 'integer', 'minimum' => 0],
                    'price' => ['type' => 'number', 'nullable' => true, 'minimum' => 0],
                    'description' => ['type' => 'string', 'nullable' => true, 'maxLength' => 5000],
                    'status' => ['type' => 'string', 'enum' => ['draft', 'active', 'inactive']],
                ],
            ],
            'ProductResource' => [
                'type' => 'object',
                'properties' => [
                    'data' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string', 'example' => 'products'],
                            'id' => ['type' => 'string', 'example' => '1'],
                            'attributes' => ['$ref' => '#/components/schemas/ProductRequest'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paths(): array
    {
        return [
            '/api/login' => [
                'post' => [
                    'summary' => 'Authenticate and set the HTTP-only API cookie',
                    'requestBody' => $this->jsonBody('LoginRequest'),
                    'responses' => [
                        '200' => ['description' => 'Authenticated'],
                        '422' => ['description' => 'Validation error'],
                    ],
                ],
            ],
            '/api/logout' => [
                'post' => [
                    'summary' => 'Revoke the current API token and clear the cookie',
                    'security' => [['cookieAuth' => []]],
                    'responses' => [
                        '204' => ['description' => 'Logged out'],
                        '401' => ['description' => 'Unauthenticated'],
                    ],
                ],
            ],
            '/api/me' => [
                'get' => [
                    'summary' => 'Return the authenticated user',
                    'security' => [['cookieAuth' => []]],
                    'responses' => [
                        '200' => ['description' => 'Authenticated user'],
                        '401' => ['description' => 'Unauthenticated'],
                    ],
                ],
            ],
            '/api/products' => [
                'get' => [
                    'summary' => 'List products',
                    'security' => [['cookieAuth' => []]],
                    'parameters' => [
                        ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1]],
                        ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]],
                    ],
                    'responses' => ['200' => ['description' => 'Product list']],
                ],
                'post' => [
                    'summary' => 'Create a product',
                    'security' => [['cookieAuth' => []]],
                    'requestBody' => $this->jsonBody('ProductRequest'),
                    'responses' => ['201' => ['description' => 'Created'], '403' => ['description' => 'Forbidden']],
                ],
            ],
            '/api/products/{product}' => [
                'get' => [
                    'summary' => 'Show a product',
                    'security' => [['cookieAuth' => []]],
                    'parameters' => [$this->productPathParameter()],
                    'responses' => ['200' => ['description' => 'Product']],
                ],
                'patch' => [
                    'summary' => 'Update a product',
                    'security' => [['cookieAuth' => []]],
                    'parameters' => [$this->productPathParameter()],
                    'requestBody' => $this->jsonBody('ProductRequest'),
                    'responses' => ['200' => ['description' => 'Updated'], '403' => ['description' => 'Forbidden']],
                ],
                'delete' => [
                    'summary' => 'Delete a product',
                    'security' => [['cookieAuth' => []]],
                    'parameters' => [$this->productPathParameter()],
                    'responses' => ['204' => ['description' => 'Deleted'], '403' => ['description' => 'Forbidden']],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonBody(string $schema): array
    {
        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/'.$schema],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productPathParameter(): array
    {
        return [
            'name' => 'product',
            'in' => 'path',
            'required' => true,
            'schema' => ['type' => 'integer'],
        ];
    }
}

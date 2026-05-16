<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireApiKey;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiKeyMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cas.api_key' => 'test-key']);

        Route::middleware(RequireApiKey::class)->get('/middleware-api-key-test', function () {
            return response()->json(['ok' => true]);
        });
    }

    public function test_request_without_api_key_is_rejected(): void
    {
        $this->getJson('/middleware-api-key-test')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Invalid or missing API key.']);
    }

    public function test_request_with_api_key_is_allowed(): void
    {
        $this->withHeader('X-API-Key', 'test-key')
            ->getJson('/middleware-api-key-test')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiErrorHandlingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gateway.api_key' => 'test-apws-key',
            'services.apws.api_key' => 'test-apws-key',
            'services.apws.gateway_api_key' => 'test-apws-key',
        ]);
    }

    public function testRouteNotFoundReturnsStandardJson(): void
    {
        $response = $this->getJson('/api/v1/route-inexistente');

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Route not found.',
            'error' => 'The requested endpoint does not exist.',
        ]);
    }

    public function testMethodNotAllowedReturnsStandardJson(): void
    {
        $response = $this->getJson('/api/v1/sync/run');

        $response->assertStatus(405);
        $response->assertJson([
            'success' => false,
            'message' => 'Method not allowed.',
            'error' => 'The HTTP method used is not supported for this endpoint.',
        ]);
    }

    public function testValidationErrorReturnsStandardJson(): void
    {
        $response = $this
            ->withHeaders(['X-API-KEY' => 'test-apws-key'])
            ->postJson('/api/v1/sync/run', [
                't1' => 'invalid',
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Validation failed.',
        ]);
        $response->assertJsonStructure([
            'errors' => ['t1'],
        ]);
    }
}

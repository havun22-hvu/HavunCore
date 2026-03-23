<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityAuditTest extends TestCase
{
    public function test_app_env_is_testing(): void
    {
        $this->assertEquals('testing', config('app.env'));
    }

    public function test_api_endpoints_do_not_expose_stack_traces(): void
    {
        $response = $this->getJson('/api/this-route-does-not-exist');

        $response->assertStatus(404);
        $content = $response->getContent();
        $this->assertStringNotContainsString('vendor/laravel', $content);
        $this->assertStringNotContainsString('Stack trace', $content);
    }
}

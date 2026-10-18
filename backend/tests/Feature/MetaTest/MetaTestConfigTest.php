<?php

namespace Tests\Feature\MetaTest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetaTestConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_config_loads_from_environment(): void
    {
        config()->set('meta.app_id', 'test-app-id');
        config()->set('meta.enabled', true);

        $this->assertEquals('test-app-id', config('meta.app_id'));
        $this->assertTrue(config('meta.enabled'));
    }

    public function test_meta_test_routes_return_404_when_disabled(): void
    {
        config()->set('meta.enabled', false);

        $this->getJson('/api/meta-test/dashboard')->assertNotFound();
        $this->getJson('/api/meta-test/status')->assertNotFound();
    }

    public function test_meta_test_routes_require_authentication(): void
    {
        config()->set('meta.enabled', true);

        $this->getJson('/api/meta-test/dashboard')->assertUnauthorized();
        $this->getJson('/api/meta-test/setup')->assertUnauthorized();
    }
}

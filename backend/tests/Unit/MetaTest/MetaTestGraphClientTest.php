<?php

namespace Tests\Unit\MetaTest;

use App\Modules\MetaTest\Exceptions\MetaTestApiException;
use App\Modules\MetaTest\Services\MetaTestGraphClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaTestGraphClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_masks_secrets_in_api_log(): void
    {
        config()->set('meta.access_token', 'super-secret-access-token-12345');

        Http::fake([
            '*' => Http::response(['id' => 'me'], 200),
        ]);

        $client = new MetaTestGraphClient();

        try {
            $client->request('GET', '/me', ['access_token' => 'super-secret-access-token-12345']);
        } catch (\Throwable $e) {
            // ignore
        }

        $this->assertDatabaseHas('meta_test_api_logs', [
            'endpoint' => config('meta.graph_url') . '/' . config('meta.api_version') . '/me',
        ]);

        $log = \App\Modules\MetaTest\Models\MetaTestApiLog::first();
        $request = json_encode($log->request_payload);

        $expectedMask = 'supe' . str_repeat('*', max(0, strlen('super-secret-access-token-12345') - 8)) . '2345';

        $this->assertStringNotContainsString('super-secret-access-token-12345', $request);
        $this->assertStringContainsString($expectedMask, $request);
    }

    public function test_throws_meta_api_exception_on_error(): void
    {
        config()->set('meta.access_token', 'test-token');

        Http::fake([
            '*' => Http::response([
                'error' => [
                    'message' => 'Invalid token',
                    'code' => 190,
                ],
            ], 400),
        ]);

        $client = new MetaTestGraphClient();

        $this->expectException(MetaTestApiException::class);

        $client->request('GET', '/me');
    }

    public function test_debug_token_requires_app_credentials(): void
    {
        config()->set('meta.app_id', null);
        config()->set('meta.app_secret', null);

        $client = new MetaTestGraphClient();

        $this->expectException(MetaTestApiException::class);

        $client->debugToken('any-token');
    }
}

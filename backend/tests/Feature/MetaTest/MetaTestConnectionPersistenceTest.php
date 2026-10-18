<?php

namespace Tests\Feature\MetaTest;

use App\Models\User;
use App\Modules\MetaTest\Models\MetaTestConnection;
use App\Modules\MetaTest\Models\MetaTestPhoneNumber;
use App\Modules\MetaTest\Models\MetaTestWaba;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetaTestConnectionPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_waba_and_phone_number_can_be_persisted(): void
    {
        $user = User::factory()->create();

        $connection = MetaTestConnection::create([
            'user_id' => $user->id,
            'status' => MetaTestConnection::STATUS_CONNECTED,
            'access_token' => 'test-token',
        ]);

        $waba = MetaTestWaba::create([
            'user_id' => $user->id,
            'connection_id' => $connection->id,
            'meta_waba_id' => '123456789',
            'name' => 'Test WABA',
            'status' => 'ACTIVE',
        ]);

        $phone = MetaTestPhoneNumber::create([
            'user_id' => $user->id,
            'waba_id' => $waba->id,
            'meta_phone_number_id' => '987654321',
            'phone_number' => '+201012345678',
            'status' => 'CONNECTED',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('meta_test_wabas', ['meta_waba_id' => '123456789']);
        $this->assertDatabaseHas('meta_test_phone_numbers', ['meta_phone_number_id' => '987654321']);

        $this->assertEquals(1, $connection->wabas()->count());
        $this->assertEquals(1, $waba->phoneNumbers()->count());
        $this->assertEquals('Test WABA', $phone->waba->name);
    }
}

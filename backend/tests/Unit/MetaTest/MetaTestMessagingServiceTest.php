<?php

namespace Tests\Unit\MetaTest;

use App\Models\User;
use App\Modules\MetaTest\Models\MetaTestConnection;
use App\Modules\MetaTest\Models\MetaTestMessage;
use App\Modules\MetaTest\Models\MetaTestPhoneNumber;
use App\Modules\MetaTest\Models\MetaTestTemplate;
use App\Modules\MetaTest\Models\MetaTestWaba;
use App\Modules\MetaTest\Services\MetaTestMessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaTestMessagingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_store_incoming_message(): void
    {
        $user = User::factory()->create();

        $connection = MetaTestConnection::create([
            'user_id' => $user->id,
            'status' => 'connected',
        ]);

        $waba = MetaTestWaba::create([
            'user_id' => $user->id,
            'connection_id' => $connection->id,
            'meta_waba_id' => 'waba-123',
        ]);

        $phone = MetaTestPhoneNumber::create([
            'user_id' => $user->id,
            'waba_id' => $waba->id,
            'meta_phone_number_id' => 'phone-123',
            'phone_number' => '+1234',
        ]);

        $service = new MetaTestMessagingService($user->id);

        $messageData = [
            'id' => 'wamid.incoming',
            'from' => '201012345678',
            'type' => 'text',
            'text' => ['body' => 'Hello'],
            'phone_number_id' => 'phone-123',
            'metadata' => [
                'phone_number_id' => 'phone-123',
                'display_phone_number' => '+1234',
            ],
        ];

        $result = $service->storeIncomingMessage($user->id, $messageData, 'waba-123');

        $this->assertNotNull($result);
        $this->assertEquals(MetaTestMessage::DIRECTION_INCOMING, $result->direction);
        $this->assertEquals('Hello', $result->body);
        $this->assertDatabaseHas('meta_test_messages', ['meta_message_id' => 'wamid.incoming']);
    }

    public function test_update_message_status(): void
    {
        $user = User::factory()->create();

        $connection = MetaTestConnection::create([
            'user_id' => $user->id,
            'status' => 'connected',
        ]);

        $waba = MetaTestWaba::create([
            'user_id' => $user->id,
            'connection_id' => $connection->id,
            'meta_waba_id' => 'waba-123',
        ]);

        $phone = MetaTestPhoneNumber::create([
            'user_id' => $user->id,
            'waba_id' => $waba->id,
            'meta_phone_number_id' => 'phone-123',
            'phone_number' => '+1234',
        ]);

        $message = MetaTestMessage::create([
            'user_id' => $user->id,
            'phone_number_id' => $phone->id,
            'meta_message_id' => 'wamid.abc',
            'direction' => MetaTestMessage::DIRECTION_OUTGOING,
            'status' => MetaTestMessage::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $service = new MetaTestMessagingService($user->id);

        $updated = $service->updateMessageStatus('wamid.abc', MetaTestMessage::STATUS_DELIVERED);

        $this->assertNotNull($updated);
        $this->assertEquals(MetaTestMessage::STATUS_DELIVERED, $updated->status);
        $this->assertNotNull($updated->delivered_at);
    }

    public function test_send_text_message_validates_request(): void
    {
        $user = User::factory()->create();

        $connection = MetaTestConnection::create([
            'user_id' => $user->id,
            'status' => 'connected',
            'access_token' => 'token',
        ]);

        $waba = MetaTestWaba::create([
            'user_id' => $user->id,
            'connection_id' => $connection->id,
            'meta_waba_id' => 'waba-123',
        ]);

        $phone = MetaTestPhoneNumber::create([
            'user_id' => $user->id,
            'waba_id' => $waba->id,
            'meta_phone_number_id' => 'phone-123',
            'phone_number' => '+1234',
            'is_active' => true,
        ]);

        Http::fake([
            '*' => Http::response(['messages' => [['id' => 'wamid.sent']]], 200),
        ]);

        $service = new MetaTestMessagingService($user->id);

        $message = $service->sendTextMessage($phone, '+201012345678', 'Test message');

        $this->assertEquals('wamid.sent', $message->meta_message_id);
        $this->assertDatabaseHas('meta_test_messages', ['meta_message_id' => 'wamid.sent']);
    }
}

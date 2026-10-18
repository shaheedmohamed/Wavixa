<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Realtime feed for the CRM — one private channel per account owner carries
 * new messages, status ticks, typing indicators and device state changes so
 * the SPA updates instantly instead of polling.
 */
class CrmRealtimeEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param int    $ownerId  account owner the channel is scoped to
     * @param string $type     message.new | message.status | device.status | typing
     * @param array  $payload  event-specific data (message row, status, ...)
     */
    public function __construct(
        public int $ownerId,
        public string $type,
        public array $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('crm.' . $this->ownerId)];
    }

    public function broadcastAs(): string
    {
        return 'crm.event';
    }

    public function broadcastWith(): array
    {
        return ['type' => $this->type, 'data' => $this->payload];
    }
}

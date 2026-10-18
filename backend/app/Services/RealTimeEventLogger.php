<?php

namespace App\Services;

use App\Models\RealTimeEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RealTimeEventLogger
{
    public static function log(
        string $type,
        string $title,
        ?int $userId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        ?array $metadata = null,
    ): void {
        $request = request();

        RealTimeEvent::create([
            'type' => $type,
            'user_id' => $userId ?? Auth::id(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'title' => $title,
            'description' => $description,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}

<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    /**
     * Log an admin activity.
     */
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?int $userId = null,
        ?string $description = null,
        array $metadata = []
    ): ActivityLog {
        $request = request();

        return ActivityLog::create([
            'admin_id' => Auth::id(),
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'metadata' => self::redact($metadata),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /**
     * Recursively redact sensitive keys from metadata arrays.
     */
    protected static function redact(array $data): array
    {
        $sensitive = ['api_key', 'key', 'secret', 'token', 'password', 'auth', 'certificate', 'credential', 'private_key'];

        foreach ($data as $k => $v) {
            $lower = is_string($k) ? strtolower($k) : '';
            foreach ($sensitive as $pattern) {
                if (is_string($lower) && str_contains($lower, $pattern)) {
                    $data[$k] = '***';
                    break;
                }
            }

            if (is_array($v)) {
                $data[$k] = self::redact($v);
            }
        }

        return $data;
    }
}

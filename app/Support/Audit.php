<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Tiny helper for writing append-only audit trail entries.
 *
 * Usage:
 *   Audit::record('updated', $employee, $oldValues, $newValues);
 */
class Audit
{
    public static function record(
        string $action,
        ?Model $model = null,
        array $oldValues = [],
        array $newValues = [],
        ?int $userId = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->getKey(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => Str::limit((string) request()->userAgent(), 255),
        ]);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit trail (COA-ready). Rows are only ever inserted by the
 * Audit helper / model observers — never updated or deleted in normal flow.
 */
class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'model_type', 'model_id',
        'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getModelLabelAttribute(): string
    {
        return class_basename($this->model_type ?? 'System');
    }

    public function getActionLabelAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->action));
    }
}

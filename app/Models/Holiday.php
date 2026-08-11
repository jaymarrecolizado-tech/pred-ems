<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'date',
        'type',
        'is_repeating',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'is_repeating' => 'boolean',
    ];

    public const TYPES = [
        'regular_holiday' => 'Regular holiday',
        'special_nonworking' => 'Special (non-working) day',
        'work_suspension' => 'Work suspension',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    /**
     * Does this holiday occur on the given date? Repeating holidays match by
     * month/day so a seeded Dec 25 applies to every year.
     */
    public function occursOn(CarbonInterface $date): bool
    {
        if ($this->is_repeating) {
            return $this->date->month === $date->month && $this->date->day === $date->day;
        }

        return $this->date->toDateString() === $date->toDateString();
    }
}

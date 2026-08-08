<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'days',
        'starts_on',
        'ends_on',
        'is_active',
        'revert_schedule_id',
        'created_by',
    ];

    protected $casts = [
        'days' => 'array',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_active' => 'boolean',
    ];

    public function revertSchedule(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revert_schedule_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The schedule in effect on a given date (latest starts_on wins).
     */
    public static function effectiveOn(CarbonInterface $date): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_on')->orWhere('starts_on', '<=', $date->toDateString()))
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $date->toDateString()))
            ->orderByRaw("COALESCE(starts_on, '1900-01-01') DESC")
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Day definition for a given day-of-week (1 = Monday … 7 = Sunday).
     */
    public function dayConfig(int $dayOfWeekIso): array
    {
        $days = is_array($this->days) ? $this->days : [];

        return $days[(string) $dayOfWeekIso]
            ?? ['work' => false];
    }

    public function isWorkingDay(int $dayOfWeekIso): bool
    {
        return (bool) ($this->dayConfig($dayOfWeekIso)['work'] ?? false);
    }

    /**
     * Human-readable summary, e.g. "Mon–Thu 07:00–12:00 / 13:00–18:00 · Fri–Sun rest".
     */
    public function summary(): string
    {
        $days = is_array($this->days) ? $this->days : [];
        $isoNames = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

        $parts = [];
        $working = [];
        $rest = [];

        for ($i = 1; $i <= 7; $i++) {
            $cfg = $days[(string) $i] ?? ['work' => false];
            $label = $isoNames[$i];

            if (! empty($cfg['work'])) {
                $working[] = $label . ' ' . ($cfg['am_start'] ?? '—') . '–' . ($cfg['am_end'] ?? '—')
                    . ' / ' . ($cfg['pm_start'] ?? '—') . '–' . ($cfg['pm_end'] ?? '—');
            } else {
                $rest[] = $label;
            }
        }

        if ($working) {
            $parts[] = implode(', ', $working);
        }
        if ($rest) {
            $parts[] = implode('–', $rest) . ' rest';
        }

        return implode(' · ', $parts) ?: 'No working days configured';
    }
}

<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Config-driven statutory contribution & tax rules (GSIS, PhilHealth,
 * PAG-IBIG, BIR). Rates are data rows with effective dating, so annual
 * updates are data changes — never code changes.
 */
class ContributionRate extends Model
{
    public const AGENCIES = ['GSIS', 'PHILHEALTH', 'PAGIBIG', 'BIR'];

    protected $fillable = [
        'agency', 'name', 'config', 'effective_from', 'effective_to', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The active rate set for an agency on a given date (latest start wins,
     * open-ended rows treated as beginning of time).
     */
    public static function effectiveOn(string $agency, CarbonInterface $date): ?self
    {
        return static::query()
            ->where('agency', $agency)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $date->toDateString()))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date->toDateString()))
            ->orderByRaw('COALESCE(effective_from, \'1900-01-01\') DESC')
            ->orderByDesc('id')
            ->first();
    }
}

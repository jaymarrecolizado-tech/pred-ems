<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceCheckpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'address', 'latitude', 'longitude', 'radius_meters',
        'is_active', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'radius_meters' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Haversine distance (meters) from this checkpoint to a GPS position.
     */
    public function distanceTo(float $lat, float $lng): float
    {
        $earth = 6371000;
        $dLat = deg2rad($lat - (float) $this->latitude);
        $dLng = deg2rad($lng - (float) $this->longitude);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat)) * cos(deg2rad((float) $this->latitude)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

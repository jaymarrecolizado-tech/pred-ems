<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'salary_grade', 'level', 'is_plantilla',
        'plantilla_item_no', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_plantilla' => 'boolean', 'is_active' => 'boolean'];
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}

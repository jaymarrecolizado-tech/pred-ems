<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'document_type', 'reference_no', 'file_path',
        'remarks', 'generated_by', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function getDocumentTypeLabelAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->document_type));
    }
}

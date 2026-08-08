<?php

namespace App\Support;

use App\Models\Document;
use App\Models\Position;

/**
 * Shared helpers for issued documents (Service Record, Certificate of
 * Employment, future certifications): sequential reference numbers and the
 * signatory blocks resolved from the actual plantilla.
 */
class DocumentIssuer
{
    /**
     * Next sequential reference number for a document prefix.
     * e.g. nextReferenceNo('SR')  → SR-2026-0001
     *      nextReferenceNo('COE') → COE-2026-0001
     */
    public static function nextReferenceNo(string $prefix): string
    {
        $prefix = strtoupper($prefix);
        $yearPrefix = $prefix . '-' . now()->format('Y') . '-';
        $last = Document::where('reference_no', 'like', $yearPrefix . '%')
            ->orderByDesc('reference_no')
            ->value('reference_no');

        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $yearPrefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * "Prepared by" — the HRMO / administrative officer handling HR, falling
     * back to the signed-in user.
     */
    public static function preparer(): array
    {
        $position = Position::where('title', 'like', '%HRMO%')
            ->orWhere('title', 'like', 'Administrative Officer II%')
            ->first();

        $employee = $position?->employees()->first() ?? auth()->user()->employee;

        return $employee
            ? ['name' => $employee->full_name, 'title' => $employee->position?->title ?? 'HRMO']
            : ['name' => auth()->user()->name, 'title' => auth()->user()->roles()->first()?->name ?? 'HRMO'];
    }

    /**
     * "Certified true and correct" — the Chief Administrative Officer.
     */
    public static function certifier(): array
    {
        $position = Position::where('title', 'like', '%Chief Administrative Officer%')->first();
        $employee = $position?->employees()->first();

        return $employee
            ? ['name' => $employee->full_name, 'title' => 'Chief Administrative Officer']
            : ['name' => 'Chief Administrative Officer', 'title' => 'Chief Administrative Officer'];
    }
}

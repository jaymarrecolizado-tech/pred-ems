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

    /**
     * Convert a peso amount to the official English words used on
     * certifications, e.g. 40736.40 → "Forty Thousand Seven Hundred
     * Thirty-Six Pesos and Forty Centavos".
     */
    public static function amountInWords(float $amount): string
    {
        $amount = max(0, round((float) $amount, 2));
        $pesos = (int) floor($amount);
        $centavos = (int) round(($amount - $pesos) * 100);

        if ($centavos === 100) {
            $pesos++;
            $centavos = 0;
        }

        $result = self::numberToWords($pesos) . ($pesos === 1 ? ' Peso' : ' Pesos');

        if ($centavos > 0) {
            $result .= ' and ' . self::numberToWords($centavos) . ($centavos === 1 ? ' Centavo' : ' Centavos');
        } else {
            $result .= ' Only';
        }

        return $result;
    }

    /**
     * Ordinal form of a day number: 1 → 1st, 2 → 2nd, 3 → 3rd, 20 → 20th.
     */
    public static function ordinalSuffix(int $number): string
    {
        if (in_array($number % 100, [11, 12, 13], true)) {
            return $number . 'th';
        }

        return $number . match ($number % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }

    /**
     * Whole number (0 – 999,999,999) as English words.
     */
    private static function numberToWords(int $number): string
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        if ($number === 0) {
            return 'Zero';
        }

        if ($number < 20) {
            return $ones[$number];
        }

        if ($number < 100) {
            return $tens[intdiv($number, 10)] . ($number % 10 ? '-' . $ones[$number % 10] : '');
        }

        if ($number < 1000) {
            return $ones[intdiv($number, 100)] . ' Hundred' . ($number % 100 ? ' ' . self::numberToWords($number % 100) : '');
        }

        if ($number < 1000000) {
            return self::numberToWords(intdiv($number, 1000)) . ' Thousand' . ($number % 1000 ? ' ' . self::numberToWords($number % 1000) : '');
        }

        if ($number < 1000000000) {
            return self::numberToWords(intdiv($number, 1000000)) . ' Million' . ($number % 1000000 ? ' ' . self::numberToWords($number % 1000000) : '');
        }

        return (string) $number;
    }
}

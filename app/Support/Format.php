<?php

namespace App\Support;

/**
 * Tiny display helpers shared by actions and controllers.
 */
class Format
{
    /**
     * Format a day count as a whole number when integral, 2 decimals otherwise.
     */
    public static function days(float $value): string
    {
        return number_format($value, $value == (int) $value ? 0 : 2);
    }
}

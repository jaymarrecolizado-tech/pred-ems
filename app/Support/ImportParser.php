<?php

namespace App\Support;

use DateTime;

/**
 * Value parsers shared by the bulk-import actions (employee roster +
 * attendance punch files). ImportReader handles the file formats; this class
 * normalizes the date/time strings HR actually types into spreadsheets.
 */
class ImportParser
{
    /**
     * Normalize a human-typed date to YYYY-MM-DD, or null when unparseable.
     */
    public static function parseDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d', 'F j, Y'] as $format) {
            $date = DateTime::createFromFormat('!'.$format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Normalize a human-typed time to H:i:s, or null when unparseable.
     */
    public static function parseTime(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['H:i:s', 'H:i', 'h:i A', 'g:i A', 'h:iA'] as $format) {
            $date = DateTime::createFromFormat('!'.$format, $value);
            if ($date) {
                return $date->format('H:i:s');
            }
        }

        return null;
    }
}

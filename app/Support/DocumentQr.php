<?php

namespace App\Support;

use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * QR authenticity codes for official documents.
 *
 * Every issued PDF (COE, Service Record, Leave Balances, No Pending Case,
 * DTR) carries a QR code that resolves to the public verification page
 * (/verify/{reference}). Scanning it proves the document was issued by
 * DICT RO2 with a ledger-recorded reference number.
 *
 * Rendered with chillerlan/php-qrcode's GD PNG output (ext-gd ships with
 * XAMPP by default; the imagick extension is not required).
 */
class DocumentQr
{
    /**
     * Base64 PNG data URI for the QR code encoding the verification URL.
     * dompdf-safe: rendered as an <img src="data:image/png;base64,...">.
     */
    public static function dataUri(string $referenceNo): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'scale' => 6,
            'margin' => 2,
        ]);

        return (new QRCode($options))->render(url('/verify/'.rawurlencode($referenceNo)));
    }
}

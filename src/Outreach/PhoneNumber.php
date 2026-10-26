<?php

declare(strict_types=1);

namespace Prospec\Outreach;

/**
 * Brazilian phone number helpers for WhatsApp outreach.
 */
final class PhoneNumber
{
    /**
     * Returns the national number usable on WhatsApp: 11 digits (mobile) or 10 digits
     * (landline, since many businesses run WhatsApp Business on a landline).
     * Rejects toll-free/premium numbers (0800, 0300, 0500, 0900) and numbers without a valid area code.
     */
    public static function toWhatsApp(string $phone): ?string
    {
        $digits = (string)preg_replace('/\D/', '', $phone);
        if (preg_match('/^0?(800|300|500|900)/', $digits)) {
            return null;
        }
        $digits = ltrim($digits, '0');
        if (strlen($digits) > 11 && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }
        if (!preg_match('/^[1-9][1-9]/', $digits)) { // area code 11 to 99
            return null;
        }
        if (strlen($digits) === 11 && $digits[2] === '9') {
            return $digits;
        }
        if (strlen($digits) === 10 && $digits[2] >= '2' && $digits[2] <= '5') {
            return $digits;
        }
        return null;
    }

    /**
     * Formats a national number returned by toWhatsApp() as "+55 16 99104-8833" or "+55 16 3610-1234".
     */
    public static function format(string $national): string
    {
        $area = substr($national, 0, 2);
        $local = substr($national, 2);
        $split = strlen($local) === 9 ? 5 : 4;
        return '+55 ' . $area . ' ' . substr($local, 0, $split) . '-' . substr($local, $split);
    }

    public static function isLandline(string $national): bool
    {
        return strlen($national) === 10;
    }
}

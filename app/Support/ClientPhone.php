<?php

namespace App\Support;

class ClientPhone
{
    public static function toWhatsappId(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '221'.substr($digits, 1);
        }
        if (strlen($digits) === 9 && str_starts_with($digits, '7')) {
            $digits = '221'.$digits;
        }
        if (strlen($digits) < 8) {
            return null;
        }

        return $digits;
    }
}

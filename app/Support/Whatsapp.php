<?php

namespace App\Support;

/**
 * Click-to-chat links (wa.me). No API, no cost and no template approval: the
 * link opens WhatsApp with the message prefilled and a person taps send.
 */
class Whatsapp
{
    /**
     * Returns the number in international digits (e.g. 573001234567) when it
     * is a Colombian mobile, or null otherwise. Landlines and foreign
     * numbers are not supported in this MVP.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return match (true) {
            strlen($digits) === 10 && $digits[0] === '3' => '57'.$digits,
            strlen($digits) === 12 && str_starts_with($digits, '573') => $digits,
            default => null,
        };
    }

    public static function link(?string $phone, string $message): ?string
    {
        $number = self::normalizePhone($phone);

        return $number === null ? null : "https://wa.me/{$number}?text=".rawurlencode($message);
    }

    public static function vehicleReadyMessage(?string $customerName, string $plate, string $businessName): string
    {
        $greeting = $customerName ? "Hola {$customerName}," : 'Hola,';

        return "{$greeting} tu vehículo con placa {$plate} ya está listo en {$businessName}. ¡Te esperamos!";
    }
}

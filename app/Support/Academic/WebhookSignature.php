<?php

namespace App\Support\Academic;

use Carbon\Carbon;
use Throwable;

/**
 * Tanda tangan webhook Academic: `sha256=` + HMAC-SHA256 dari `timestamp.rawBody` dengan rahasia bersama.
 * Timestamp (detik Unix atau ISO 8601) harus dalam toleransi agar rekaman permintaan lama tidak bisa diputar ulang.
 */
class WebhookSignature
{
    public static function sign(string $timestamp, string $rawBody, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
    }

    public static function verify(string $rawBody, ?string $timestamp, ?string $signature): bool
    {
        $secret = (string) config('academic.webhook_secret');

        if ($secret === '' || $timestamp === null || $timestamp === '' || $signature === null || $signature === '') {
            return false;
        }

        if (! hash_equals(self::sign($timestamp, $rawBody, $secret), $signature)) {
            return false;
        }

        $sentAt = self::parseTimestamp($timestamp);

        return $sentAt !== null && abs(now()->timestamp - $sentAt) <= (int) config('academic.webhook_tolerance');
    }

    private static function parseTimestamp(string $timestamp): ?int
    {
        if (ctype_digit($timestamp)) {
            return (int) $timestamp;
        }

        try {
            return Carbon::parse($timestamp)->timestamp;
        } catch (Throwable) {
            return null;
        }
    }
}

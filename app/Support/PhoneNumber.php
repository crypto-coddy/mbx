<?php

namespace App\Support;

class PhoneNumber
{
    public static function normalize(string $phone): string
    {
        return preg_replace('/\D+/', '', trim($phone)) ?? '';
    }

    /** @return list<string> */
    public static function loginCandidates(string $phone): array
    {
        $normalized = self::normalize($phone);
        if ($normalized === '') {
            return [];
        }

        $candidates = [$normalized];

        if (preg_match('/^(\d{1,4})\1(.+)$/', $normalized, $matches) === 1) {
            $candidates[] = $matches[1].$matches[2];
        }

        return array_values(array_unique($candidates));
    }
}

<?php
declare(strict_types=1);

class Masking
{
    public static function maskName(string $name): string
    {
        $parts = explode(' ', trim($name));
        return implode(' ', array_map(static function (string $part): string {
            if (strlen($part) <= 1) return $part . '*';
            return $part[0] . str_repeat('*', max(3, strlen($part) - 1));
        }, $parts));
    }

    public static function maskPhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($digits) < 4) return '****';
        return substr($digits, 0, 2)
            . str_repeat('*', strlen($digits) - 4)
            . substr($digits, -2);
    }

    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return '***@***.***';
        [$user, $domain] = $parts;
        $masked = strlen($user) > 2
            ? $user[0] . str_repeat('*', strlen($user) - 2) . $user[strlen($user) - 1]
            : '***';
        return $masked . '@' . $domain;
    }
}

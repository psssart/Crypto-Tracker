<?php

namespace App\Support;

class IntegrationRegistry
{
    public static function all(): array
    {
        return config('integrations.providers', []);
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function get(string $key): ?array
    {
        $providers = self::all();
        return $providers[$key] ?? null;
    }
}

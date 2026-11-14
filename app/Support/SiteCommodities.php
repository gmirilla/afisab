<?php

namespace App\Support;

use App\Models\Commodity;

/**
 * The public commodity catalogue: the designer's copy and photographs (config/site_commodities.php) shown for commodities that
 * are active in the list staff manage. Switching a commodity off there removes it from the catalogue, its page, the footer and the
 * enquiry forms together.
 */
class SiteCommodities
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $cache = null;

    /** @return array<string, array<string, mixed>> slug => entry, in catalogue order, active commodities only */
    public static function all(): array
    {
        if (self::$cache === null) {
            $active = Commodity::where('is_active', true)->pluck('name')->all();
            self::$cache = array_filter(config('site_commodities.items'), fn ($c) => in_array($c['db_name'], $active, true));
        }

        return self::$cache;
    }

    public static function forget(): void
    {
        self::$cache = null;
    }

    /** @return array<string, mixed>|null */
    public static function find(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }

    /** @return array<string, array<string, mixed>> the featured commodities (home page cards) that are active */
    public static function featuredItems(): array
    {
        return array_intersect_key(self::all(), array_flip(config('site_commodities.featured')));
    }

    /** @return array<string, string> slug => name, for the footer */
    public static function featured(): array
    {
        return array_map(fn ($c) => $c['name'], self::featuredItems());
    }

    /** @return array<string, array<string, mixed>> up to three related commodities that are active */
    public static function related(string $slug): array
    {
        $all = self::all();
        $out = [];
        foreach (($all[$slug]['related'] ?? []) as $rs) {
            if (isset($all[$rs])) {
                $out[$rs] = $all[$rs];
            }
        }

        return $out;
    }
}

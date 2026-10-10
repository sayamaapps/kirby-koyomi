<?php

namespace Koyomi;

/**
 * List filters from and to the URL query string, Kirby-free. No JavaScript:
 * filters are plain links and a GET form.       OWNER: task `kirby`
 *
 * The normalized filter array (used by Query::filter(), Repository::list()
 * and the koyomi/filters snippet) ALWAYS has exactly these keys:
 *
 *   ['category' => ?string, 'attendance' => ?string, 'language' => ?string,
 *    'featured' => bool, 'view' => 'upcoming'|'past', 'page' => int]
 *
 * Query string parameters: ?category=Retreat&attendance=online&language=cs&featured=1&view=past&page=2
 * Contract: SPEC.md §8.7.
 */
final class Filters
{
    public const EMPTY = ['category' => null, 'attendance' => null, 'language' => null, 'featured' => false, 'view' => 'upcoming', 'page' => 1];

    /**
     * @param array<string,mixed> $query       e.g. $_GET or Kirby's request()->query()->toArray()
     * @param list<string> $languages          allowed language codes; [] = any valid code
     * @return array{category:?string,attendance:?string,language:?string,featured:bool,view:string,page:int}
     */
    public static function fromQuery(array $query, array $languages = []): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /**
     * Only non-default values, fixed key order category, attendance,
     * language, featured ("1"), view ("past"), page (when > 1).
     *
     * @return array<string,string>
     */
    public static function toQuery(array $filters): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /**
     * $base + "?" + query of ($filters with $change applied), or $base alone
     * when nothing is left. A null in $change removes that filter. Any change
     * other than `page` resets page to 1. Encoding: http_build_query(…, '', '&', PHP_QUERY_RFC3986).
     *   url('https://ex.test/events', $f, ['category' => 'Retreat']) → 'https://ex.test/events?category=Retreat'
     */
    public static function url(string $base, array $filters, array $change = []): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** True when category, attendance, language or featured is set (view and page do not count). */
    public static function isActive(array $filters): bool
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }
}

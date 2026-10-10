<?php

namespace Koyomi;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Kirby\Cms\App;

/**
 * Reads `koyomi.*` options. The defaults live HERE, at the call sites — not
 * in an `options` block of App::plugin(), which Kirby would file under
 * `sayamaapps.koyomi.*`.
 *
 * `null` counts as "not set", like Kirby's own option(). Both spellings
 * work, flat (`'koyomi.active' => true`) and nested
 * (`'koyomi' => ['active' => true]`), because Kirby's option() finds both.
 *
 * The full option reference is SPEC.md §3 / README.md "Options".
 */
final class Options
{
    public const PREFIX = 'koyomi.';

    public const DEFAULT_CURRENCIES = ['EUR', 'CZK', 'CHF'];
    public const DEFAULT_LANGUAGES = ['de', 'en', 'cs'];

    /** Output switches and their defaults (all need `active` as well). */
    public const ROUTES = ['ics' => true, 'feed' => true, 'rss' => true, 'json' => false];

    public static function get(App $kirby, string $key, mixed $default = null): mixed
    {
        return $kirby->option(self::PREFIX . $key) ?? $default;
    }

    /** Master switch. Only a real `true` switches on. */
    public static function active(App $kirby): bool
    {
        return self::get($kirby, 'active', false) === true;
    }

    /** Default zone for new events and for times shown without a zone hint. Invalid → Europe/Berlin. */
    public static function timezone(App $kirby): string
    {
        $zone = (string) self::get($kirby, 'timezone', Time::DEFAULT_ZONE);
        return Time::isValidZone($zone) ? $zone : Time::DEFAULT_ZONE;
    }

    /**
     * Zones offered in the Panel select. `koyomi.timezones` = list of IANA
     * names (invalid ones dropped); unset = all identifiers PHP knows. The
     * default zone is always included.
     *
     * @return list<string>
     */
    public static function timezones(App $kirby): array
    {
        $option = self::get($kirby, 'timezones');
        $zones = is_array($option)
            ? array_values(array_filter(array_map('strval', $option), [Time::class, 'isValidZone']))
            : \DateTimeZone::listIdentifiers();
        $default = self::timezone($kirby);
        if (!in_array($default, $zones, true)) {
            array_unshift($zones, $default);
        }
        return array_values(array_unique($zones));
    }

    /** @return list<string> upper-case ISO 4217 codes, never empty */
    public static function currencies(App $kirby): array
    {
        $option = self::get($kirby, 'currencies', self::DEFAULT_CURRENCIES);
        $codes = [];
        foreach ((array) $option as $code) {
            $code = strtoupper(trim((string) $code));
            if (preg_match('/^[A-Z]{3}$/', $code) && !in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }
        return $codes === [] ? self::DEFAULT_CURRENCIES : $codes;
    }

    /** Default currency of new events: `koyomi.currency`, else the first of currencies(). */
    public static function currency(App $kirby): string
    {
        $code = strtoupper(trim((string) self::get($kirby, 'currency', '')));
        return preg_match('/^[A-Z]{3}$/', $code) ? $code : self::currencies($kirby)[0];
    }

    /**
     * Event languages offered in the Panel and the filter, code => custom
     * label ("" = use the translation `language.<code>`).
     * `koyomi.languages` may be a list (['de', 'cs']) or a map
     * (['de' => 'Deutsch', 'cs' => 'Čeština']).
     *
     * @return array<string,string>
     */
    public static function languages(App $kirby): array
    {
        $option = self::get($kirby, 'languages', self::DEFAULT_LANGUAGES);
        $out = [];
        foreach ((array) $option as $key => $value) {
            [$code, $label] = is_int($key) ? [(string) $value, ''] : [(string) $key, (string) $value];
            $code = Normalize::languages($code)[0] ?? null;
            if ($code !== null && !isset($out[$code])) {
                $out[$code] = trim($label);
            }
        }
        return $out;
    }

    /** Events per page in lists and the past archive, 1–100, default 12. */
    public static function perPage(App $kirby): int
    {
        return max(1, min(100, (int) self::get($kirby, 'perPage', 12)));
    }

    /** Minutes an event without end time lasts (for "past" and feeds), default 120. */
    public static function defaultDuration(App $kirby): int
    {
        return max(0, (int) self::get($kirby, 'defaultDuration', 120));
    }

    /** Days of past events kept in the ICS subscription feed, default 30. */
    public static function feedPastDays(App $kirby): int
    {
        return max(0, (int) self::get($kirby, 'feedPastDays', 30));
    }

    /**
     * One output switch: `ics` (per event /<event>.ics), `feed`
     * (/<calendar>.ics subscription), `rss` (/<calendar>.rss), `json`
     * (/<calendar>.json). False whenever `active` is off.
     */
    public static function route(App $kirby, string $name): bool
    {
        if (!self::active($kirby) || !array_key_exists($name, self::ROUTES)) {
            return false;
        }
        return self::get($kirby, $name, self::ROUTES[$name]) === true;
    }

    /** "Add to Google Calendar" link in the event snippet, default true. */
    public static function google(App $kirby): bool
    {
        return self::get($kirby, 'google', true) === true;
    }

    /** Hide cancelled events from the upcoming list (feeds keep them), default false. */
    public static function hideCancelled(App $kirby): bool
    {
        return self::get($kirby, 'hideCancelled', false) === true;
    }

    /** Reset status fields of a duplicated event (page.duplicate:after), default true. */
    public static function duplicateReset(App $kirby): bool
    {
        return self::get($kirby, 'duplicateReset', true) !== false;
    }

    /**
     * Date/time patterns per language, merged over Format's defaults:
     * ['de' => ['date' => 'l, j. F Y', 'time' => 'H:i', 'rangeSameMonth' => ['j.', 'j. F Y']], …].
     * Passed on: string patterns, and the range* pairs as lists of exactly
     * two strings. Anything else is dropped.
     *
     * @return array<string, array<string,string|array{0:string,1:string}>>
     */
    public static function formats(App $kirby): array
    {
        $out = [];
        foreach ((array) self::get($kirby, 'formats', []) as $language => $patterns) {
            if (!is_string($language) || !is_array($patterns)) {
                continue;
            }
            foreach ($patterns as $name => $pattern) {
                if (!is_string($name)) {
                    continue;
                }
                if (is_string($pattern)) {
                    $out[$language][$name] = $pattern;
                } elseif (is_array($pattern) && array_is_list($pattern) && count($pattern) === 2 && is_string($pattern[0]) && is_string($pattern[1])) {
                    $out[$language][$name] = [$pattern[0], $pattern[1]];
                }
            }
        }
        return $out;
    }

    /**
     * `koyomi.mapUrl`: a closure fn (Koyomi\Venue $venue): string replacing
     * the OpenStreetMap link, or null.
     */
    public static function mapUrl(App $kirby): ?Closure
    {
        $option = self::get($kirby, 'mapUrl');
        return $option instanceof Closure ? $option : null;
    }

    /** Page id of the calendar (`koyomi.calendar`), or null = find the first koyomi-calendar page. */
    public static function calendarId(App $kirby): ?string
    {
        $id = self::get($kirby, 'calendar');
        return is_string($id) && trim($id, " /") !== '' ? trim($id, " /") : null;
    }

    /** Page id of the venues container (`koyomi.venues`), or null = first koyomi-venues page. */
    public static function venuesId(App $kirby): ?string
    {
        $id = self::get($kirby, 'venues');
        return is_string($id) && trim($id, " /") !== '' ? trim($id, " /") : null;
    }

    /** Page id of the organizers container (`koyomi.organizers`), or null = first koyomi-organizers page. */
    public static function organizersId(App $kirby): ?string
    {
        $id = self::get($kirby, 'organizers');
        return is_string($id) && trim($id, " /") !== '' ? trim($id, " /") : null;
    }

    /**
     * Language of snippets and feeds: `koyomi.language`, else the current
     * language of a multi-language site, else its default language, else the
     * first two letters of Kirby's `locale` option, else English. Only
     * languages Koyomi ships (I18n::LANGUAGES) are returned.
     */
    public static function language(App $kirby): string
    {
        $candidates = [self::get($kirby, 'language')];
        try {
            $candidates[] = $kirby->language()?->code();
            $candidates[] = $kirby->defaultLanguage()?->code();
        } catch (\Throwable) {
            // No languages configured.
        }
        $locale = $kirby->option('locale');
        if (is_array($locale)) {
            $locale = $locale[LC_ALL] ?? reset($locale);
        }
        $candidates[] = is_string($locale) ? substr($locale, 0, 2) : null;

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && in_array(strtolower($candidate), I18n::LANGUAGES, true)) {
                return strtolower($candidate);
            }
        }
        return 'en';
    }

    /**
     * The clock. `koyomi.now` (a "Y-m-d H:i" string, a DateTimeInterface or
     * a closure returning either) pins it — for tests and for previewing a
     * calendar as it will look on a given day. Otherwise the real time. Always
     * in the default zone.
     */
    public static function now(App $kirby): DateTimeImmutable
    {
        $zone = Time::zone(self::timezone($kirby));
        $option = self::get($kirby, 'now');
        if ($option instanceof Closure) {
            $option = $option();
        }
        if ($option instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($option)->setTimezone($zone);
        }
        if (is_string($option) && ($parsed = Time::parse($option, $zone)) !== null) {
            return $parsed;
        }
        return new DateTimeImmutable('now', $zone);
    }

    /**
     * Second argument of Event::fromArray().
     *
     * @return array{timezone:string,currency:string,defaultDuration:int}
     */
    public static function eventDefaults(App $kirby): array
    {
        return [
            'timezone' => self::timezone($kirby),
            'currency' => self::currency($kirby),
            'defaultDuration' => self::defaultDuration($kirby),
        ];
    }

    /**
     * Everything the blueprint builders need, as plain data (Blueprints is
     * Kirby-free and testable with a hand-made array of this shape).
     *
     * @return array{timezone:string,timezones:list<string>,currency:string,currencies:list<string>,languages:array<string,string>}
     */
    public static function blueprintSettings(App $kirby): array
    {
        return [
            'timezone' => self::timezone($kirby),
            'timezones' => self::timezones($kirby),
            'currency' => self::currency($kirby),
            'currencies' => self::currencies($kirby),
            'languages' => self::languages($kirby),
        ];
    }
}

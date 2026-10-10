<?php

namespace Koyomi;

use DateTimeImmutable;

/**
 * Human-readable text for dates, prices, status … in the current I18n
 * language. Kirby-free. Returns PLAIN text — callers escape.
 *                                              OWNER: task `present`
 * Contract and expected outputs: SPEC.md §8.5.
 */
final class Format
{
    /**
     * Pattern defaults per language. Tokens (PHP date() letters, own names):
     *   d j m n Y y H G i s  → as in PHP date()
     *   F M  → month name / short month (translations month.N / monthShort.N)
     *   l D  → weekday / short weekday (translations weekday.N / weekdayShort.N, ISO N)
     *   \x   → literal x
     * Any other character is copied as is.
     *
     * Keys: date (single day, with weekday), dateShort (numeric), time,
     * rangeSameMonth / rangeSameYear / rangeOther (two patterns each: start, end),
     * with separators rangeSameMonthSep / rangeSep.
     */
    public const DEFAULTS = [
        'en' => [
            'date' => 'l, j F Y',
            'dateShort' => 'Y-m-d',
            'dateNoWeekday' => 'j F Y',
            'time' => 'H:i',
            'rangeSameMonth' => ['j', 'j F Y'],
            'rangeSameYear' => ['j F', 'j F Y'],
            'rangeOther' => ['j F Y', 'j F Y'],
            'rangeSameMonthSep' => '–',
            'rangeSep' => ' – ',
        ],
        'de' => [
            'date' => 'l, j. F Y',
            'dateShort' => 'd.m.Y',
            'dateNoWeekday' => 'j. F Y',
            'time' => 'H:i',
            'rangeSameMonth' => ['j.', 'j. F Y'],
            'rangeSameYear' => ['j. F', 'j. F Y'],
            'rangeOther' => ['j. F Y', 'j. F Y'],
            'rangeSameMonthSep' => '–',
            'rangeSep' => ' – ',
        ],
    ];

    /** Currency symbols; any other code is printed as the code. */
    public const SYMBOLS = ['EUR' => '€', 'CZK' => 'Kč', 'CHF' => 'CHF'];

    /** @var array<string, array<string, string|array>> */
    private static array $formats = self::DEFAULTS;

    private static string $siteZone = Time::DEFAULT_ZONE;

    /*
     * configure(), reset(), pattern() and siteZone() are written by the
     * architect (SHARED, done): koyomi_i18n() calls configure() on every
     * request, so every task's Kirby tests depend on it. Keep them as they are.
     */

    /**
     * Per-request setup (koyomi_i18n() calls it). $formats is
     * Options::formats(): ['de' => ['date' => '…'], …], merged key by key
     * over DEFAULTS (a language Koyomi does not ship is accepted, and falls
     * back to English per key). $siteZone: events in another zone get a zone
     * hint in when().
     */
    public static function configure(array $formats = [], string $siteZone = Time::DEFAULT_ZONE): void
    {
        $merged = self::DEFAULTS;
        foreach ($formats as $language => $patterns) {
            if (!is_string($language) || !is_array($patterns)) {
                continue;
            }
            foreach ($patterns as $name => $pattern) {
                if (is_string($name) && (is_string($pattern) || (is_array($pattern) && count($pattern) === 2))) {
                    $merged[$language][$name] = is_array($pattern) ? array_values(array_map('strval', $pattern)) : $pattern;
                }
            }
        }
        self::$formats = $merged;
        self::$siteZone = Time::isValidZone($siteZone) ? $siteZone : Time::DEFAULT_ZONE;
    }

    /** Back to DEFAULTS and Europe/Berlin (tests). */
    public static function reset(): void
    {
        self::$formats = self::DEFAULTS;
        self::$siteZone = Time::DEFAULT_ZONE;
    }

    /**
     * The effective pattern(s) for $name in the current language: string, or
     * [start, end] for the range* keys. Order: configured/default pattern of
     * the current language, then English, then "".
     */
    public static function pattern(string $name): string|array
    {
        $language = I18n::language();
        return self::$formats[$language][$name] ?? self::$formats['en'][$name] ?? '';
    }

    /** The configured site zone (for the zone hint). */
    public static function siteZone(): string
    {
        return self::$siteZone;
    }

    /** Applies a pattern (tokens above) to a time, in that time's own zone. */
    public static function apply(DateTimeImmutable $time, string $pattern): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** apply($time, pattern('date')) — "Friday, 12 March 2027" / "Freitag, 12. März 2027". */
    public static function date(DateTimeImmutable $time): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** apply($time, pattern('time')) — "18:00" (no "Uhr"; that comes from the when.* phrases). */
    public static function time(DateTimeImmutable $time): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /**
     * Date range of firstDay() … lastDay() without times:
     * same day → date(); same month → rangeSameMonth joined by rangeSameMonthSep;
     * same year → rangeSameYear joined by rangeSep; else rangeOther joined by rangeSep.
     */
    public static function dateRange(Event $event): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /**
     * The full "when" line (SPEC.md §8.5 table), e.g.
     *   de: "Freitag, 12. März 2027, 18:00–20:00 Uhr", "12.–14. März 2027",
     *       "Freitag, 12. März 2027, 18:00 Uhr – Sonntag, 14. März 2027, 13:00 Uhr"
     * plus " (Europe/Prague)" via when.timezone when the event zone differs
     * from the configured site zone and the event is not all-day.
     */
    public static function when(Event $event): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** "1.200 €" (de) / "€1,200" (en) — see SPEC.md §8.5 money rules. */
    public static function money(float $amount, string $currency): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** "Kostenlos" | money() | "" (no price). The priceNote is NOT included. */
    public static function price(Event $event): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** Status label, "" for scheduled. */
    public static function status(Event $event): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** "Ursprünglich geplant am 20. Februar 2027" for rescheduled events with previousStart, else "". */
    public static function previousStart(Event $event): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** Effective attendance label; with onlinePlatform for online/mixed ("Online über Zoom"). */
    public static function attendance(Event $event): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** Availability label, "" for available. */
    public static function availability(Event $event): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** "Teil 2 von 3" when the event is part of a series, else "". */
    public static function series(Event $event): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /**
     * Name of a language code: $custom label if not "", else translation
     * language.<code>, else the upper-cased code ("PT-BR").
     */
    public static function languageName(string $code, string $custom = ''): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** Language names of the event joined with ", " ("Deutsch, Tschechisch"). @param array<string,string> $custom code => label */
    public static function languages(Event $event, array $custom = []): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /**
     * All labels at once — the `labels` key of $page->koyomiData():
     *   ['when' => …, 'dateRange' => …, 'status' => …, 'previousStart' => …, 'attendance' => …,
     *    'availability' => …, 'price' => …, 'series' => …, 'languages' => …]
     */
    public static function labels(Event $event, array $customLanguages = []): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }
}

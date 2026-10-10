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

    /**
     * Applies a pattern (tokens above) to a time, in that time's own zone.
     * The month and weekday names come from the translations, so a pattern
     * stays language-neutral; "\x" prints x literally ("\T" is a plain T).
     */
    public static function apply(DateTimeImmutable $time, string $pattern): string
    {
        $out = '';
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];
            if ($char === '\\') {
                if ($i + 1 < $length) {
                    $out .= $pattern[++$i];
                }
                continue;
            }
            // Bytes, not characters: every token is ASCII and a multi-byte
            // character is copied byte by byte, so the result is the same.
            $out .= match ($char) {
                'd', 'j', 'm', 'n', 'Y', 'y', 'H', 'G', 'i', 's' => $time->format($char),
                'F' => I18n::t('month.' . $time->format('n')),
                'M' => I18n::t('monthShort.' . $time->format('n')),
                'l' => I18n::t('weekday.' . $time->format('N')),
                'D' => I18n::t('weekdayShort.' . $time->format('N')),
                default => $char,
            };
        }
        return $out;
    }

    /** apply($time, pattern('date')) — "Friday, 12 March 2027" / "Freitag, 12. März 2027". */
    public static function date(DateTimeImmutable $time): string
    {
        return self::apply($time, self::single('date'));
    }

    /** apply($time, pattern('time')) — "18:00" (no "Uhr"; that comes from the when.* phrases). */
    public static function time(DateTimeImmutable $time): string
    {
        return self::apply($time, self::single('time'));
    }

    /**
     * Date range of firstDay() … lastDay() without times:
     * same day → date(); same month → rangeSameMonth joined by rangeSameMonthSep;
     * same year → rangeSameYear joined by rangeSep; else rangeOther joined by rangeSep.
     */
    public static function dateRange(Event $event): string
    {
        $first = $event->firstDay();
        $last = $event->lastDay();
        if ($first->format('Y-m-d') === $last->format('Y-m-d')) {
            return self::date($first);
        }
        if ($first->format('Y-m') === $last->format('Y-m')) {
            [$startPattern, $endPattern] = self::pair('rangeSameMonth');
            return self::apply($first, $startPattern) . self::single('rangeSameMonthSep') . self::apply($last, $endPattern);
        }
        if ($first->format('Y') === $last->format('Y')) {
            [$startPattern, $endPattern] = self::pair('rangeSameYear');
        } else {
            [$startPattern, $endPattern] = self::pair('rangeOther');
        }
        return self::apply($first, $startPattern) . self::single('rangeSep') . self::apply($last, $endPattern);
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
        if ($event->allDay) {
            return self::dateRange($event);
        }
        $start = $event->start;
        if (!$event->isMultiDay()) {
            $time = $event->hasEndTime
                ? I18n::t('when.timeRange', ['start' => self::time($start), 'end' => self::time($event->end)])
                : I18n::t('when.timeSingle', ['time' => self::time($start)]);
            $text = I18n::t('when.dateTime', ['date' => self::date($start), 'time' => $time]);
        } elseif ($event->hasEndTime) {
            $text = I18n::t('when.multiDayTimed', [
                'startDate' => self::date($start),
                'startTime' => self::time($start),
                'endDate' => self::date($event->end),
                'endTime' => self::time($event->end),
            ]);
        } else {
            $text = I18n::t('when.dateTime', [
                'date' => self::dateRange($event),
                'time' => I18n::t('when.timeSingle', ['time' => self::time($start)]),
            ]);
        }
        if ($event->timezone !== self::siteZone()) {
            $text = I18n::t('when.timezone', [
                'when' => $text,
                'zone' => str_replace('_', ' ', $event->timezone),
            ]);
        }
        return $text;
    }

    /**
     * "380 €" (de) / "€380" (en) — see SPEC.md §8.5 money rules. Whole
     * amounts have no decimals, others two. Plain spaces, no-break spaces
     * would break the expected output.
     */
    public static function money(float $amount, string $currency): string
    {
        $currency = strtoupper($currency);
        $decimals = abs($amount - round($amount)) < 0.005 ? 0 : 2;
        if (I18n::language() === 'de') {
            $number = number_format($amount, $decimals, ',', '.');
            return $number . ' ' . (self::SYMBOLS[$currency] ?? $currency);
        }
        $number = number_format($amount, $decimals, '.', ',');
        return $currency === 'EUR' ? '€' . $number : $currency . ' ' . $number;
    }

    /** "Kostenlos" | money() | "" (no price). The priceNote is NOT included. */
    public static function price(Event $event): string
    {
        if ($event->isFree) {
            return I18n::t('price.free');
        }
        if ($event->price === null) {
            return '';
        }
        return self::money($event->price, $event->currency);
    }

    /** Status label, "" for scheduled. */
    public static function status(Event $event): string
    {
        return $event->status === Status::SCHEDULED ? '' : I18n::t('status.' . $event->status);
    }

    /** "Ursprünglich geplant am 20. Februar 2027" for rescheduled events with previousStart, else "". */
    public static function previousStart(Event $event): string
    {
        if ($event->status !== Status::RESCHEDULED || $event->previousStart === null) {
            return '';
        }
        return I18n::t('status.previousStart', ['date' => self::date($event->previousStart)]);
    }

    /** Effective attendance label; with onlinePlatform for online/mixed ("Online über Zoom"). */
    public static function attendance(Event $event): string
    {
        $platform = trim($event->onlinePlatform);
        return match ($event->effectiveAttendance()) {
            Attendance::ONLINE => $platform === ''
                ? I18n::t('attendance.online')
                : I18n::t('attendance.onlineVia', ['platform' => $platform]),
            Attendance::MIXED => $platform === ''
                ? I18n::t('attendance.mixed')
                : I18n::t('attendance.mixedVia', ['platform' => $platform]),
            default => I18n::t('attendance.offline'),
        };
    }

    /** Availability label, "" for available. */
    public static function availability(Event $event): string
    {
        return match ($event->availability) {
            Availability::LIMITED => I18n::t('availability.limited'),
            Availability::SOLDOUT => I18n::t('availability.soldout'),
            default => '',
        };
    }

    /** "Teil 2 von 3" when the event is part of a series, else "". */
    public static function series(Event $event): string
    {
        if ($event->series === [] || $event->seriesPart === null) {
            return '';
        }
        return I18n::t('series.part', ['part' => $event->seriesPart, 'total' => $event->seriesTotal()]);
    }

    /**
     * Name of a language code: $custom label if not "", else translation
     * language.<code>, else the upper-cased code ("PT-BR").
     */
    public static function languageName(string $code, string $custom = ''): string
    {
        $custom = trim($custom);
        if ($custom !== '') {
            return $custom;
        }
        $key = 'language.' . $code;
        $name = I18n::t($key);
        return $name === $key ? strtoupper($code) : $name;
    }

    /** Language names of the event joined with ", " ("Deutsch, Tschechisch"). @param array<string,string> $custom code => label */
    public static function languages(Event $event, array $custom = []): string
    {
        $names = [];
        foreach ($event->languages as $code) {
            $names[] = self::languageName($code, (string) ($custom[$code] ?? ''));
        }
        return implode(', ', $names);
    }

    /**
     * All labels at once — the `labels` key of $page->koyomiData():
     *   ['when' => …, 'dateRange' => …, 'status' => …, 'previousStart' => …, 'attendance' => …,
     *    'availability' => …, 'price' => …, 'series' => …, 'languages' => …]
     */
    public static function labels(Event $event, array $customLanguages = []): array
    {
        return [
            'when' => self::when($event),
            'dateRange' => self::dateRange($event),
            'status' => self::status($event),
            'previousStart' => self::previousStart($event),
            'attendance' => self::attendance($event),
            'availability' => self::availability($event),
            'price' => self::price($event),
            'series' => self::series($event),
            'languages' => self::languages($event, $customLanguages),
        ];
    }

    /** A single pattern of a key (the first one when the key is a range pair). */
    private static function single(string $name): string
    {
        $pattern = self::pattern($name);
        return is_array($pattern) ? (string) ($pattern[0] ?? '') : $pattern;
    }

    /** The [start, end] pattern pair of a range key; a single pattern serves for both. */
    private static function pair(string $name): array
    {
        $pattern = self::pattern($name);
        if (is_string($pattern)) {
            return [$pattern, $pattern];
        }
        $start = (string) ($pattern[0] ?? '');
        return [$start, (string) ($pattern[1] ?? $start)];
    }
}

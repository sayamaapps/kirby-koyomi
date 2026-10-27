<?php

namespace Sayamaapps\Koyomi;

use DateTimeImmutable;

/**
 * Human-readable text for dates, prices, status … in the current I18n
 * language. Kirby-free. Returns PLAIN text — callers escape.
 *
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-koyomi
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class Format
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
	 * with separators rangeSameMonthSep / rangeSep; for the timeline monthTitle
	 * ("November 2026"), day ("6." / "6") and dayMonth ("2. Dez." / "Dec 2").
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
			'monthTitle' => 'F Y',
			'day' => 'j',
			'dayMonth' => 'M j',
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
			'monthTitle' => 'F Y',
			'day' => 'j.',
			'dayMonth' => 'j. M',
		],
	];

	/** Currency symbols; any other code is printed as the code. */
	public const SYMBOLS = ['EUR' => '€', 'CZK' => 'Kč', 'CHF' => 'CHF'];

	/** @var array<string, array<string, string|array>> */
	private static array $formats = self::DEFAULTS;
	private static string $siteZone = Time::DEFAULT_ZONE;

	/**
	 * Address of a venue as display lines, empty ones left out:
	 * ["Musterstraße 1", "12345 Musterstadt", "Tschechien"]. The country is shown by
	 * name, and left out when it is $homeCountry (`koyomi.country`).
	 *
	 * @return list<string>
	 */
	public static function addressLines(Venue $venue, string $homeCountry = ''): array
	{
		$country = $venue->country !== '' && $venue->country !== strtoupper($homeCountry)
			? self::countryName($venue->country)
			: '';
		return array_values(array_filter(
			[$venue->street, trim($venue->postalCode . ' ' . $venue->city), $country],
			fn (string $line) => $line !== ''
		));
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

	/**
	 * The time of day for the timeline, the date being shown by dayBlock():
	 * "18:00–21:00 Uhr", "18:00 Uhr", "Fr 18:00 – So 13:00 Uhr", "ganztägig".
	 * Adds the zone like when() when it differs from the site zone.
	 */
	public static function clock(Event $event): string
	{
		if ($event->allDay) {
			return I18n::t('when.allDay');
		}
		$start = $event->start;
		if ($event->isMultiDay() && $event->hasEndTime) {
			$text = I18n::t('when.multiDayClock', [
				'startDay' => self::apply($start, 'D'),
				'startTime' => self::time($start),
				'endDay' => self::apply($event->end, 'D'),
				'endTime' => self::time($event->end),
			]);
		} elseif ($event->hasEndTime && !$event->isMultiDay()) {
			$text = I18n::t('when.timeRange', ['start' => self::time($start), 'end' => self::time($event->end)]);
		} else {
			$text = I18n::t('when.timeSingle', ['time' => self::time($start)]);
		}
		if ($event->timezone !== self::siteZone()) {
			$text = I18n::t('when.timezone', ['when' => $text, 'zone' => str_replace('_', ' ', $event->timezone)]);
		}
		return $text;
	}

	// configure() is called once per request by Api::i18n(); it sets the formats and the site zone.

	/**
	 * Per-request setup (Api::i18n() calls it). $formats is
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

	/** Name of an ISO country code: translation country.<CODE>, else the code as it is ("MT"). */
	public static function countryName(string $code): string
	{
		$key = 'country.' . strtoupper($code);
		$name = I18n::t($key);
		return $name === $key ? strtoupper($code) : $name;
	}

	/** apply($time, pattern('date')) — "Friday, 12 March 2027" / "Freitag, 12. März 2027". */
	public static function date(DateTimeImmutable $time): string
	{
		return self::apply($time, self::single('date'));
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
	 * The calendar-sheet date of a card in the timeline, from firstDay() and
	 * lastDay(): one day → ['Fr', '6.', 'Nov.', ''], several days in one month
	 * → ['Fr', '6.–8.', 'Nov.', ''], across months → ['Fr', '30.', 'Okt.', 'bis 2. Nov.'].
	 *
	 * @return array{weekday: string, day: string, month: string, until: string}
	 */
	public static function dayBlock(Event $event): array
	{
		$first = $event->firstDay();
		$last = $event->lastDay();
		$dayPattern = self::single('day');
		$day = self::apply($first, $dayPattern);
		$until = '';
		if ($first->format('Y-m-d') !== $last->format('Y-m-d')) {
			if ($first->format('Y-m') === $last->format('Y-m')) {
				$day .= self::single('rangeSameMonthSep') . self::apply($last, $dayPattern);
			} else {
				$until = I18n::t('date.until', ['date' => self::apply($last, self::single('dayMonth'))]);
			}
		}
		return [
			'weekday' => self::apply($first, 'D'),
			'day' => $day,
			'month' => self::apply($first, 'M'),
			'until' => $until,
		];
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

	/**
	 * Language names of the event joined with ", " ("Deutsch, Tschechisch").
	 *
	 * @param array<string,string> $custom code => label
	 */
	public static function languages(Event $event, array $custom = []): string
	{
		$names = [];
		foreach ($event->languages as $code) {
			$names[] = self::languageName($code, (string)($custom[$code] ?? ''));
		}
		return implode(', ', $names);
	}

	/**
	 * "380 €" (de) / "€380" (en) — see the money rules in money(). Whole
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

	/** Heading of a month in the timeline: "November 2026". */
	public static function monthTitle(DateTimeImmutable $time): string
	{
		return self::apply($time, self::single('monthTitle'));
	}

	/** The [start, end] pattern pair of a range key; a single pattern serves for both. */
	private static function pair(string $name): array
	{
		$pattern = self::pattern($name);
		if (is_string($pattern)) {
			return [$pattern, $pattern];
		}
		$start = (string)($pattern[0] ?? '');
		return [$start, (string)($pattern[1] ?? $start)];
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

	/** "Ursprünglich geplant am 20. Februar 2027" for rescheduled events with previousStart, else "". */
	public static function previousStart(Event $event): string
	{
		if ($event->status !== Status::RESCHEDULED || $event->previousStart === null) {
			return '';
		}
		return I18n::t('status.previousStart', ['date' => self::date($event->previousStart)]);
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

	/** "Teil 2 von 3" when the event is part of a series, else "". */
	public static function series(Event $event): string
	{
		if ($event->series === [] || $event->seriesPart === null) {
			return '';
		}
		return I18n::t('series.part', ['part' => $event->seriesPart, 'total' => $event->seriesTotal()]);
	}

	/** A single pattern of a key (the first one when the key is a range pair). */
	private static function single(string $name): string
	{
		$pattern = self::pattern($name);
		return is_array($pattern) ? (string)($pattern[0] ?? '') : $pattern;
	}

	/** The configured site zone (for the zone hint). */
	public static function siteZone(): string
	{
		return self::$siteZone;
	}

	/** Status label, "" for scheduled. */
	public static function status(Event $event): string
	{
		return $event->status === Status::SCHEDULED ? '' : I18n::t('status.' . $event->status);
	}

	/** apply($time, pattern('time')) — "18:00" (no "Uhr"; that comes from the when.* phrases). */
	public static function time(DateTimeImmutable $time): string
	{
		return self::apply($time, self::single('time'));
	}

	/**
	 * The full "when" line, e.g.
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
}

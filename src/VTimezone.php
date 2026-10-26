<?php

namespace Sayamaapps\Koyomi;

use DateTimeZone;

/**
 * VTIMEZONE components from PHP's tz database, Kirby-free.
 *
 * No RRULEs: every real transition in the window becomes its own STANDARD or
 * DAYLIGHT sub-component with an explicit DTSTART. That is valid RFC 5545,
 * exact for any zone PHP knows, and needs no guessing of rules.
 * Contract: SPEC.md §8.2.
 */
final class VTimezone
{
	/** Fixed DTSTART of the base observance (RFC 5545 needs a start before any transition). */
	private const BASE_START = '19700101T000000';

	/** One year in seconds: the margin added on both sides of the event window. */
	private const MARGIN = 31536000;

	/**
	 * Lines (unfolded) BEGIN:VTIMEZONE … END:VTIMEZONE for $zone, covering
	 * $from … $to (unix timestamps). The window is widened by one year on
	 * each side. The state in force at the start of the window is always
	 * emitted, so the first event's offset is defined.
	 *
	 * Sub-component for a transition at UTC instant T from offset A to B:
	 *   BEGIN:STANDARD (or DAYLIGHT when the new state is DST)
	 *   DTSTART:<T + A as local time, Ymd\THis>   ← local time BEFORE the change
	 *   TZOFFSETFROM:<A as ±HHMM>
	 *   TZOFFSETTO:<B as ±HHMM>
	 *   TZNAME:<abbreviation after the change, e.g. CEST>
	 *   END:STANDARD
	 *
	 * A zone without any transition in the window (e.g. Asia/Tokyo) gives one
	 * STANDARD with DTSTART:19700101T000000 and TZOFFSETFROM = TZOFFSETTO =
	 * the current offset.
	 *
	 * Returns [] for "UTC" (callers use the Z form instead).
	 *
	 * @return list<string>
	 */
	public static function lines(string $zone, int $from, int $to): array
	{
		if ($zone === 'UTC') {
			return [];
		}

		// PHP lists the state at the window begin first, then every real
		// transition inside the window.
		$transitions = (new DateTimeZone($zone))->getTransitions($from - self::MARGIN, $to + self::MARGIN);
		$base = array_shift($transitions);

		$lines = ['BEGIN:VTIMEZONE', 'TZID:' . $zone];
		// The base observance is written with a fixed DTSTART and FROM = TO:
		// it only defines the offset in force before the first real change.
		$offset = $base['offset'];
		$lines = array_merge($lines, self::observance($base['isdst'], self::BASE_START, $offset, $offset, $base['abbr']));

		$previous = $offset;
		foreach ($transitions as $transition) {
			// DTSTART is the local time BEFORE the change, i.e. UTC + old offset.
			$dtstart = gmdate('Ymd\THis', $transition['ts'] + $previous);
			$lines = array_merge($lines, self::observance($transition['isdst'], $dtstart, $previous, $transition['offset'], $transition['abbr']));
			$previous = $transition['offset'];
		}

		$lines[] = 'END:VTIMEZONE';
		return $lines;
	}

	/**
	 * One STANDARD or DAYLIGHT sub-component (six lines). The kind follows the
	 * DST flag of the state the component introduces, not the one it leaves.
	 *
	 * @return list<string>
	 */
	private static function observance(bool $daylight, string $dtstart, int $from, int $to, string $abbreviation): array
	{
		$kind = $daylight ? 'DAYLIGHT' : 'STANDARD';
		return [
			'BEGIN:' . $kind,
			'DTSTART:' . $dtstart,
			'TZOFFSETFROM:' . self::offset($from),
			'TZOFFSETTO:' . self::offset($to),
			'TZNAME:' . $abbreviation,
			'END:' . $kind,
		];
	}

	/** Seconds → "+0100", "-0500", "+0530", "+0000". */
	public static function offset(int $seconds): string
	{
		$sign = $seconds < 0 ? '-' : '+';
		$abs = abs($seconds);
		return sprintf('%s%02d%02d', $sign, intdiv($abs, 3600), intdiv($abs % 3600, 60));
	}
}

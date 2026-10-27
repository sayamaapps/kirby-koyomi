<?php

namespace Sayamaapps\Koyomi;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Wall-clock time + IANA zone → instant, and the output formats every part
 * of Koyomi shares. Kirby-free.
 *
 * Content stores LOCAL wall time ("2027-03-15", "18:00") plus a zone name
 * ("Europe/Berlin"); offsets are only computed at output, so a date entered
 * in winter for a summer event gets the summer offset (DST-safe).
 *
 * DST edge cases follow PHP: a wall time inside the spring-forward gap moves
 * forward (2027-03-28 02:30 Europe/Berlin → 03:30+02:00); an ambiguous time
 * in the autumn overlap takes the later, standard-time instant
 * (2027-10-31 02:30 → 02:30+01:00).
 *
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-koyomi
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class Time
{
	public const DEFAULT_ZONE = 'Europe/Berlin';

	/** @var array<string,true>|null */
	private static array|null $zones = null;

	/** "2027-03-15" — the local calendar date. */
	public static function date(DateTimeImmutable $time): string
	{
		return $time->format('Y-m-d');
	}

	/** iCalendar date: "20270315". */
	public static function icsDate(DateTimeImmutable $time): string
	{
		return $time->format('Ymd');
	}

	/** ISO 8601 with offset: "2027-03-15T18:00:00+01:00" (schema.org, JSON, <time datetime>). */
	public static function iso(DateTimeImmutable $time): string
	{
		return $time->format('Y-m-d\TH:i:sP');
	}

	/**
	 * IANA identifier PHP knows ("Europe/Berlin", "UTC"); no abbreviations, no offsets.
	 *
	 * @psalm-assert-if-true non-empty-string $name
	 */
	public static function isValidZone(string $name): bool
	{
		self::$zones ??= array_fill_keys(DateTimeZone::listIdentifiers(), true);
		return isset(self::$zones[$name]);
	}

	/** iCalendar local form without zone: "20270315T180000" (used with TZID=). */
	public static function local(DateTimeImmutable $time): string
	{
		return $time->format('Ymd\THis');
	}

	/**
	 * "Y-m-d", "Y-m-d H:i", "Y-m-d H:i:s" or "Y-m-d\TH:i[:s]" as wall time
	 * in $zone; null otherwise.
	 */
	public static function parse(string $value, DateTimeZone $zone): DateTimeImmutable|null
	{
		$value = trim($value);
		if (!preg_match('/^(\d{4}-\d{2}-\d{2})(?:[ T](\d{1,2}:\d{2}(?::\d{2})?))?$/', $value, $m)) {
			return null;
		}
		return self::wall($m[1], $m[2] ?? '', $zone);
	}

	/** iCalendar UTC form: "20270315T170000Z". */
	public static function utc(DateTimeImmutable $time): string
	{
		return $time->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
	}

	/**
	 * Date ("Y-m-d", only the first 10 characters count, so Kirby's
	 * "2027-03-15 00:00:00" works) and time ("H:i" or "H:i:s", "" = 00:00)
	 * as local wall time in $zone. Null for anything that is not a real
	 * calendar date or clock time (2027-02-30, 25:00, "soon").
	 */
	public static function wall(string $date, string $time, DateTimeZone $zone): DateTimeImmutable|null
	{
		$date = substr(trim($date), 0, 10);
		$time = trim($time);
		if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $d) || !checkdate((int)$d[2], (int)$d[3], (int)$d[1])) {
			return null;
		}
		if ($time === '') {
			$time = '00:00:00';
		} elseif (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time, $t)) {
			if ((int)$t[1] > 23 || (int)$t[2] > 59 || (int)($t[3] ?? 0) > 59) {
				return null;
			}
			$time = sprintf('%02d:%02d:%02d', (int)$t[1], (int)$t[2], (int)($t[3] ?? 0));
		} else {
			return null;
		}
		$result = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date . ' ' . $time, $zone);
		return $result === false ? null : $result;
	}

	/** The named zone; an invalid or empty name gives $fallback, an invalid fallback UTC. */
	public static function zone(string|null $name, string $fallback = self::DEFAULT_ZONE): DateTimeZone
	{
		foreach ([trim((string)$name), $fallback] as $candidate) {
			if (self::isValidZone($candidate)) {
				return new DateTimeZone($candidate);
			}
		}
		return new DateTimeZone('UTC');
	}
}

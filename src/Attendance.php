<?php

namespace Sayamaapps\Koyomi;

/**
 * How people attend. The Panel stores two toggles, `inPerson` and `online`
 * (Kirby's `when` can only test equality, so "venue required for offline OR
 * mixed" needs a toggle); Mapper derives the value. A site blueprint may
 * store a field `attendance` with one of these values instead — it wins.
 *
 *   value    schema.org eventAttendanceMode
 *   offline  https://schema.org/OfflineEventAttendanceMode
 *   online   https://schema.org/OnlineEventAttendanceMode
 *   mixed    https://schema.org/MixedEventAttendanceMode
 *
 * Status `movedOnline` makes the EFFECTIVE attendance `online`
 * (Event::effectiveAttendance()).
 */
final class Attendance
{
	public const ALL = [self::OFFLINE, self::ONLINE, self::MIXED];
	public const MIXED = 'mixed';
	public const OFFLINE = 'offline';
	public const ONLINE = 'online';

	/** From the two Panel toggles; both off counts as offline. */
	public static function fromToggles(bool $inPerson, bool $online): string
	{
		return match (true) {
			$inPerson && $online => self::MIXED,
			$online => self::ONLINE,
			default => self::OFFLINE,
		};
	}

	public static function normalize(mixed $value): string
	{
		return Normalize::choice($value, self::ALL, self::OFFLINE);
	}
}

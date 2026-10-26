<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Koyomi\Event;
use Sayamaapps\Koyomi\VTimezone;

/**
 * The six-line sub-component whose DTSTART line equals $dtstart (its BEGIN
 * line sits right before it). Empty when the DTSTART is not in the list.
 *
 * @return list<string>
 */
function vtz_test_component(array $lines, string $dtstart): array
{
	$index = array_search('DTSTART:' . $dtstart, $lines, true);
	return $index === false ? [] : array_slice($lines, $index - 1, 6);
}

/** Unix timestamp of a wall time in a zone. */
function vtz_test_ts(string $wall, string $zone): int
{
	return (new DateTimeImmutable($wall, new DateTimeZone($zone)))->getTimestamp();
}

/**
 * VTimezone (VTIMEZONE from PHP's tz database): the Berlin example of
 * SPEC §8.2 exactly, a zone without transitions (Tokyo), UTC, New York and
 * Sydney (northern and southern DST), and the offset() formatter.
 */
#[CoversClass(VTimezone::class)]
final class VTimezoneTest extends TestCase
{
	public function testSuite(): void
	{
		koyomi_require_core();

		// SPEC §8.2: the Berlin example, window of the reference retreat.
		$retreat = Event::fromArray(koyomi_raw_event([
			'id' => 'evt-retreat', 'title' => 'Silent Retreat', 'endDate' => '2027-03-14', 'endTime' => '13:00',
		]));
		$berlin = VTimezone::lines('Europe/Berlin', $retreat->start->getTimestamp(), $retreat->end->getTimestamp());
		expect(
			[
				'BEGIN:VTIMEZONE',
				'TZID:Europe/Berlin',
				'BEGIN:STANDARD',
				'DTSTART:19700101T000000',
				'TZOFFSETFROM:+0100',
				'TZOFFSETTO:+0100',
				'TZNAME:CET',
				'END:STANDARD',
				'BEGIN:DAYLIGHT',
				'DTSTART:20260329T020000',
				'TZOFFSETFROM:+0100',
				'TZOFFSETTO:+0200',
				'TZNAME:CEST',
				'END:DAYLIGHT',
				'BEGIN:STANDARD',
				'DTSTART:20261025T030000',
				'TZOFFSETFROM:+0200',
				'TZOFFSETTO:+0100',
				'TZNAME:CET',
				'END:STANDARD',
				'BEGIN:DAYLIGHT',
				'DTSTART:20270328T020000',
				'TZOFFSETFROM:+0100',
				'TZOFFSETTO:+0200',
				'TZNAME:CEST',
				'END:DAYLIGHT',
				'BEGIN:STANDARD',
				'DTSTART:20271031T030000',
				'TZOFFSETFROM:+0200',
				'TZOFFSETTO:+0100',
				'TZNAME:CET',
				'END:STANDARD',
				'END:VTIMEZONE',
			],
			$berlin,
			'Berlin example of SPEC §8.2, line by line',
		);
		$observances = array_values(array_filter($berlin, fn (string $l) => $l === 'BEGIN:STANDARD' || $l === 'BEGIN:DAYLIGHT'));
		expect(5, count($observances), 'Berlin: base observance plus one per real transition in the window');

		// The window is widened by one year on each side, so the base observance
		// is the state one year before the event (July 2026: CEST, DST).
		$summer = VTimezone::lines('Europe/Berlin', vtz_test_ts('2027-07-01 10:00', 'Europe/Berlin'), vtz_test_ts('2027-07-01 12:00', 'Europe/Berlin'));
		expect(
			['BEGIN:DAYLIGHT', 'DTSTART:19700101T000000', 'TZOFFSETFROM:+0200', 'TZOFFSETTO:+0200', 'TZNAME:CEST', 'END:DAYLIGHT'],
			array_slice($summer, 2, 6),
			'mid-summer window: base observance is the state at the widened window start (CEST)',
		);

		// Asia/Tokyo: no transition since 1951, so one STANDARD only.
		$tokyo = VTimezone::lines('Asia/Tokyo', vtz_test_ts('2027-03-12 18:00', 'Asia/Tokyo'), vtz_test_ts('2027-03-14 13:00', 'Asia/Tokyo'));
		expect(
			[
				'BEGIN:VTIMEZONE',
				'TZID:Asia/Tokyo',
				'BEGIN:STANDARD',
				'DTSTART:19700101T000000',
				'TZOFFSETFROM:+0900',
				'TZOFFSETTO:+0900',
				'TZNAME:JST',
				'END:STANDARD',
				'END:VTIMEZONE',
			],
			$tokyo,
			'Tokyo: single observance with the current offset',
		);

		// UTC uses the Z form, so it never gets a VTIMEZONE.
		expect([], VTimezone::lines('UTC', 0, 1000000), 'UTC: no VTIMEZONE');

		// America/New_York 2027: spring-forward and fall-back as local times
		// before the change (02:00 EST = 07:00Z → 02:00; 02:00 EDT = 06:00Z → 02:00).
		$newYork = VTimezone::lines(
			'America/New_York',
			vtz_test_ts('2027-01-01 00:00', 'America/New_York'),
			vtz_test_ts('2027-12-31 23:00', 'America/New_York'),
		);
		expect(
			['BEGIN:STANDARD', 'DTSTART:19700101T000000', 'TZOFFSETFROM:-0500', 'TZOFFSETTO:-0500', 'TZNAME:EST', 'END:STANDARD'],
			vtz_test_component($newYork, '19700101T000000'),
			'New York: base observance EST',
		);
		expect(
			['BEGIN:DAYLIGHT', 'DTSTART:20270314T020000', 'TZOFFSETFROM:-0500', 'TZOFFSETTO:-0400', 'TZNAME:EDT', 'END:DAYLIGHT'],
			vtz_test_component($newYork, '20270314T020000'),
			'New York: DST starts 2027-03-14 02:00 local',
		);
		expect(
			['BEGIN:STANDARD', 'DTSTART:20271107T020000', 'TZOFFSETFROM:-0400', 'TZOFFSETTO:-0500', 'TZNAME:EST', 'END:STANDARD'],
			vtz_test_component($newYork, '20271107T020000'),
			'New York: DST ends 2027-11-07 02:00 local',
		);

		// Australia/Sydney: southern hemisphere. In January the base state is
		// DST (AEDT), so the base observance is DAYLIGHT; DST ends in April and
		// starts again in October.
		$sydney = VTimezone::lines(
			'Australia/Sydney',
			vtz_test_ts('2027-01-01 00:00', 'Australia/Sydney'),
			vtz_test_ts('2027-12-31 23:00', 'Australia/Sydney'),
		);
		expect(
			['BEGIN:DAYLIGHT', 'DTSTART:19700101T000000', 'TZOFFSETFROM:+1100', 'TZOFFSETTO:+1100', 'TZNAME:AEDT', 'END:DAYLIGHT'],
			array_slice($sydney, 2, 6),
			'Sydney: base observance is DAYLIGHT when the window starts in DST',
		);
		expect(
			['BEGIN:STANDARD', 'DTSTART:20270404T030000', 'TZOFFSETFROM:+1100', 'TZOFFSETTO:+1000', 'TZNAME:AEST', 'END:STANDARD'],
			vtz_test_component($sydney, '20270404T030000'),
			'Sydney: April change back to standard time',
		);
		expect(
			['BEGIN:DAYLIGHT', 'DTSTART:20271003T020000', 'TZOFFSETFROM:+1000', 'TZOFFSETTO:+1100', 'TZNAME:AEDT', 'END:DAYLIGHT'],
			vtz_test_component($sydney, '20271003T020000'),
			'Sydney: DAYLIGHT in October (2027-10-03 02:00 local)',
		);
		foreach ($sydney as $line) {
			if (str_starts_with($line, 'DTSTART:')) {
				holds((bool)preg_match('/^DTSTART:\d{8}T\d{6}$/', $line), 'DTSTART is Ymd\THis: ' . $line);
			}
		}

		// offset(): seconds → ±HHMM, including the half-hour and quarter-hour zones.
		expect('+0100', VTimezone::offset(3600), 'offset +1 hour');
		expect('+0000', VTimezone::offset(0), 'offset zero');
		expect('-0500', VTimezone::offset(-18000), 'offset -5 hours');
		expect('+0530', VTimezone::offset(19800), 'offset +05:30 (India)');
		expect('-0930', VTimezone::offset(-34200), 'offset -09:30 (Marquesas)');
		expect('+0900', VTimezone::offset(32400), 'offset +9 (Tokyo)');
		expect('+0545', VTimezone::offset(20700), 'offset +05:45 (Nepal)');
		expect('-0100', VTimezone::offset(-3600), 'offset -1 hour');
		expect('+1400', VTimezone::offset(50400), 'offset +14 (Kiritimati)');
	}
}

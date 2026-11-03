<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Koyomi\Event;
use Sayamaapps\Koyomi\Expiry;

/**
 * Expiry::next(): the earliest future start or end decides when a cached
 * list goes stale.
 */
#[CoversClass(Expiry::class)]
final class ExpiryTest extends TestCase
{
	/**
	 * leavesAt(): the one rule behind the feed filter and the cache expiry: the
	 * end plus N calendar days on the wall clock of the given zone, also across a
	 * daylight-saving change. Window 30 days, Europe/Berlin.
	 */
	public function testLeavesAtAcrossDaylightSaving(): void
	{
		$zone = new DateTimeZone('Europe/Berlin');
		$at = fn (string $instant): int => (new DateTimeImmutable($instant))->getTimestamp();
		$ending = fn (string $date, string $end): Event => Event::fromArray(koyomi_raw_event(['id' => 'dst', 'startDate' => $date, 'startTime' => '02:00', 'endTime' => $end]));

		$normal = $ending('2027-02-25', '02:30');
		$spring = $ending('2027-02-26', '02:30');
		$autumn = $ending('2027-10-01', '02:30');

		$this->assertSame($at('2027-03-27 02:30:00+01:00'), Expiry::leavesAt($normal, $zone, 30), 'no clock change in between: the same wall time');
		$this->assertSame($at('2027-03-28 03:30:00+02:00'), Expiry::leavesAt($spring, $zone, 30), 'spring: 02:30 does not exist on 28 March, PHP moves it forward');
		$this->assertSame($at('2027-10-31 02:30:00+02:00'), Expiry::leavesAt($autumn, $zone, 30), 'autumn: the first of the two 02:30');
		$this->assertSame($at('2027-04-19 11:00:00+00:00'), Expiry::leavesAt($ending('2027-03-20', '12:00'), new DateTimeZone('UTC'), 30), 'counted in the given zone: no daylight saving in UTC');
		$this->assertSame($at('2027-03-20 12:00:00+01:00'), Expiry::leavesAt($ending('2027-03-20', '12:00'), $zone, 0), 'window 0: the end itself');

		// leaving() reports exactly that moment while it is ahead, and nothing once it has come.
		$this->assertSame($at('2027-03-28 03:30:00+02:00'), Expiry::leaving([$spring], new DateTimeImmutable('2027-03-28 01:55', $zone), 30), 'spring: leaving() agrees with leavesAt()');
		$this->assertSame($at('2027-10-31 02:30:00+02:00'), Expiry::leaving([$autumn], (new DateTimeImmutable('2027-10-31 02:15:00+02:00'))->setTimezone($zone), 30), 'autumn: leaving() agrees with leavesAt()');
		$this->assertSame(null, Expiry::leaving([$autumn], (new DateTimeImmutable('2027-10-31 02:15:00+01:00'))->setTimezone($zone), 30), 'autumn: after the first 02:30 nothing is left to leave');
	}

	public function testSuite(): void
	{
		$zone = new DateTimeZone('Europe/Berlin');
		$now = new DateTimeImmutable('2027-03-10 12:00', $zone);
		$ts = fn (string $s) => (new DateTimeImmutable($s, $zone))->getTimestamp();

		$ongoing = Event::fromArray(koyomi_raw_event(['id' => 'a', 'allDay' => true, 'startDate' => '2027-03-08', 'endDate' => '2027-03-11']));
		$zoom = Event::fromArray(koyomi_raw_event(['id' => 'b', 'startDate' => '2027-03-11', 'startTime' => '19:00', 'endTime' => '20:30']));
		$past = Event::fromArray(koyomi_raw_event(['id' => 'c', 'startDate' => '2027-02-01']));

		$this->assertSame(null, Expiry::next([], $now), 'no events');
		$this->assertSame(null, Expiry::next([$past], $now), 'only past events');
		$this->assertSame($ts('2027-03-12 00:00'), Expiry::next([$ongoing, $past], $now), 'ongoing all-day: its exclusive end');
		$this->assertSame(1804788000, Expiry::next([$ongoing, $zoom, $past], $now), 'the next start wins (fixture example)');
		$this->assertSame($ts('2027-03-11 19:00'), Expiry::next([$zoom], $now), 'upcoming: start');
		$this->assertSame($ts('2027-03-11 20:30'), Expiry::next([$zoom], new DateTimeImmutable('2027-03-11 19:00', $zone)), 'start == now is not future: end');
		$this->assertSame(null, Expiry::next([$zoom], new DateTimeImmutable('2027-03-11 20:30', $zone)), 'end == now: nothing left');
		$this->assertSame(null, Expiry::next(['junk', null], $now), 'non-events ignored');

		// leaving(): the moment an event drops out of a window of N days after its end.
		// $past ended 2027-02-01 20:00, 36 days and 16 hours before $now.
		$this->assertSame($ts('2027-03-13 20:00'), Expiry::leaving([$past], $now, 40), 'past event: leaves 40 days after its end');
		$this->assertSame(null, Expiry::leaving([$past], $now, 30), 'already outside the window: nothing');
		$this->assertSame($ts('2027-03-11 20:30'), Expiry::leaving([$ongoing, $zoom], $now, 0), 'window 0: the earliest end');
		$this->assertSame($ts('2027-03-13 20:00'), Expiry::leaving([$zoom, $past, 'junk'], $now, 40), 'the earliest wins, non-events ignored');
		// midnight(): the next local midnight, also on the days the clocks change.
		$this->assertSame($ts('2027-03-11 00:00'), Expiry::midnight($now), 'next midnight');
		$this->assertSame($ts('2027-03-11 00:00'), Expiry::midnight(new DateTimeImmutable('2027-03-10 00:00', $zone)), 'at midnight: the next one');
		$this->assertSame($ts('2027-03-29 00:00'), Expiry::midnight(new DateTimeImmutable('2027-03-28 12:00', $zone)), 'spring change day: 23 hours later');
		$this->assertSame($ts('2027-11-01 00:00'), Expiry::midnight(new DateTimeImmutable('2027-10-31 12:00', $zone)), 'autumn change day: 25 hours long');
	}
}

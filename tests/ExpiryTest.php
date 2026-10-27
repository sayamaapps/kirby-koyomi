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

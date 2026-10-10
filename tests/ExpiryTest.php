<?php
/**
 * Expiry::next(): the earliest future start or end decides when a cached
 * list goes stale.
 *   php tests/ExpiryTest.php
 */
require __DIR__ . '/lib/expect.php';
foreach (['I18n', 'Normalize', 'Time', 'Status', 'Attendance', 'Availability', 'Venue', 'Organizer', 'Event', 'Expiry'] as $class) {
    require_once __DIR__ . '/../src/' . $class . '.php';
}

use Koyomi\Event;
use Koyomi\Expiry;

koyomi_test('Koyomi Expiry', function () {
    $zone = new DateTimeZone('Europe/Berlin');
    $now = new DateTimeImmutable('2027-03-10 12:00', $zone);
    $ts = fn (string $s) => (new DateTimeImmutable($s, $zone))->getTimestamp();

    $ongoing = Event::fromArray(koyomi_raw_event(['id' => 'a', 'allDay' => true, 'startDate' => '2027-03-08', 'endDate' => '2027-03-11']));
    $zoom = Event::fromArray(koyomi_raw_event(['id' => 'b', 'startDate' => '2027-03-11', 'startTime' => '19:00', 'endTime' => '20:30']));
    $past = Event::fromArray(koyomi_raw_event(['id' => 'c', 'startDate' => '2027-02-01']));

    expect(null, Expiry::next([], $now), 'no events');
    expect(null, Expiry::next([$past], $now), 'only past events');
    expect($ts('2027-03-12 00:00'), Expiry::next([$ongoing, $past], $now), 'ongoing all-day: its exclusive end');
    expect(1804788000, Expiry::next([$ongoing, $zoom, $past], $now), 'the next start wins (fixture example)');
    expect($ts('2027-03-11 19:00'), Expiry::next([$zoom], $now), 'upcoming: start');
    expect($ts('2027-03-11 20:30'), Expiry::next([$zoom], new DateTimeImmutable('2027-03-11 19:00', $zone)), 'start == now is not future: end');
    expect(null, Expiry::next([$zoom], new DateTimeImmutable('2027-03-11 20:30', $zone)), 'end == now: nothing left');
    expect(null, Expiry::next(['junk', null], $now), 'non-events ignored');
});

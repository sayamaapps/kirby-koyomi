<?php
/**
 * The shared core without Kirby: Normalize, Time (zones, DST), Venue,
 * Organizer and Event::fromArray() with every end-time rule, all-day
 * exclusivity, past/ongoing, attendance, series and toArray().
 *   php tests/EventTest.php
 */
require __DIR__ . '/lib/expect.php';
foreach (['I18n', 'Normalize', 'Time', 'Status', 'Attendance', 'Availability', 'Venue', 'Organizer', 'Event'] as $class) {
    require_once __DIR__ . '/../src/' . $class . '.php';
}

use Koyomi\Attendance;
use Koyomi\Event;
use Koyomi\Normalize;
use Koyomi\Organizer;
use Koyomi\Status;
use Koyomi\Time;
use Koyomi\Venue;

koyomi_test('Koyomi Event', function () {
    $berlin = new DateTimeZone('Europe/Berlin');
    $at = fn (string $s, string $zone = 'Europe/Berlin') => new DateTimeImmutable($s, new DateTimeZone($zone));
    $iso = fn (DateTimeImmutable $d) => $d->format('Y-m-d\TH:i:sP');

    // Normalize
    expect(true, Normalize::bool('true'), 'bool "true"');
    expect(false, Normalize::bool('false'), 'bool "false"');
    expect(true, Normalize::bool('On'), 'bool "On"');
    expect(false, Normalize::bool(null), 'bool null');
    expect(['Retreat', 'Meditation'], Normalize::list('Retreat, Meditation, , retreat'), 'list: trimmed, empty and case-duplicates dropped');
    expect(['de', 'cs'], Normalize::languages('DE, cs, xx-toolongvalue1, 1'), 'languages lower-cased, invalid dropped');
    expect(120.5, Normalize::amount('120,50'), 'amount German decimal comma');
    expect(1200.5, Normalize::amount('1.200,50'), 'amount German thousands');
    expect(1200.5, Normalize::amount('1,200.50'), 'amount English thousands');
    expect(380.0, Normalize::amount('380'), 'amount integer string');
    expect(null, Normalize::amount('-5'), 'negative amount');
    expect(null, Normalize::amount('free'), 'text amount');
    expect('', Normalize::url('javascript:alert(1)'), 'javascript: URL dropped');
    expect('https://a.example/x?y=1', Normalize::url(' https://a.example/x?y=1 '), 'https URL kept, trimmed');
    expect('', Normalize::email('nope'), 'invalid email dropped');
    expect(2, Normalize::positiveInt('2'), 'positive int');
    expect(null, Normalize::positiveInt('0'), 'zero is not positive');
    expect("a\nb", Normalize::string("a\r\nb\x07"), 'CRLF → LF, control chars removed');

    // Time
    holds(Time::isValidZone('Europe/Prague') && Time::isValidZone('UTC'), 'valid zones');
    holds(!Time::isValidZone('CET+1') && !Time::isValidZone(''), 'invalid zones');
    expect('Europe/Berlin', Time::zone('Mars/Base')->getName(), 'invalid zone → fallback');
    expect('UTC', Time::zone('nope', 'also-nope')->getName(), 'invalid fallback → UTC');
    expect(null, Time::wall('2027-02-30', '10:00', $berlin), 'impossible date');
    expect(null, Time::wall('2027-03-01', '25:00', $berlin), 'impossible time');
    expect('2027-03-01T09:05:00+01:00', $iso(Time::wall('2027-03-01 00:00:00', '9:05', $berlin)), 'Kirby date string, short hour');
    expect('2027-07-01T18:00:00+02:00', $iso(Time::wall('2027-07-01', '18:00:00', $berlin)), 'summer offset');
    expect('2027-03-28T03:30:00+02:00', $iso(Time::wall('2027-03-28', '02:30', $berlin)), 'spring-forward gap moves forward');
    expect('2027-10-31T02:30:00+01:00', $iso(Time::wall('2027-10-31', '02:30', $berlin)), 'autumn overlap takes standard time');
    expect('2027-02-20T20:00:00+01:00', $iso(Time::parse('2027-02-20 20:00:00', $berlin)), 'parse datetime');
    expect('2027-02-20T00:00:00+01:00', $iso(Time::parse('2027-02-20', $berlin)), 'parse date');
    expect(null, Time::parse('next week', $berlin), 'parse junk');
    expect('20270312T170000Z', Time::utc(Time::wall('2027-03-12', '18:00', $berlin)), 'UTC form');
    expect('20270312T180000', Time::local(Time::wall('2027-03-12', '18:00', $berlin)), 'local form');

    // Venue
    $venue = Venue::fromArray(koyomi_raw_venue(['country' => 'de', 'mapImage' => 'https://example.test/map.jpg', 'mapImageAlt' => 'Map', 'showMap' => 'false']));
    expect('DE', $venue->country, 'country upper-cased');
    expect('Seminarweg 1, 12345 Musterstadt, DE', $venue->addressLine(), 'address line');
    expect('Seminarhaus Beispielhof, Seminarweg 1, 12345 Musterstadt, DE', $venue->label(), 'label');
    expect('https://www.openstreetmap.org/?mlat=47.856&mlon=12.128#map=17/47.856/12.128', $venue->mapLink(), 'OSM link from coordinates');
    expect('', $venue->toArray()['mapImage'], 'map image hidden unless showMap');
    $noCoords = Venue::fromArray(['name' => 'Kulturhaus', 'street' => 'Národní 1', 'postalCode' => '110 00', 'city' => 'Praha', 'country' => 'CZ', 'lat' => '999']);
    expect(null, $noCoords->lat, 'out-of-range latitude dropped');
    expect('https://www.openstreetmap.org/search?query=N%C3%A1rodn%C3%AD%201%2C%20110%2000%20Praha%2C%20CZ', $noCoords->mapLink(), 'OSM search link from address');
    expect('', Venue::fromArray(['name' => 'Nowhere'])->mapLink(), 'no address, no link');
    expect('https://maps.example/x', Venue::fromArray(['name' => 'X', 'mapLink' => 'https://maps.example/x'])->mapLink(), 'configured map link wins');
    expect('12', Venue::coordinate(12.0), 'coordinate trailing zeros');
    expect('-0.5', Venue::coordinate(-0.5), 'negative coordinate');

    // Organizer
    $org = Organizer::fromArray(['id' => 'org-x', 'name' => 'X', 'kind' => 'Person', 'email' => 'bad', 'website' => 'ftp://x']);
    expect('person', $org->kind, 'kind case-insensitive');
    expect('', $org->email, 'invalid email dropped');
    expect('', $org->link(), 'non-http website dropped, no url');

    // Event: a timed single-day event
    $e = Event::fromArray(koyomi_raw_event());
    expect('2027-03-12T18:00:00+01:00', $iso($e->start), 'start');
    expect('2027-03-12T20:00:00+01:00', $iso($e->end), 'end');
    expect(false, $e->allDay, 'timed');
    expect(true, $e->hasEndTime, 'has end time');
    expect(false, $e->isMultiDay(), 'single day');
    expect(Attendance::OFFLINE, $e->attendance, 'default attendance offline');
    expect(Status::SCHEDULED, $e->status, 'default status');
    expect('EUR', $e->currency, 'default currency');
    expect(null, $e->price, 'no price');
    expect('https://example.test/events/test-event.ics', $e->icsUrl(), 'ics url');

    // Past / ongoing / upcoming at the boundaries (end is exclusive).
    expect(true, $e->isUpcoming($at('2027-03-12 17:59')), 'upcoming before start');
    expect(true, $e->isOngoing($at('2027-03-12 18:00')), 'ongoing at start');
    expect(false, $e->isPast($at('2027-03-12 19:59:59')), 'not past one second before end');
    expect(true, $e->isPast($at('2027-03-12 20:00')), 'past at end');
    expect(true, $e->isPast($at('2027-03-12 19:00', 'UTC')), 'past compares instants across zones');

    // No end time: default duration; option 0 → zero length.
    $e = Event::fromArray(koyomi_raw_event(['endTime' => '']));
    expect('2027-03-12T20:00:00+01:00', $iso($e->end), 'default duration 120 min');
    expect(false, $e->hasEndTime, 'no end time');
    $e = Event::fromArray(koyomi_raw_event(['endTime' => '']), ['defaultDuration' => 45]);
    expect('2027-03-12T18:45:00+01:00', $iso($e->end), 'configured duration');

    // End time before start time without end date: next day (concert until 01:00).
    $e = Event::fromArray(koyomi_raw_event(['startTime' => '20:00', 'endTime' => '01:00']));
    expect('2027-03-13T01:00:00+01:00', $iso($e->end), 'overnight end');
    // Ending after midnight touches two calendar days (ending AT midnight does not, see below).
    expect(true, $e->isMultiDay(), 'overnight until 01:00 is multi-day');
    expect('2027-03-13', $e->lastDay()->format('Y-m-d'), 'overnight lastDay is the 13th');
    $e = Event::fromArray(koyomi_raw_event(['startTime' => '20:00', 'endTime' => '00:00']));
    expect('2027-03-13T00:00:00+01:00', $iso($e->end), 'ends at midnight');
    expect('2027-03-12', $e->lastDay()->format('Y-m-d'), 'midnight end stays on the start day');
    // Explicit end date before the start: falls back to the default duration.
    $e = Event::fromArray(koyomi_raw_event(['endDate' => '2027-03-11', 'endTime' => '10:00']));
    expect('2027-03-12T20:00:00+01:00', $iso($e->end), 'end before start → default duration');
    expect(false, $e->hasEndTime, 'end before start → no end time shown');

    // Multi-day timed retreat.
    $e = Event::fromArray(koyomi_raw_event(['endDate' => '2027-03-14', 'endTime' => '13:00']));
    expect('2027-03-14T13:00:00+01:00', $iso($e->end), 'multi-day end');
    expect(true, $e->isMultiDay(), 'multi-day');
    expect('2027-03-14', $e->lastDay()->format('Y-m-d'), 'last day');
    // Multi-day without end time: until the end of the last day.
    $e = Event::fromArray(koyomi_raw_event(['endDate' => '2027-03-14', 'endTime' => '']));
    expect('2027-03-15T00:00:00+01:00', $iso($e->end), 'multi-day without end time ends at midnight after the last day');

    // All-day: exclusive end, DST-safe.
    $e = Event::fromArray(koyomi_raw_event(['allDay' => 'true', 'startDate' => '2027-03-27', 'endDate' => '2027-03-28']));
    expect(true, $e->allDay, 'all day');
    expect('2027-03-27T00:00:00+01:00', $iso($e->start), 'all-day start midnight');
    expect('2027-03-29T00:00:00+02:00', $iso($e->end), 'all-day end exclusive across DST');
    expect('2027-03-28', $e->lastDay()->format('Y-m-d'), 'all-day last day inclusive');
    expect(false, $e->isPast($at('2027-03-28 23:59')), 'all-day still running on the last evening');
    $e = Event::fromArray(koyomi_raw_event(['startTime' => '', 'endTime' => '']));
    expect(true, $e->allDay, 'empty start time means all day');
    expect('2027-03-13T00:00:00+01:00', $iso($e->end), 'single all-day ends next midnight');
    $e = Event::fromArray(koyomi_raw_event(['allDay' => true, 'endDate' => '2027-03-01']));
    expect('2027-03-13T00:00:00+01:00', $iso($e->end), 'all-day end date before start → one day');

    // Zone: stored wall time + zone.
    $e = Event::fromArray(koyomi_raw_event(['timezone' => 'Europe/Prague', 'startDate' => '2027-07-01']));
    expect('Europe/Prague', $e->timezone, 'zone kept');
    expect('2027-07-01T18:00:00+02:00', $iso($e->start), 'summer offset at output');
    $e = Event::fromArray(koyomi_raw_event(['timezone' => 'Nowhere']), ['timezone' => 'America/New_York']);
    expect('America/New_York', $e->timezone, 'invalid zone → configured default');
    expect('2027-03-12T18:00:00-05:00', $iso($e->start), 'New York still standard time on 12 March (US DST starts 14 March 2027)');

    // Invalid start
    throws(InvalidArgumentException::class, fn () => Event::fromArray(koyomi_raw_event(['startDate' => ''])), 'no start date');
    throws(InvalidArgumentException::class, fn () => Event::fromArray(koyomi_raw_event(['startDate' => '2027-13-01'])), 'impossible start date');

    // Status, price, attendance, misc
    $e = Event::fromArray(koyomi_raw_event([
        'status' => 'rescheduled', 'previousStart' => '2027-02-20 20:00:00', 'statusNote' => ' New date ',
        'isFree' => 'false', 'price' => '380', 'currency' => 'czk', 'priceNote' => 'Early bird',
        'availability' => 'LIMITED', 'bookingUrl' => 'https://booking.example/x', 'attendance' => 'mixed',
        'onlinePlatform' => 'Zoom', 'onlineUrl' => 'https://zoom.us/j/SECRET', 'venue' => koyomi_raw_venue(),
        'organizers' => [['id' => 'org-a', 'name' => 'A', 'email' => 'a@example.org'], new Organizer('org-b', 'B'), 'junk'],
        'categories' => 'Retreat, Meditation', 'languages' => 'de, cs', 'featured' => 'true',
    ]));
    expect('2027-02-20T20:00:00+01:00', $iso($e->previousStart), 'previous start kept for rescheduled');
    expect('New date', $e->statusNote, 'status note trimmed');
    expect(380.0, $e->price, 'price');
    expect('CZK', $e->currency, 'currency upper-cased');
    expect('limited', $e->availability, 'availability normalized');
    expect('mixed', $e->effectiveAttendance(), 'mixed');
    holds($e->isOnline() && $e->isInPerson(), 'mixed is online and in person');
    expect('venue-sonnenhof', $e->physicalVenue()?->id, 'venue when in person');
    expect(['org-a', 'org-b'], array_map(fn ($o) => $o->id, $e->organizers), 'organizers from arrays and objects, junk ignored');
    expect(['Retreat', 'Meditation'], $e->categories, 'categories');
    expect(['de', 'cs'], $e->languages, 'languages');
    expect(true, $e->featured, 'featured');
    $e = Event::fromArray(koyomi_raw_event(['status' => 'scheduled', 'previousStart' => '2027-02-20 20:00']));
    expect(null, $e->previousStart, 'previous start dropped unless rescheduled');
    $e = Event::fromArray(koyomi_raw_event(['isFree' => 'true', 'price' => '50']));
    expect(null, $e->price, 'free events have no price');
    expect('EUR', Event::fromArray(koyomi_raw_event(['currency' => 'euro']))->currency, 'invalid currency → default');
    $moved = Event::fromArray(koyomi_raw_event(['status' => 'movedOnline', 'attendance' => 'offline', 'venue' => koyomi_raw_venue()]));
    expect('online', $moved->effectiveAttendance(), 'moved online is online');
    expect(null, $moved->physicalVenue(), 'moved online: no physical venue');
    expect(null, $moved->toArray()['venue'], 'moved online: venue not in public array');
    expect('scheduled', Event::fromArray(koyomi_raw_event(['status' => 'soon']))->status, 'unknown status');

    // Series: self added, sorted, parts
    $e = Event::fromArray(koyomi_raw_event([
        'id' => 'evt-2', 'title' => 'Course 2', 'startDate' => '2027-04-08',
        'series' => [
            ['id' => 'evt-3', 'title' => 'Course 3', 'url' => 'https://example.test/events/c3', 'startDate' => '2027-04-15', 'startTime' => '10:00'],
            ['id' => 'evt-1', 'title' => 'Course 1', 'url' => 'https://example.test/events/c1', 'startDate' => '2027-04-01', 'startTime' => '10:00'],
            ['id' => 'evt-2', 'title' => 'ignored self copy', 'startDate' => '2000-01-01'],
            ['id' => 'evt-x', 'title' => 'no date'],
        ],
    ]));
    expect(['evt-1', 'evt-2', 'evt-3'], array_column($e->series, 'id'), 'series sorted, self included once, undated dropped');
    expect([false, true, false], array_column($e->series, 'current'), 'current flag');
    expect([1, 2, 3], array_column($e->series, 'part'), 'parts by position');
    expect(2, $e->seriesPart, 'own part by position');
    expect(3, $e->seriesTotal(), 'total');
    expect('Course 2', $e->series[1]['title'], 'self uses its own title');
    $e = Event::fromArray(koyomi_raw_event(['seriesPart' => '5', 'series' => [['id' => 'a', 'startDate' => '2027-01-01', 'part' => '4']]]));
    expect([4, 5], array_column($e->series, 'part'), 'explicit parts win');
    expect(5, $e->seriesPart, 'explicit own part');
    $e = Event::fromArray(koyomi_raw_event(['seriesPart' => '2']));
    expect([[], null, 0], [$e->series, $e->seriesPart, $e->seriesTotal()], 'no series: part ignored');

    // toArray: shape and privacy
    $e = Event::fromArray(koyomi_raw_event([
        'onlineUrl' => 'https://zoom.us/j/SECRET-123', 'attendance' => 'online', 'onlinePlatform' => 'Zoom',
        'allDay' => true, 'endDate' => '2027-03-14',
    ]));
    $array = $e->toArray();
    expect(['id', 'title', 'url', 'start', 'end', 'firstDay', 'lastDay', 'allDay', 'hasEndTime', 'multiDay', 'timezone',
        'description', 'image', 'imageAlt', 'attendance', 'effectiveAttendance', 'onlinePlatform', 'venue', 'status',
        'previousStart', 'statusNote', 'isFree', 'price', 'currency', 'priceNote', 'availability', 'bookingUrl', 'bookingLabel',
        'organizers', 'categories', 'languages', 'featured', 'series', 'seriesPart', 'seriesTotal', 'icsUrl', 'modified'], array_keys($array), 'toArray keys');
    expect(['2027-03-12', '2027-03-15', '2027-03-14'], [$array['start'], $array['end'], $array['lastDay']], 'all-day dates, end exclusive, lastDay inclusive');
    holds(!str_contains(json_encode($array), 'SECRET'), 'onlineUrl never in toArray');
    holds(!property_exists($e, 'onlineUrl'), 'Event has no onlineUrl property');
});

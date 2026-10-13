<?php
/**
 * Ics (RFC 5545 writer): escape, fold, render, UID and SEQUENCE, the full
 * VEVENT of the reference retreat line by line (SPEC §8.1), all-day, UTC,
 * status mapping, LOCATION rules, ORGANIZER, the language option and the
 * calendar header, plus the privacy check for onlineUrl.
 *   php tests/IcsTest.php
 */
require __DIR__ . '/lib/expect.php';
koyomi_require_core();

use Koyomi\Event;
use Koyomi\I18n;
use Koyomi\Ics;

/** The reference retreat of SPEC §8 (start 2027-03-12 18:00 Europe/Berlin). */
function ics_test_retreat(array $override = []): Event
{
    return Event::fromArray(koyomi_raw_event($override + [
        'id' => 'evt-retreat', 'title' => 'Silent Retreat', 'url' => 'https://example.test/events/retreat',
        'endDate' => '2027-03-14', 'endTime' => '13:00',
        'description' => 'Three days of silence.', 'attendance' => 'mixed', 'venue' => koyomi_raw_venue(),
        'onlinePlatform' => 'Zoom', 'categories' => 'Retreat, Meditation', 'languages' => 'de, cs',
        'price' => '380', 'availability' => 'limited', 'bookingUrl' => 'https://booking.example/retreat',
        'organizers' => [['id' => 'org-anna', 'name' => 'Anna Beispiel', 'kind' => 'person', 'website' => 'https://anna.example']],
        'image' => 'https://example.test/media/cover.jpg', 'modified' => 1791000000,
    ]));
}

/** The reference "now": 2027-03-10 12:00 Berlin = 11:00Z. */
function ics_test_now(): DateTimeImmutable
{
    return new DateTimeImmutable('2027-03-10 12:00', new DateTimeZone('Europe/Berlin'));
}

/** Options that make the output deterministic. */
function ics_test_options(array $extra = []): array
{
    return $extra + ['host' => 'example.test', 'now' => ics_test_now(), 'language' => 'en'];
}

/** Unfolds a rendered document back into its content lines (no trailing empty element). */
function ics_test_unfold(string $ics): array
{
    return explode("\r\n", rtrim(str_replace("\r\n ", '', $ics), "\r\n"));
}

/** A minimal event whose only varying part is `modified`. */
function koyomi_test_event_modified(int $modified): Event
{
    return Event::fromArray(koyomi_raw_event(['id' => 'evt-seq', 'modified' => $modified]));
}

/** The first unfolded line that starts with $prefix, or null. */
function ics_test_line(array $lines, string $prefix): ?string
{
    foreach ($lines as $line) {
        if (str_starts_with($line, $prefix)) {
            return $line;
        }
    }
    return null;
}

/** The exact VTIMEZONE of SPEC §8.2 for Europe/Berlin (window of the retreat). */
function ics_test_berlin_timezone(): array
{
    return [
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
    ];
}

/** The full VEVENT of $retreat, unfolded, exactly as SPEC §8.1 lists it. */
function ics_test_retreat_vevent(): array
{
    return [
        'BEGIN:VEVENT',
        'UID:evt-retreat@example.test',
        'DTSTAMP:20270310T110000Z',
        'LAST-MODIFIED:20261003T040000Z',
        'SEQUENCE:3552720',
        'DTSTART;TZID=Europe/Berlin:20270312T180000',
        'DTEND;TZID=Europe/Berlin:20270314T130000',
        'SUMMARY:Silent Retreat',
        'DESCRIPTION:Three days of silence.\\n\\nMore information: https://example.test/events/retreat',
        'LOCATION:Seminarhaus Beispielhof\\, Musterstraße 1\\, 12345 Musterstadt\\, DE / Online (Zoom)',
        'GEO:48.123;11.456',
        'URL:https://example.test/events/retreat',
        'STATUS:CONFIRMED',
        'TRANSP:OPAQUE',
        'CATEGORIES:Retreat,Meditation',
        'END:VEVENT',
    ];
}

koyomi_test('Koyomi Ics', function () {
    $retreat = ics_test_retreat();

    // escape(): each special character, and the order of the replacements.
    expect('\\\\', Ics::escape('\\'), 'backslash doubled');
    expect('\\;', Ics::escape(';'), 'semicolon escaped');
    expect('\\,', Ics::escape(','), 'comma escaped');
    expect('\\n', Ics::escape("\r\n"), 'CRLF becomes backslash-n');
    expect('\\n', Ics::escape("\r"), 'bare CR becomes backslash-n');
    expect('\\n', Ics::escape("\n"), 'bare LF becomes backslash-n');
    expect('n', Ics::escape('n'), 'the letter n is not touched');
    expect('a\\\\b\\;c\\,d\\ne', Ics::escape("a\\b;c,d\ne"), 'mixed text escaped once');
    expect('\\\\\\;', Ics::escape('\\;'), 'backslash before semicolon: no double escaping');
    expect('Lüge\\, nein', Ics::escape('Lüge, nein'), 'umlauts untouched, comma escaped');

    // fold(): octets, not characters; continuation = CRLF + space.
    $ascii = 'DESCRIPTION:' . str_repeat('a', 100);
    expect(112, strlen($ascii), 'fixture is 112 octets');
    expect(
        'DESCRIPTION:' . str_repeat('a', 63) . "\r\n " . str_repeat('a', 37),
        Ics::fold($ascii),
        'ASCII 112 octets: 75 + 37',
    );
    $umlaut = 'SUMMARY:' . str_repeat('ä', 40);
    expect(88, strlen($umlaut), 'fixture is 88 octets');
    expect(
        'SUMMARY:' . str_repeat('ä', 33) . "\r\n " . str_repeat('ä', 7),
        Ics::fold($umlaut),
        'ä line: first chunk 74 octets (no split inside ä), continuation 15 with the space',
    );
    expect(str_repeat('b', 75), Ics::fold(str_repeat('b', 75)), 'exactly 75 octets unchanged');
    expect(
        str_repeat('b', 75) . "\r\n b",
        Ics::fold(str_repeat('b', 76)),
        '76 octets: one more chunk',
    );
    expect('short line', Ics::fold('short line'), 'short line unchanged');
    $emoji = str_repeat('a', 73) . "\u{1F600}";
    expect(77, strlen($emoji), 'fixture: 73 ASCII + one 4-byte emoji');
    expect(
        str_repeat('a', 73) . "\r\n \u{1F600}",
        Ics::fold($emoji),
        'a 4-byte emoji is never split (cut moves back to 73)',
    );
    $emojis = Ics::fold(str_repeat("\u{1F600}", 30));
    foreach (explode("\r\n", $emojis) as $index => $physical) {
        $content = $index === 0 ? $physical : substr($physical, 1);
        holds(strlen($physical) <= 75, 'emoji physical line ≤ 75 octets');
        holds(preg_match('//u', $content) === 1, 'emoji chunk is valid UTF-8 on its own');
    }
    expect(str_repeat("\u{1F600}", 30), str_replace("\r\n ", '', $emojis), 'emoji fold unfolds to the original');

    // render(): CRLF everywhere, final CRLF, every physical line ≤ 75 octets.
    expect("A:1\r\nB:2\r\n", Ics::render(['A:1', 'B:2']), 'render joins with CRLF and ends with CRLF');
    expect('', Ics::render([]), 'render of no lines is empty');
    $document = Ics::event($retreat, ics_test_options());
    holds(str_ends_with($document, "\r\n"), 'document ends with CRLF');
    holds(preg_match('/(?<!\r)\n/', $document) === 0, 'no bare LF in the document');
    holds(preg_match('/\r(?!\n)/', $document) === 0, 'no bare CR in the document');
    foreach (explode("\r\n", $document) as $physical) {
        holds(strlen($physical) <= 75, 'every physical line ≤ 75 octets: ' . $physical);
    }

    // uid()
    expect('evt-retreat@example.test', Ics::uid($retreat, 'example.test'), 'uid of a clean id');
    $messy = ics_test_retreat(['id' => 'evt retreat/1 ä']);
    expect('evt-retreat-1--@example.test', Ics::uid($messy, 'example.test'), 'uid: space, slash and ä become "-" (one each)');
    $dots = ics_test_retreat(['id' => 'Abc.def_ghi-9']);
    expect('Abc.def_ghi-9@example.test', Ics::uid($dots, 'example.test'), 'uid: [A-Za-z0-9._-] kept');

    // sequence()
    expect(3552720, Ics::sequence($retreat), 'SEQUENCE example from SPEC §8.1');
    expect(0, Ics::sequence(koyomi_test_event_modified(0)), 'SEQUENCE 0 for modified 0');
    expect(0, Ics::sequence(koyomi_test_event_modified(1)), 'SEQUENCE 0 before the epoch');
    expect(3552730, Ics::sequence(koyomi_test_event_modified(1791000600)), 'SEQUENCE grows with modified (10 minutes later)');
    holds(Ics::sequence(koyomi_test_event_modified(1791000600)) > Ics::sequence($retreat), 'SEQUENCE is monotonic');

    // vevent(): the full VEVENT of $retreat, line by line.
    expect(
        ics_test_retreat_vevent(),
        Ics::vevent($retreat, ics_test_options()),
        'full VEVENT of the reference retreat (SPEC §8.1)',
    );
    holds(ics_test_line(Ics::vevent(koyomi_test_event_modified(0), ics_test_options()), 'LAST-MODIFIED:') === null, 'LAST-MODIFIED omitted when modified = 0');

    // calendar(): header, timezone and the complete document of $retreat.
    $header = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//SayamaApps//Koyomi 1.0//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
    ];
    $expectedDocument = array_merge(
        $header,
        ics_test_berlin_timezone(),
        ics_test_retreat_vevent(),
        ['END:VCALENDAR'],
    );
    expect(
        Ics::render($expectedDocument),
        Ics::event($retreat, ics_test_options()),
        'event() of the reference retreat: header, VTIMEZONE, VEVENT, END',
    );
    expect(
        $expectedDocument,
        ics_test_unfold(Ics::event($retreat, ics_test_options())),
        'the unfolded document matches the expected line list',
    );
    $bare = ics_test_unfold(Ics::calendar([], ics_test_options()));
    expect(array_merge($header, ['END:VCALENDAR']), $bare, 'empty calendar: header and END only');
    $withHeader = ics_test_unfold(Ics::calendar([$retreat], ics_test_options([
        'name' => 'Events, Sayama', 'description' => 'Upcoming', 'timezone' => 'Europe/Berlin', 'refresh' => 'PT6H',
    ])));
    expect(
        array_merge($header, [
            'X-WR-CALNAME:Events\\, Sayama',
            'X-WR-CALDESC:Upcoming',
            'X-WR-TIMEZONE:Europe/Berlin',
            'REFRESH-INTERVAL;VALUE=DURATION:PT6H',
            'X-PUBLISHED-TTL:PT6H',
        ]),
        array_slice($withHeader, 0, 10),
        'calendar header with X-WR-* and refresh, in SPEC order',
    );
    expect('BEGIN:VTIMEZONE', $withHeader[10], 'VTIMEZONE follows the header');
    $noWrLines = ics_test_unfold(Ics::calendar([$retreat], ics_test_options()));
    holds(ics_test_line($noWrLines, 'X-WR-') === null && ics_test_line($noWrLines, 'REFRESH-INTERVAL') === null, 'X-WR-* and refresh omitted when not given');

    // Zones: one VTIMEZONE per zone of the timed events, sorted by name.
    $prague = Event::fromArray(koyomi_raw_event([
        'id' => 'evt-foreign-zone', 'title' => 'Foreign zone', 'startDate' => '2027-03-20', 'startTime' => '20:00',
        'endTime' => '', 'timezone' => 'Europe/Prague',
    ]));
    $berlinOther = Event::fromArray(koyomi_raw_event([
        'id' => 'evt-berlin-2', 'title' => 'Berlin again', 'startDate' => '2027-11-01', 'startTime' => '10:00',
        'endTime' => '11:00', 'timezone' => 'Europe/Berlin',
    ]));
    $two = Ics::calendar([$prague, $retreat, $berlinOther], ics_test_options());
    expect(2, substr_count($two, "BEGIN:VTIMEZONE\r\n"), 'one VTIMEZONE per zone (Berlin twice → once)');
    $zoneOrder = array_values(array_filter(ics_test_unfold($two), fn (string $l) => str_starts_with($l, 'TZID:')));
    expect(['TZID:Europe/Berlin', 'TZID:Europe/Prague'], $zoneOrder, 'VTIMEZONEs sorted by zone name');
    $allDayOnly = Event::fromArray(koyomi_raw_event([
        'id' => 'evt-allday', 'startDate' => '2027-03-25', 'startTime' => '', 'endDate' => '', 'endTime' => '',
        'allDay' => 'true',
    ]));
    holds(!str_contains(Ics::calendar([$allDayOnly], ics_test_options()), 'VTIMEZONE'), 'no VTIMEZONE for an all-day-only calendar');
    $ordered = ics_test_unfold(Ics::calendar([$prague, $retreat], ics_test_options()));
    expect(['UID:evt-foreign-zone@example.test', 'UID:evt-retreat@example.test'], array_values(array_filter(
        $ordered,
        fn (string $l) => str_starts_with($l, 'UID:'),
    )), 'events keep the given order (foreign zone first, although it sorts after Berlin)');

    // All-day: VALUE=DATE, exclusive DTEND, no TZID.
    $allDay = Event::fromArray(koyomi_raw_event([
        'id' => 'evt-course', 'startDate' => '2027-03-30', 'startTime' => '', 'endDate' => '2027-03-31',
        'endTime' => '', 'allDay' => 'true',
    ]));
    $allDayLines = ics_test_unfold(Ics::event($allDay, ics_test_options()));
    expect('DTSTART;VALUE=DATE:20270330', ics_test_line($allDayLines, 'DTSTART'), 'all-day DTSTART as DATE');
    expect('DTEND;VALUE=DATE:20270401', ics_test_line($allDayLines, 'DTEND'), 'all-day DTEND is the day after the last day (month end)');
    $single = Event::fromArray(koyomi_raw_event([
        'id' => 'evt-day', 'startDate' => '2027-03-25', 'startTime' => '', 'endDate' => '', 'endTime' => '', 'allDay' => 'true',
    ]));
    expect('DTEND;VALUE=DATE:20270326', ics_test_line(ics_test_unfold(Ics::event($single, ics_test_options())), 'DTEND'), 'single all-day: DTEND = next day');
    $acrossDst = Event::fromArray(koyomi_raw_event([
        'id' => 'evt-dst', 'startDate' => '2027-03-27', 'startTime' => '', 'endDate' => '2027-03-28', 'endTime' => '', 'allDay' => 'true',
    ]));
    $dstDocument = Ics::event($acrossDst, ics_test_options());
    expect('DTEND;VALUE=DATE:20270329', ics_test_line(ics_test_unfold($dstDocument), 'DTEND'), 'all-day across the DST change: DATE values, no time');
    holds(!str_contains($dstDocument, 'TZID') && !str_contains($dstDocument, 'VTIMEZONE'), 'all-day: no TZID and no VTIMEZONE');

    // UTC zone: Z form, no VTIMEZONE.
    $utc = Event::fromArray(koyomi_raw_event([
        'id' => 'evt-utc', 'startDate' => '2027-03-12', 'startTime' => '18:00', 'endTime' => '20:00', 'timezone' => 'UTC',
    ]));
    $utcDocument = Ics::event($utc, ics_test_options());
    $utcLines = ics_test_unfold($utcDocument);
    expect('DTSTART:20270312T180000Z', ics_test_line($utcLines, 'DTSTART'), 'UTC zone: Z form');
    expect('DTEND:20270312T200000Z', ics_test_line($utcLines, 'DTEND'), 'UTC zone: Z form end');
    holds(!str_contains($utcDocument, 'TZID') && !str_contains($utcDocument, 'VTIMEZONE'), 'UTC zone: no TZID and no VTIMEZONE');

    // Status mapping (SPEC §7) and SUMMARY/DESCRIPTION prefixes.
    $cancelled = ics_test_retreat(['title' => 'Breathing Workshop', 'status' => 'cancelled', 'statusNote' => 'Venue flooded']);
    $cancelledLines = Ics::vevent($cancelled, ics_test_options());
    expect('SUMMARY:Cancelled: Breathing Workshop', ics_test_line($cancelledLines, 'SUMMARY:'), 'cancelled: SUMMARY prefix');
    expect('DESCRIPTION:Cancelled: Venue flooded\\n\\nThree days of silence.\\n\\nMore information: https://example.test/events/retreat', ics_test_line($cancelledLines, 'DESCRIPTION:'), 'cancelled: status line in DESCRIPTION');
    expect('STATUS:CANCELLED', ics_test_line($cancelledLines, 'STATUS:'), 'cancelled: STATUS');
    expect('TRANSP:TRANSPARENT', ics_test_line($cancelledLines, 'TRANSP:'), 'cancelled: TRANSP');
    $postponed = ics_test_retreat(['status' => 'postponed', 'statusNote' => 'New date follows']);
    $postponedLines = Ics::vevent($postponed, ics_test_options());
    expect('STATUS:TENTATIVE', ics_test_line($postponedLines, 'STATUS:'), 'postponed → TENTATIVE');
    expect('SUMMARY:Postponed: Silent Retreat', ics_test_line($postponedLines, 'SUMMARY:'), 'postponed: SUMMARY prefix');
    expect('TRANSP:OPAQUE', ics_test_line($postponedLines, 'TRANSP:'), 'postponed keeps the time blocked');
    $rescheduled = ics_test_retreat(['status' => 'rescheduled', 'previousStart' => '2027-02-20 18:00', 'statusNote' => 'Spring']);
    $reschedLines = Ics::vevent($rescheduled, ics_test_options());
    expect('STATUS:CONFIRMED', ics_test_line($reschedLines, 'STATUS:'), 'rescheduled → CONFIRMED');
    expect('SUMMARY:Silent Retreat', ics_test_line($reschedLines, 'SUMMARY:'), 'rescheduled: no SUMMARY prefix');
    expect('DESCRIPTION:Rescheduled: Spring\\n\\nThree days of silence.\\n\\nMore information: https://example.test/events/retreat', ics_test_line($reschedLines, 'DESCRIPTION:'), 'rescheduled: status line in DESCRIPTION');
    $scheduledLines = Ics::vevent($retreat, ics_test_options());
    holds(!str_contains(implode("\n", $scheduledLines), 'Scheduled'), 'scheduled: no status line');

    // LOCATION and GEO.
    $online = ics_test_retreat(['attendance' => 'online']);
    $onlineLines = Ics::vevent($online, ics_test_options());
    expect('LOCATION:Online (Zoom)', ics_test_line($onlineLines, 'LOCATION:'), 'online only: "Online (Zoom)", no venue address');
    holds(ics_test_line($onlineLines, 'GEO:') === null, 'online only: no GEO');
    $movedOnline = ics_test_retreat(['attendance' => 'offline', 'status' => 'movedOnline', 'onlinePlatform' => '']);
    $movedLines = Ics::vevent($movedOnline, ics_test_options());
    expect('LOCATION:Online', ics_test_line($movedLines, 'LOCATION:'), 'movedOnline: "Online", the old venue is gone');
    holds(ics_test_line($movedLines, 'GEO:') === null, 'movedOnline: no GEO');
    expect('STATUS:CONFIRMED', ics_test_line($movedLines, 'STATUS:'), 'movedOnline → CONFIRMED');
    $offlineNoVenue = ics_test_retreat(['attendance' => 'offline', 'venue' => null]);
    holds(ics_test_line(Ics::vevent($offlineNoVenue, ics_test_options()), 'LOCATION:') === null, 'no venue and not online: no LOCATION');
    $mixedNoVenue = ics_test_retreat(['venue' => null]);
    expect('LOCATION:Online (Zoom)', ics_test_line(Ics::vevent($mixedNoVenue, ics_test_options()), 'LOCATION:'), 'mixed without venue: online text only');
    expect('', Ics::location($offlineNoVenue, 'en'), 'location(): empty without venue and online');
    expect('Seminarhaus Beispielhof, Musterstraße 1, 12345 Musterstadt, DE', Ics::location(ics_test_retreat(['attendance' => 'offline', 'onlinePlatform' => '']), 'en'), 'location(): venue label');

    // ORGANIZER: only a public email, first one wins, no quotes in CN.
    $publicOrganizer = ics_test_retreat(['organizers' => [
        ['id' => 'org-hidden', 'name' => 'Hidden', 'kind' => 'person', 'email' => ''],
        ['id' => 'org-sayama', 'name' => 'Sayama', 'kind' => 'organization', 'email' => 'info@sayama.example'],
        ['id' => 'org-second', 'name' => 'Second', 'email' => 'second@sayama.example'],
    ]]);
    expect(
        'ORGANIZER;CN="Sayama":mailto:info@sayama.example',
        ics_test_line(Ics::vevent($publicOrganizer, ics_test_options()), 'ORGANIZER'),
        'ORGANIZER from the first organizer with a public email',
    );
    $quoted = ics_test_retreat(['organizers' => [['id' => 'org-q', 'name' => 'Say"ama', 'email' => 'info@sayama.example']]]);
    expect(
        'ORGANIZER;CN="Sayama":mailto:info@sayama.example',
        ics_test_line(Ics::vevent($quoted, ics_test_options()), 'ORGANIZER'),
        'double quote removed from CN',
    );
    holds(ics_test_line(Ics::vevent($retreat, ics_test_options()), 'ORGANIZER') === null, 'organizer without email: no ORGANIZER line');

    // Language option: applied for the call, previous language restored.
    I18n::setLanguage('en');
    $german = Ics::vevent($cancelled, ics_test_options(['language' => 'de']));
    expect('SUMMARY:Abgesagt: Breathing Workshop', ics_test_line($german, 'SUMMARY:'), 'de: cancelled prefix in German');
    holds(str_contains(implode("\n", $german), 'Weitere Informationen: https://example.test/events/retreat'), 'de: "More information" in German');
    expect('en', I18n::language(), 'language restored after a German call');
    I18n::setLanguage('de');
    Ics::event($cancelled, ics_test_options(['language' => 'en']));
    expect('de', I18n::language(), 'previous language (de) restored after an English call');
    I18n::setLanguage('en');
    $germanDocument = Ics::event($cancelled, ics_test_options(['language' => 'de']));
    holds(str_contains($germanDocument, "\r\nSUMMARY:Abgesagt: Breathing Workshop\r\n"), 'de: document contains the German summary');
    expect('Online (Zoom)', Ics::location($online, 'de'), 'location() uses the given language; "Online" is shared');

    // Folding at the limit (review): a continuation line of exactly 75 octets is correct, 76 is not.
    $long = 'DESCRIPTION:' . str_repeat('x', 200);                    // 212 octets
    $physicals = explode("\r\n", Ics::fold($long));
    expect([75, 75, 64], array_map('strlen', $physicals), 'fold at the limit: 75, then continuation lines of 75 (74 + space), then the rest');
    foreach (array_slice($physicals, 1) as $continuation) {
        holds(str_starts_with($continuation, ' '), 'fold: continuation lines start with one space');
    }
    expect($long, str_replace(["\r\n ", "\r\n"], '', Ics::fold($long)), 'fold: unfolding gives the original line');
    $multibyte = 'SUMMARY:' . str_repeat('ä', 100);                     // 208 octets
    $parts = explode("\r\n", Ics::fold($multibyte));
    foreach ($parts as $index => $physical) {
        holds(strlen($physical) <= 75, 'fold multibyte: physical line ≤ 75 octets');
        holds(preg_match('//u', $index === 0 ? $physical : substr($physical, 1)) === 1, 'fold multibyte: no split character');
    }
    holds(in_array(75, array_map('strlen', array_slice($parts, 1)), true), 'fold multibyte: a continuation line reaches exactly 75 octets');
    expect($multibyte, str_replace(["\r\n "], '', Ics::fold($multibyte)), 'fold multibyte: unfolding gives the original line');

    // Organizer names cannot inject lines (review): control characters leave the quoted CN parameter.
    $injected = \Koyomi\Organizer::fromArray([
        'id' => 'org-injected', 'name' => "Sayama\r\nATTENDEE:mailto:evil@example.com\nX-INJECT:1\"", 'kind' => 'organization',
        'email' => 'info@sayama.example',
    ]);
    $injectedLines = Ics::vevent(Event::fromArray(koyomi_raw_event(['organizers' => [$injected]])), ics_test_options());
    expect('ORGANIZER;CN="SayamaATTENDEE:mailto:evil@example.comX-INJECT:1":mailto:info@sayama.example', ics_test_line($injectedLines, 'ORGANIZER;'), 'organizer CN: control characters and quotes removed, one logical line');
    holds(ics_test_line($injectedLines, 'ATTENDEE') === null && ics_test_line($injectedLines, 'X-INJECT') === null, 'organizer CN: no ATTENDEE or X-INJECT line');
    $injectedDocument = Ics::event(Event::fromArray(koyomi_raw_event(['organizers' => [$injected]])), ics_test_options());
    foreach (explode("\r\n", $injectedDocument) as $physical) {
        holds(!str_starts_with($physical, 'ATTENDEE') && !str_starts_with($physical, 'X-INJECT'), 'organizer CN: no physical line starts with an injected property');
    }

    // Autumn DST overlap (review): 02:30 on 2027-10-31 in Berlin names two instants; the ICS form is UTC (the standard-time instant the page shows).
    $overlap = Event::fromArray(koyomi_raw_event(['startDate' => '2027-10-31', 'startTime' => '02:30', 'endTime' => '03:30']));
    holds(Ics::isAmbiguous($overlap->start), 'isAmbiguous: 02:30 on 2027-10-31 in Berlin');
    holds(!Ics::isAmbiguous(Event::fromArray(koyomi_raw_event(['startDate' => '2027-03-12']))->start), 'isAmbiguous: an ordinary evening is not');
    holds(!Ics::isAmbiguous(Event::fromArray(koyomi_raw_event(['startDate' => '2027-03-28', 'startTime' => '02:30', 'endTime' => '04:00']))->start), 'isAmbiguous: a spring-forward gap time is not ambiguous');
    $overlapLines = Ics::vevent($overlap, ics_test_options());
    expect('DTSTART:20271031T013000Z', ics_test_line($overlapLines, 'DTSTART'), 'ambiguous start: UTC form (01:30Z = 02:30+01:00)');
    expect('DTEND;TZID=Europe/Berlin:20271031T033000', ics_test_line($overlapLines, 'DTEND'), 'a non-ambiguous end keeps the TZID form');
    $overnight = Event::fromArray(koyomi_raw_event(['startDate' => '2027-10-30', 'startTime' => '22:00', 'endTime' => '02:30']));
    $overnightEnd = Ics::vevent($overnight, ics_test_options());
    expect('DTEND:20271031T013000Z', ics_test_line($overnightEnd, 'DTEND'), 'overnight end in the overlap: UTC form, same instant as the page (02:30+01:00)');

    // Privacy: onlineUrl never reaches any output (SPEC §0, PrivacyTest).
    $secret = Event::fromArray(koyomi_raw_event([
        'id' => 'evt-secret', 'title' => 'Zoom Session', 'attendance' => 'online', 'onlinePlatform' => 'Zoom',
        'onlineUrl' => 'https://zoom.us/j/SECRET', 'description' => 'Join us.',
    ]));
    $secretDocument = Ics::event($secret, ics_test_options());
    holds(!str_contains($secretDocument, 'SECRET'), 'onlineUrl value absent from the ICS');
    holds(!str_contains($secretDocument, 'zoom.us'), 'onlineUrl host absent from the ICS');
    holds(!str_contains(Ics::calendar([$secret], ics_test_options(['name' => 'Feed'])), 'SECRET'), 'onlineUrl absent from the feed too');
}, function () {
    I18n::setLanguage('en');
});

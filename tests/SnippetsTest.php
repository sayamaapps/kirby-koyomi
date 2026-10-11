<?php
/**
 * The presentation snippets with real Kirby (tests/lib/Fixture.php): card,
 * event, past, venue, list, filters, pagination, subscribe and layout, in
 * English and German, the inactive switch and a site override.
 *
 * Event and Venue objects come from fromArray(); no page model, Repository
 * or Filters is involved, so the test passes event/venue/events/list/
 * calendarUrl explicitly (SPEC §9).
 *   php tests/SnippetsTest.php
 */
$base = dirname(__DIR__);
if (!is_file($base . '/vendor/autoload.php')) { fwrite(STDERR, "Composer dependencies missing; run composer install first\n"); exit(1); }
require $base . '/vendor/autoload.php';
require __DIR__ . '/lib/expect.php';
require __DIR__ . '/lib/Fixture.php';

use Koyomi\Event;
use Koyomi\Venue;

/** Renders a snippet and returns its HTML as a string. */
function koyomi_snippet_html($kirby, string $name, array $data = []): string
{
    return (string) $kirby->snippet($name, $data, true);
}

/** An event from raw content with overrides (2027-03-12 18:00–20:00 Europe/Berlin by default). */
function koyomi_snippet_event(array $override = []): Event
{
    return Event::fromArray(koyomi_raw_event($override));
}

$root = KoyomiFixture::create();

koyomi_test('Koyomi snippets', function () use ($root) {
    // Booting Kirby loads the plugin, which defines the Koyomi classes used below.
    KoyomiFixture::app($root);
    $berlin = new DateTimeZone('Europe/Berlin');
    $retreatData = [
        'id' => 'evt-retreat', 'title' => 'Silent Retreat', 'url' => 'https://example.test/events/retreat',
        'endDate' => '2027-03-14', 'endTime' => '13:00',
        'description' => 'Three days of silence.', 'attendance' => 'mixed', 'venue' => koyomi_raw_venue(),
        'onlinePlatform' => 'Zoom', 'categories' => 'Retreat, Meditation', 'languages' => 'de, cs',
        'price' => '380', 'priceNote' => 'Frühbucher bis 1. Februar', 'availability' => 'limited',
        'bookingUrl' => 'https://booking.example/retreat',
        'organizers' => [['id' => 'org-anna', 'name' => 'Anna', 'kind' => 'person', 'website' => 'https://anna.example']],
        'image' => 'https://example.test/media/cover.jpg', 'imageAlt' => 'Sunrise', 'modified' => 1791000000,
        'featured' => true,
    ];
    $retreat = koyomi_snippet_event($retreatData);

    // ---- card (English) -----------------------------------------------------
    $kirby = KoyomiFixture::app($root);
    $html = koyomi_snippet_html($kirby, 'koyomi/card', ['event' => $retreat]);
    holds(str_contains($html, '<article class="koyomi-card koyomi-card--scheduled koyomi-card--featured">'), 'card: classes incl. featured');
    holds(str_contains($html, '<h3 class="koyomi-card-title"><a href="https://example.test/events/retreat">Silent Retreat</a></h3>'), 'card: title link at level 3');
    holds(str_contains($html, '<time datetime="2027-03-12T18:00:00+01:00">Friday, 12 March 2027, 18:00 – Sunday, 14 March 2027, 13:00</time>'), 'card: <time datetime> with offset and range text');
    holds(str_contains($html, '<img class="koyomi-card-image" src="https://example.test/media/cover.jpg" alt="Sunrise" loading="lazy">'), 'card: image');
    holds(str_contains($html, '<p class="koyomi-card-place">Seminarhaus Beispielhof, Musterstadt · Hybrid: in person and online via Zoom</p>'), 'card: place and attendance');
    holds(str_contains($html, '<p class="koyomi-card-price">€380 · Only a few places left</p>'), 'card: price and availability as text');
    holds(str_contains($html, '<p class="koyomi-card-excerpt">Three days of silence.</p>'), 'card: excerpt');
    holds(!str_contains($html, 'koyomi-card-status') && !str_contains($html, 'koyomi-card-badge'), 'card: scheduled and upcoming show no status or badge');

    $html = koyomi_snippet_html($kirby, 'koyomi/card', ['event' => $retreat, 'level' => 4]);
    holds(str_contains($html, '<h4 class="koyomi-card-title">'), 'card: level 4 heading');

    $cancelled = koyomi_snippet_event([
        'id' => 'evt-cancelled', 'title' => 'Breathing Workshop', 'url' => 'https://example.test/events/workshop-cancelled',
        'startDate' => '2027-03-25', 'startTime' => '', 'endTime' => '', 'allDay' => true,
        'status' => 'cancelled', 'statusNote' => 'Due to illness.',
    ]);
    $html = koyomi_snippet_html($kirby, 'koyomi/card', ['event' => $cancelled]);
    holds(str_contains($html, '<article class="koyomi-card koyomi-card--cancelled">'), 'card cancelled: modifier');
    holds(str_contains($html, '<p class="koyomi-card-status"><strong>Cancelled</strong></p>'), 'card cancelled: status as text');
    holds(str_contains($html, '<time datetime="2027-03-25">Thursday, 25 March 2027</time>'), 'card all-day: date only in datetime');

    $ongoing = koyomi_snippet_event([
        'id' => 'evt-ongoing', 'title' => 'Spring Retreat', 'startDate' => '2027-03-08', 'endDate' => '2027-03-11',
        'startTime' => '', 'endTime' => '', 'allDay' => true,
    ]);
    $html = koyomi_snippet_html($kirby, 'koyomi/card', ['event' => $ongoing]);
    holds(str_contains($html, 'koyomi-card--ongoing') && str_contains($html, '<p class="koyomi-card-badge">Happening now</p>'), 'card ongoing: "Happening now" as text');

    $past = koyomi_snippet_event([
        'id' => 'evt-past', 'title' => 'Past Workshop', 'startDate' => '2027-02-01', 'startTime' => '10:00', 'endTime' => '17:00',
    ]);
    $html = koyomi_snippet_html($kirby, 'koyomi/card', ['event' => $past]);
    holds(str_contains($html, 'koyomi-card--past') && str_contains($html, '<p class="koyomi-card-badge">Has taken place</p>'), 'card past: "Has taken place" as text');

    $concert = koyomi_snippet_event([
        'id' => 'evt-concert', 'title' => 'Concert in Prague', 'startDate' => '2027-03-20', 'startTime' => '20:00', 'endTime' => '',
        'timezone' => 'Europe/Prague', 'status' => 'rescheduled', 'previousStart' => '2027-02-20 20:00:00',
        'isFree' => false, 'price' => '450', 'currency' => 'CZK',
    ]);
    $html = koyomi_snippet_html($kirby, 'koyomi/card', ['event' => $concert]);
    holds(str_contains($html, '<time datetime="2027-03-20T20:00:00+01:00">Saturday, 20 March 2027, 20:00 (Europe/Prague)</time>'), 'card: foreign zone hint and offset');
    holds(str_contains($html, '<p class="koyomi-card-status"><strong>Rescheduled</strong></p>'), 'card: rescheduled as text');
    holds(str_contains($html, '<p class="koyomi-card-price">CZK 450</p>'), 'card: CZK money');

    // A page-less card for a non-event renders nothing.
    expect('', trim(koyomi_snippet_html($kirby, 'koyomi/card', ['event' => 'nope'])), 'card: not an event prints nothing');

    // ---- German ---------------------------------------------------------------
    $kirby = KoyomiFixture::app($root, ['koyomi.language' => 'de']);
    $html = koyomi_snippet_html($kirby, 'koyomi/card', ['event' => $retreat]);
    holds(str_contains($html, 'Freitag, 12. März 2027, 18:00 Uhr – Sonntag, 14. März 2027, 13:00 Uhr'), 'de card: German when');
    holds(str_contains($html, '<p class="koyomi-card-price">380 € · Nur noch wenige Plätze</p>'), 'de card: German money and availability');
    holds(str_contains($html, 'Läuft gerade') === false, 'de card: upcoming shows no ongoing badge');
    $html = koyomi_snippet_html($kirby, 'koyomi/card', ['event' => $ongoing]);
    holds(str_contains($html, 'Läuft gerade'), 'de card: ongoing badge');
    $html = koyomi_snippet_html($kirby, 'koyomi/card', ['event' => $cancelled]);
    holds(str_contains($html, '<strong>Abgesagt</strong>'), 'de card: cancelled');

    // ---- event (English) ------------------------------------------------------
    $kirby = KoyomiFixture::app($root);
    $html = koyomi_snippet_html($kirby, 'koyomi/event', ['event' => $retreat, 'calendarUrl' => 'https://example.test/events']);
    holds(str_contains($html, '<div class="koyomi-event koyomi-event--scheduled koyomi-event--featured">'), 'event: classes');
    holds(str_contains($html, '<dd class="koyomi-when"><time datetime="2027-03-12T18:00:00+01:00">Friday, 12 March 2027, 18:00 – Sunday, 14 March 2027, 13:00</time></dd>'), 'event: when with offset');
    holds(str_contains($html, '<p class="koyomi-venue-name"><a href="https://example.test/venues/sonnenhof">Seminarhaus Beispielhof</a></p>'), 'event: venue inside where, compact');
    holds(str_contains($html, '<p class="koyomi-attendance">Hybrid: in person and online via Zoom</p>'), 'event: attendance label');
    holds(!str_contains($html, 'koyomi-venue-directions') && !str_contains($html, 'koyomi-venue-map'), 'event: venue compact (no directions, no map)');
    holds(str_contains($html, '<dd class="koyomi-price">€380 <span class="koyomi-price-note">Frühbucher bis 1. Februar</span> <span class="koyomi-availability">Only a few places left</span></dd>'), 'event: price, note and availability');
    holds(str_contains($html, '<dd class="koyomi-languages">German, Czech</dd>'), 'event: languages');
    holds(str_contains($html, '<dd class="koyomi-categories">Retreat, Meditation</dd>'), 'event: categories');
    holds(str_contains($html, '<a class="koyomi-booking-link" href="https://booking.example/retreat" target="_blank" rel="noopener">Register<span class="koyomi-sr-only"> (opens in a new tab)</span></a>'), 'event: booking link');
    holds(str_contains($html, '<a class="koyomi-calendar-ics" href="https://example.test/events/retreat.ics" download>Download (.ics)</a>'), 'event: ics link');
    holds(str_contains($html, '<a class="koyomi-calendar-google" href="https://calendar.google.com/calendar/render?action=TEMPLATE&amp;text=Silent%20Retreat'), 'event: Google link, escaped');
    holds(str_contains($html, 'mailto:') === false, 'event: organizer without public email has no mailto');
    holds(str_contains($html, '<dt>Organizer</dt>') && str_contains($html, '<a href="https://anna.example">Anna</a>'), 'event: organizer name linked to its website');
    holds(str_contains($html, '<p class="koyomi-status') === false, 'event: scheduled has no status line');

    // Expiry: the page is not cached past the start.
    $kirby = KoyomiFixture::app($root);
    koyomi_snippet_html($kirby, 'koyomi/event', ['event' => $retreat, 'calendarUrl' => 'https://example.test/events']);
    expect(
        (new DateTimeImmutable('2027-03-12 18:00', $berlin))->getTimestamp(),
        $kirby->response()->expires(),
        'event: koyomi_expires() sets the expiry to the start'
    );

    // Public email becomes a mailto link; the organizer's website is the link.
    $sayamaEvent = koyomi_snippet_event([
        'organizers' => [['id' => 'org-sayama', 'name' => 'Sayama', 'kind' => 'organization', 'email' => 'info@sayama.example', 'website' => 'https://sayama.example']],
    ]);
    $html = koyomi_snippet_html($kirby, 'koyomi/event', ['event' => $sayamaEvent, 'calendarUrl' => 'https://example.test/events']);
    holds(str_contains($html, '<a class="koyomi-organizer-email" href="mailto:info@sayama.example">info@sayama.example</a>'), 'event: public email as mailto');
    holds(str_contains($html, '<dt>Organizer</dt>'), 'event: one organizer uses the singular label');

    // Cancelled: status text and note; no booking, no calendar links.
    $cancelledWithBooking = koyomi_snippet_event([
        'id' => 'evt-cancelled', 'title' => 'Breathing Workshop', 'startDate' => '2027-03-25', 'startTime' => '', 'endTime' => '',
        'allDay' => true, 'status' => 'cancelled', 'statusNote' => 'Due to illness.', 'bookingUrl' => 'https://booking.example/x',
    ]);
    $html = koyomi_snippet_html($kirby, 'koyomi/event', ['event' => $cancelledWithBooking, 'calendarUrl' => 'https://example.test/events']);
    holds(str_contains($html, '<p class="koyomi-status koyomi-status--cancelled" role="status"><strong>Cancelled</strong> Due to illness.</p>'), 'event cancelled: status text, note');
    holds(!str_contains($html, 'koyomi-booking') && !str_contains($html, 'koyomi-calendar-links'), 'event cancelled: no booking and no calendar links');

    // Sold out: a text instead of the booking link.
    $soldout = koyomi_snippet_event([
        'id' => 'evt-soldout', 'title' => 'Full Workshop', 'startDate' => '2027-04-02', 'startTime' => '10:00', 'endTime' => '12:00',
        'availability' => 'soldout', 'bookingUrl' => 'https://booking.example/full',
    ]);
    $html = koyomi_snippet_html($kirby, 'koyomi/event', ['event' => $soldout, 'calendarUrl' => 'https://example.test/events']);
    holds(str_contains($html, '<p class="koyomi-booking koyomi-booking--soldout">Sold out</p>'), 'event sold out: text instead of booking');
    holds(!str_contains($html, 'koyomi-booking-link'), 'event sold out: no booking link');

    // Past: note and no booking, no calendar links.
    $pastWithBooking = koyomi_snippet_event([
        'id' => 'evt-past', 'title' => 'Past Workshop', 'startDate' => '2027-02-01', 'startTime' => '10:00', 'endTime' => '17:00',
        'bookingUrl' => 'https://booking.example/past',
    ]);
    $html = koyomi_snippet_html($kirby, 'koyomi/event', ['event' => $pastWithBooking, 'calendarUrl' => 'https://example.test/events']);
    holds(str_contains($html, 'This event has already taken place.'), 'event past: note');
    holds(!str_contains($html, 'koyomi-booking') && !str_contains($html, 'koyomi-calendar-links'), 'event past: no booking, no calendar links');

    // Switches: ics and google off.
    $kirby = KoyomiFixture::app($root, ['koyomi.ics' => false, 'koyomi.google' => false]);
    $html = koyomi_snippet_html($kirby, 'koyomi/event', ['event' => $retreat, 'calendarUrl' => 'https://example.test/events']);
    holds(!str_contains($html, 'download>') && !str_contains($html, 'calendar.google.com') && !str_contains($html, 'koyomi-calendar-links'), 'event: ics and google switched off');
    holds(str_contains($html, 'koyomi-booking-link'), 'event: booking unaffected by the switches');

    // Series navigation.
    $series = koyomi_snippet_event([
        'id' => 'evt-series-1', 'title' => 'Mantra Course 1', 'url' => 'https://example.test/events/series-part-1',
        'startDate' => '2027-04-01', 'startTime' => '10:00', 'endTime' => '12:00', 'isFree' => true, 'seriesPart' => '1',
        'series' => [['id' => 'evt-series-2', 'title' => 'Mantra Course 2', 'url' => 'https://example.test/events/series-part-2', 'startDate' => '2027-04-08', 'startTime' => '10:00']],
    ]);
    $kirby = KoyomiFixture::app($root);
    $html = koyomi_snippet_html($kirby, 'koyomi/event', ['event' => $series, 'calendarUrl' => 'https://example.test/events']);
    holds(str_contains($html, '<nav class="koyomi-series" aria-label="All dates in this series">'), 'series: navigation landmark');
    holds(str_contains($html, '<p>Part 1 of 2</p>'), 'series: part of total');
    holds(str_contains($html, '<span aria-current="page">Mantra Course 1</span> (this event)'), 'series: current item marked');
    holds(str_contains($html, '<a href="https://example.test/events/series-part-2">Mantra Course 2</a>'), 'series: link to the other part');
    holds(str_contains($html, 'Free') && str_contains($html, '<dd class="koyomi-price">Free</dd>'), 'free event: price text Free');

    // Event page via a page object is not testable here (page models are another task);
    // a non-event yields nothing.
    expect('', trim(koyomi_snippet_html($kirby, 'koyomi/event', ['event' => 'nope', 'calendarUrl' => 'x'])), 'event: not an event prints nothing');

    // German event.
    $kirby = KoyomiFixture::app($root, ['koyomi.language' => 'de']);
    $html = koyomi_snippet_html($kirby, 'koyomi/event', ['event' => $retreat, 'calendarUrl' => 'https://example.test/events']);
    holds(str_contains($html, 'Zur Anmeldung'), 'de event: booking label');
    holds(str_contains($html, 'In den Kalender') && str_contains($html, 'Herunterladen (.ics)') && str_contains($html, 'Google Kalender'), 'de event: calendar links');
    holds(str_contains($html, '<dd class="koyomi-languages">Deutsch, Tschechisch</dd>'), 'de event: languages');
    holds(str_contains($html, '<dd class="koyomi-price">380 € <span class="koyomi-price-note">Frühbucher bis 1. Februar</span> <span class="koyomi-availability">Nur noch wenige Plätze</span></dd>'), 'de event: price in German');
    holds(str_contains($html, 'Veranstalter'), 'de event: organizer label');
    $html = koyomi_snippet_html($kirby, 'koyomi/event', ['event' => $cancelledWithBooking, 'calendarUrl' => 'https://example.test/events']);
    holds(str_contains($html, '<strong>Abgesagt</strong> Due to illness.'), 'de event: cancelled label, the note is the stored text');

    // ---- past -----------------------------------------------------------------
    $kirby = KoyomiFixture::app($root);
    $html = koyomi_snippet_html($kirby, 'koyomi/past', ['event' => $past, 'calendarUrl' => 'https://example.test/events']);
    expect(
        '<p class="koyomi-past" role="note">This event has already taken place. <a href="https://example.test/events">See upcoming events</a></p>',
        trim($html),
        'past: note with link back'
    );
    expect('', trim(koyomi_snippet_html($kirby, 'koyomi/past', ['event' => $retreat, 'calendarUrl' => 'https://example.test/events'])), 'past: upcoming event prints nothing');
    $html = koyomi_snippet_html($kirby, 'koyomi/past', ['event' => $past, 'calendarUrl' => '']);
    holds(str_contains($html, 'This event has already taken place.') && !str_contains($html, '<a '), 'past: no link without a URL');
    $kirby = KoyomiFixture::app($root, ['koyomi.language' => 'de']);
    $html = koyomi_snippet_html($kirby, 'koyomi/past', ['event' => $past, 'calendarUrl' => 'https://example.test/events']);
    holds(str_contains($html, 'Diese Veranstaltung hat bereits stattgefunden.') && str_contains($html, 'Zu den kommenden Terminen'), 'de past: note and link text');

    // ---- venue ------------------------------------------------------------------
    $kirby = KoyomiFixture::app($root);
    $venue = Venue::fromArray(koyomi_raw_venue([
        'mapImage' => 'https://example.test/media/map.jpg', 'mapImageAlt' => 'Map of the Sonnenhof area', 'showMap' => true,
    ]));
    $html = koyomi_snippet_html($kirby, 'koyomi/venue', ['venue' => $venue]);
    holds(str_contains($html, '<div class="koyomi-venue">'), 'venue: wrapper');
    holds(str_contains($html, '<p class="koyomi-venue-name"><a href="https://example.test/venues/sonnenhof">Seminarhaus Beispielhof</a></p>'), 'venue: name linked');
    holds(str_contains($html, '<p class="koyomi-venue-address">Seminarweg 1<br>12345 Musterstadt<br>Germany</p>'), 'venue: address lines, country by name');
    holds(str_contains($html, '<p class="koyomi-venue-directions"><strong>Directions</strong><br>Ten minutes from the station.</p>'), 'venue: directions');
    holds(str_contains($html, '<img class="koyomi-venue-map" src="https://example.test/media/map.jpg" alt="Map of the Sonnenhof area" loading="lazy">'), 'venue: map image when showMap');
    holds(str_contains($html, '<a href="https://www.openstreetmap.org/?mlat=47.856&amp;mlon=12.128#map=17/47.856/12.128" target="_blank" rel="noopener">Show on map</a>'), 'venue: OpenStreetMap link');
    holds(str_contains($html, '<a href="https://sonnenhof.example" rel="noopener">Website</a>'), 'venue: website link');

    $noMap = Venue::fromArray(koyomi_raw_venue(['mapImage' => 'https://example.test/media/map.jpg', 'showMap' => false]));
    $html = koyomi_snippet_html($kirby, 'koyomi/venue', ['venue' => $noMap]);
    holds(!str_contains($html, 'koyomi-venue-map'), 'venue: no map image when showMap is off');

    $html = koyomi_snippet_html($kirby, 'koyomi/venue', ['venue' => $venue, 'compact' => true]);
    holds(str_contains($html, 'Seminarweg 1') && !str_contains($html, 'koyomi-venue-directions') && !str_contains($html, 'koyomi-venue-map'), 'venue compact: address only');
    holds(str_contains($html, 'koyomi-venue-links'), 'venue compact: links stay');

    $unnamedUrl = Venue::fromArray(['id' => 'venue-x', 'name' => 'Kulturhaus Praha', 'city' => 'Praha', 'country' => 'CZ']);
    $html = koyomi_snippet_html($kirby, 'koyomi/venue', ['venue' => $unnamedUrl]);
    holds(str_contains($html, '<p class="koyomi-venue-name">Kulturhaus Praha</p>'), 'venue without URL: plain name');
    holds(str_contains($html, '<p class="koyomi-venue-address">Praha<br>Czechia</p>'), 'venue: address without street');
    $malta = Venue::fromArray(['id' => 'venue-mt', 'name' => 'Valletta', 'city' => 'Valletta', 'country' => 'MT']);
    holds(str_contains(koyomi_snippet_html($kirby, 'koyomi/venue', ['venue' => $malta]), '<p class="koyomi-venue-address">Valletta<br>MT</p>'), 'venue: unknown country code printed as is');

    $kirby = KoyomiFixture::app($root, ['koyomi.country' => 'de']);
    holds(str_contains(koyomi_snippet_html($kirby, 'koyomi/venue', ['venue' => $venue]), '<p class="koyomi-venue-address">Seminarweg 1<br>12345 Musterstadt</p>'), 'venue: home country (koyomi.country) left out');
    holds(str_contains(koyomi_snippet_html($kirby, 'koyomi/venue', ['venue' => $unnamedUrl]), '<p class="koyomi-venue-address">Praha<br>Czechia</p>'), 'venue: other country kept with koyomi.country');

    $kirby = KoyomiFixture::app($root, ['koyomi.language' => 'de']);
    $html = koyomi_snippet_html($kirby, 'koyomi/venue', ['venue' => $venue]);
    holds(str_contains($html, 'Anfahrt') && str_contains($html, 'Auf Karte zeigen') && str_contains($html, 'Karte: Seminarhaus Beispielhof') === false, 'de venue: German labels');
    holds(str_contains($html, '12345 Musterstadt<br>Deutschland</p>'), 'de venue: German country name');

    // ---- list -------------------------------------------------------------------
    $kirby = KoyomiFixture::app($root);
    $html = koyomi_snippet_html($kirby, 'koyomi/list', ['events' => [$retreat, $sayamaEvent]]);
    holds(str_contains($html, '<section class="koyomi-list koyomi-list--upcoming">'), 'list: section and view class');
    holds(str_contains($html, '<h2 class="koyomi-list-title">Upcoming events</h2>'), 'list: default upcoming title');
    holds(str_contains($html, '<ol class="koyomi-events">') && substr_count($html, 'class="koyomi-events-item"') === 2, 'list: one item per event');
    // Default layout: timeline (koyomi.groupBy 'month') — months at level 3, cards at level 4.
    holds(str_contains($html, '<div class="koyomi-timeline">') && str_contains($html, 'class="koyomi-today"'), 'list: timeline with today marker');
    holds(substr_count($html, '<h4 class="koyomi-card-title">') === 2, 'list: cards at level 4 under the month headings');
    holds(str_contains($html, '<h3 class="koyomi-month-title" id="koyomi-month-2027-03"><time datetime="2027-03">March 2027</time></h3>'), 'list: month heading with datetime');
    holds(str_contains($html, '<p class="koyomi-card-date" aria-hidden="true"><span class="koyomi-card-weekday">Fri</span> <span class="koyomi-card-day">12–14</span>'), 'card: calendar-sheet date of a multi-day event');
    holds(str_contains($html, '<p class="koyomi-card-time" aria-hidden="true">Fri 18:00 – Sun 13:00</p>'), 'card: time of day for a multi-day event');
    $flat = koyomi_snippet_html($kirby, 'koyomi/list', ['events' => [$retreat, $sayamaEvent], 'group' => false]);
    holds(!str_contains($flat, 'koyomi-timeline') && substr_count($flat, '<h3 class="koyomi-card-title">') === 2, 'list group false: flat list, cards at level 3');
    $kirbyFlat = KoyomiFixture::app($root, ['koyomi.groupBy' => false]);
    holds(!str_contains(koyomi_snippet_html($kirbyFlat, 'koyomi/list', ['events' => [$retreat]]), 'koyomi-timeline'), 'koyomi.groupBy false: flat by default');
    $kirby = KoyomiFixture::app($root);
    holds(!str_contains($html, 'koyomi-list-switch') , 'list with events only: no switch link');

    $html = koyomi_snippet_html($kirby, 'koyomi/list', ['events' => [], 'title' => 'Next dates', 'level' => 3]);
    holds(str_contains($html, '<h3 class="koyomi-list-title">Next dates</h3>'), 'list: custom title and level');
    holds(str_contains($html, '<p class="koyomi-empty">There are no upcoming events at the moment.</p>'), 'list: empty text');
    $html = koyomi_snippet_html($kirby, 'koyomi/list', ['events' => [], 'title' => false]);
    holds(!str_contains($html, 'koyomi-list-title'), 'list: title false = no heading');

    $html = koyomi_snippet_html($kirby, 'koyomi/list', ['events' => [$retreat], 'level' => 3, 'group' => false]);
    holds(str_contains($html, '<h4 class="koyomi-card-title">'), 'list: cards one level below the list heading');
    $html = koyomi_snippet_html($kirby, 'koyomi/list', ['events' => [$retreat], 'level' => 3]);
    holds(str_contains($html, '<h4 class="koyomi-month-title"') && str_contains($html, '<h5 class="koyomi-card-title">'), 'timeline: month one level below, cards two levels below the list heading');

    $list = [
        'view' => 'upcoming', 'filters' => ['category' => null, 'attendance' => null, 'language' => null, 'featured' => false, 'view' => 'upcoming', 'page' => 1],
        'events' => [$retreat], 'pagination' => ['page' => 1, 'pages' => 1, 'total' => 1, 'perPage' => 12, 'hasPrev' => false, 'hasNext' => false, 'prevUrl' => null, 'nextUrl' => null],
        'facets' => ['categories' => [], 'languages' => [], 'attendance' => ['offline' => 0, 'online' => 0, 'mixed' => 1], 'featured' => 1],
        'title' => 'Events', 'baseUrl' => 'https://example.test/events', 'upcomingUrl' => 'https://example.test/events',
        'pastUrl' => 'https://example.test/events?view=past', 'filtered' => false,
    ];
    $html = koyomi_snippet_html($kirby, 'koyomi/list', ['list' => $list]);
    holds(str_contains($html, '<p class="koyomi-list-switch"><a href="https://example.test/events?view=past">Past events</a></p>'), 'list array: switch to past');
    holds(str_contains($html, 'Silent Retreat'), 'list array: events of the list');

    $past_list = $list;
    $past_list['view'] = 'past';
    $past_list['events'] = [];
    $past_list['filtered'] = true;
    $html = koyomi_snippet_html($kirby, 'koyomi/list', ['list' => $past_list]);
    holds(str_contains($html, '<section class="koyomi-list koyomi-list--past">'), 'list past: view class');
    holds(str_contains($html, '<h2 class="koyomi-list-title">Past events</h2>'), 'list past: title');
    holds(str_contains($html, '<p class="koyomi-empty">No events match these filters.</p>'), 'list past filtered: empty text');
    holds(str_contains($html, '<p class="koyomi-list-switch"><a href="https://example.test/events">Upcoming events</a></p>'), 'list past: switch back to upcoming');

    $past_list['filtered'] = false;
    $html = koyomi_snippet_html($kirby, 'koyomi/list', ['list' => $past_list]);
    holds(str_contains($html, '<p class="koyomi-empty">No past events.</p>'), 'list past unfiltered: empty text');

    // ---- filters ----------------------------------------------------------------
    $filterList = [
        'view' => 'upcoming',
        'filters' => ['category' => 'Retreat', 'attendance' => 'online', 'language' => 'cs', 'featured' => true, 'view' => 'upcoming', 'page' => 1],
        'events' => [],
        'pagination' => ['page' => 1, 'pages' => 1, 'total' => 0, 'perPage' => 12, 'hasPrev' => false, 'hasNext' => false, 'prevUrl' => null, 'nextUrl' => null],
        'facets' => [
            'categories' => ['Meditation' => 2, 'Retreat' => 1],
            'languages' => ['cs' => 1, 'de' => 3],
            'attendance' => ['offline' => 2, 'online' => 3, 'mixed' => 0],
            'featured' => 1,
        ],
        'title' => 'Events', 'baseUrl' => 'https://example.test/events',
        'upcomingUrl' => 'https://example.test/events', 'pastUrl' => 'https://example.test/events?view=past', 'filtered' => true,
    ];
    $html = koyomi_snippet_html($kirby, 'koyomi/filters', ['list' => $filterList]);
    holds(str_contains($html, '<form class="koyomi-filters" method="get" action="https://example.test/events" aria-label="Filter events">'), 'filters: GET form to the base URL');
    holds(str_contains($html, '<option value="">All</option>'), 'filters: "All" option');
    holds(str_contains($html, '<option value="Retreat" selected>Retreat (1)</option>'), 'filters: selected category');
    holds(str_contains($html, '<option value="Meditation">Meditation (2)</option>'), 'filters: other category');
    holds(str_contains($html, '<option value="online" selected>Online (3)</option>') && str_contains($html, '<option value="offline">In person (2)</option>'), 'filters: attendance with counts, selected');
    holds(!str_contains($html, 'value="mixed"'), 'filters: attendance with count 0 is left out');
    holds(str_contains($html, '<option value="cs" selected>Czech (1)</option>') && str_contains($html, '<option value="de">German (3)</option>'), 'filters: language with names and counts');
    holds(str_contains($html, '<input type="checkbox" name="featured" value="1" checked>'), 'filters: featured checked');
    holds(str_contains($html, '<button type="submit">Apply</button>'), 'filters: submit button');
    holds(str_contains($html, '<a class="koyomi-filters-reset" href="https://example.test/events">Reset filters</a>'), 'filters: reset link when filtered');
    holds(!str_contains($html, 'name="view"'), 'filters upcoming: no hidden view');

    $pastFilters = $filterList;
    $pastFilters['view'] = 'past';
    $pastFilters['filtered'] = false;
    $pastFilters['filters']['category'] = null;
    $html = koyomi_snippet_html($kirby, 'koyomi/filters', ['list' => $pastFilters]);
    holds(str_contains($html, '<input type="hidden" name="view" value="past">'), 'filters past: hidden view');
    holds(!str_contains($html, 'koyomi-filters-reset'), 'filters: no reset link when nothing is filtered');
    holds(str_contains($html, '<option value="Retreat">Retreat (1)</option>'), 'filters: no category selected');

    $nothing = ['view' => 'upcoming', 'filters' => [], 'facets' => ['categories' => [], 'languages' => [], 'attendance' => [], 'featured' => 0], 'filtered' => false, 'baseUrl' => 'https://example.test/events'];
    expect('', trim(koyomi_snippet_html($kirby, 'koyomi/filters', ['list' => $nothing])), 'filters: nothing to filter by prints nothing');

    $kirby = KoyomiFixture::app($root, ['koyomi.languages' => ['de' => 'Deutsch (Deutschland)', 'cs' => 'Čeština']]);
    $html = koyomi_snippet_html($kirby, 'koyomi/filters', ['list' => $filterList]);
    holds(str_contains($html, 'Čeština (1)') && str_contains($html, 'Deutsch (Deutschland) (3)'), 'filters: custom language labels from the options');

    // ---- pagination -------------------------------------------------------------
    $kirby = KoyomiFixture::app($root);
    $pager = ['page' => 2, 'pages' => 3, 'total' => 7, 'perPage' => 3, 'hasPrev' => true, 'hasNext' => true, 'prevUrl' => 'https://example.test/events?page=1', 'nextUrl' => 'https://example.test/events?page=3'];
    $html = koyomi_snippet_html($kirby, 'koyomi/pagination', ['list' => ['pagination' => $pager]]);
    holds(str_contains($html, '<nav class="koyomi-pagination" aria-label="Pagination">'), 'pagination: landmark');
    holds(str_contains($html, '<a rel="prev" href="https://example.test/events?page=1">Previous page</a>'), 'pagination: prev link');
    holds(str_contains($html, '<span>Page 2 of 3</span>'), 'pagination: page of pages');
    holds(str_contains($html, '<a rel="next" href="https://example.test/events?page=3">Next page</a>'), 'pagination: next link');

    $first = array_merge($pager, ['page' => 1, 'hasPrev' => false, 'prevUrl' => null]);
    $html = koyomi_snippet_html($kirby, 'koyomi/pagination', ['list' => ['pagination' => $first]]);
    holds(!str_contains($html, 'rel="prev"') && str_contains($html, 'rel="next"'), 'pagination: first page has no prev link');

    $single = array_merge($pager, ['pages' => 1, 'page' => 1]);
    expect('', trim(koyomi_snippet_html($kirby, 'koyomi/pagination', ['list' => ['pagination' => $single]])), 'pagination: one page prints nothing');

    // ---- subscribe ----------------------------------------------------------------
    $calendar = $kirby->page('events');
    $html = koyomi_snippet_html($kirby, 'koyomi/subscribe', ['calendar' => $calendar]);
    holds(str_contains($html, '<div class="koyomi-subscribe">'), 'subscribe: wrapper');
    holds(str_contains($html, '<a href="webcal://example.test/events.ics">Subscribe to calendar</a>'), 'subscribe: webcal link');
    holds(str_contains($html, '<p class="koyomi-subscribe-hint">Paste this address into your calendar app to see new dates automatically. <code>https://example.test/events.ics</code></p>'), 'subscribe: hint with the ics address');
    holds(str_contains($html, '<a href="https://example.test/events.rss" type="application/rss+xml">RSS feed</a>'), 'subscribe: rss link');

    $kirby = KoyomiFixture::app($root, ['koyomi.feed' => false]);
    $html = koyomi_snippet_html($kirby, 'koyomi/subscribe', ['calendar' => $kirby->page('events')]);
    holds(!str_contains($html, 'webcal') && str_contains($html, 'events.rss'), 'subscribe: feed off, rss still there');

    $kirby = KoyomiFixture::app($root, ['koyomi.feed' => false, 'koyomi.rss' => false]);
    expect('', trim(koyomi_snippet_html($kirby, 'koyomi/subscribe', ['calendar' => $kirby->page('events')])), 'subscribe: both off prints nothing');

    // ---- layout -------------------------------------------------------------------
    $kirby = KoyomiFixture::app($root);
    $html = koyomi_snippet_html($kirby, 'koyomi/layout', ['page' => $kirby->page('events')]);
    holds(str_contains($html, '<html lang="en">'), 'layout: lang en');
    holds(str_contains($html, '<meta charset="utf-8">') && str_contains($html, 'width=device-width'), 'layout: charset and viewport');
    holds(str_contains($html, '<title>Events – Koyomi Test Site</title>'), 'layout: page title – site title');
    holds(str_contains($html, '<main class="koyomi-main">'), 'layout: main landmark');
    holds(!str_contains($html, 'application/ld+json'), 'layout: no JSON-LD on a calendar page');
    $kirby = KoyomiFixture::app($root, ['koyomi.language' => 'de', 'koyomi.active' => false]);
    $html = koyomi_snippet_html($kirby, 'koyomi/layout', ['page' => $kirby->page('events')]);
    holds(str_contains($html, '<html lang="de">') && str_contains($html, '<main class="koyomi-main">'), 'layout: prints even when inactive, German lang');

    // ---- inactive: every snippet prints nothing -----------------------------------
    $kirby = KoyomiFixture::app($root, ['koyomi.active' => false]);
    $calendar = $kirby->page('events');
    $inactive = [
        'koyomi/card' => ['event' => $retreat],
        'koyomi/event' => ['event' => $retreat, 'calendarUrl' => 'https://example.test/events'],
        'koyomi/past' => ['event' => $past, 'calendarUrl' => 'https://example.test/events'],
        'koyomi/venue' => ['venue' => $venue],
        'koyomi/list' => ['events' => [$retreat]],
        'koyomi/filters' => ['list' => $filterList],
        'koyomi/pagination' => ['list' => ['pagination' => $pager]],
        'koyomi/subscribe' => ['calendar' => $calendar],
    ];
    foreach ($inactive as $name => $data) {
        expect('', trim(koyomi_snippet_html($kirby, $name, $data)), "inactive: $name prints nothing");
    }

    // ---- site override -------------------------------------------------------------
    $kirby = KoyomiFixture::app($root);
    @mkdir($root . '/site/snippets/koyomi', 0700, true);
    file_put_contents($root . '/site/snippets/koyomi/card.php', 'OWN CARD <?= esc($event->title) ?>');
    expect('OWN CARD Silent Retreat', trim(koyomi_snippet_html($kirby, 'koyomi/card', ['event' => $retreat])), 'site/snippets/koyomi/card.php wins');
    unlink($root . '/site/snippets/koyomi/card.php');
}, fn () => KoyomiFixture::remove($root));

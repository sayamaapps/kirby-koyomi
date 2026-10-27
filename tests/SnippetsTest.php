<?php

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Koyomi\Event;
use Sayamaapps\Koyomi\Venue;

/** Renders a snippet and returns its HTML as a string. */
function koyomi_snippet_html($kirby, string $name, array $data = []): string
{
	return (string)$kirby->snippet($name, $data, true);
}

/** An event from raw content with overrides (2027-03-12 18:00–20:00 Europe/Berlin by default). */
function koyomi_snippet_event(array $override = []): Event
{
	return Event::fromArray(koyomi_raw_event($override));
}

/**
 * The presentation snippets with real Kirby (tests/lib/Fixture.php): card,
 * event, past, venue, list, filters, pagination, subscribe and layout, in
 * English and German, the inactive switch and a site override.
 *
 * Event and Venue objects come from fromArray(); no page model, Repository
 * or Filters is involved, so the test passes event/venue/events/list/
 * calendarUrl explicitly.
 */
#[CoversNothing]
final class SnippetsTest extends TestCase
{
	public function testSuite(): void
	{
		$root = KoyomiFixture::create();

		try {

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
				'organizers' => [['id' => 'org-anna', 'name' => 'Anna Beispiel', 'kind' => 'person', 'website' => 'https://anna.example']],
				'image' => 'https://example.test/media/cover.jpg', 'imageAlt' => 'Sunrise', 'modified' => 1791000000,
				'featured' => true,
			];
			$retreat = koyomi_snippet_event($retreatData);

			// ---- card (English) -----------------------------------------------------
			$kirby = KoyomiFixture::app($root);
			$html = koyomi_snippet_html($kirby, 'koyomi/card', ['event' => $retreat]);
			$this->assertTrue(str_contains($html, '<article class="koyomi-card koyomi-card--scheduled koyomi-card--featured">'), 'card: classes incl. featured');
			$this->assertTrue(
				str_contains($html, '<h3 class="koyomi-card-title"><a href="https://example.test/events/retreat">Silent Retreat</a></h3>'),
				'card: title link at level 3'
			);
			$this->assertTrue(
				str_contains($html, '<time datetime="2027-03-12T18:00:00+01:00">Friday, 12 March 2027, 18:00 – Sunday, 14 March 2027, 13:00</time>'),
				'card: <time datetime> with offset and range text'
			);
			$this->assertTrue(str_contains($html, '<img class="koyomi-card-image" src="https://example.test/media/cover.jpg" alt="Sunrise" loading="lazy">'), 'card: image');
			$this->assertTrue(
				str_contains($html, '<p class="koyomi-card-place">Seminarhaus Beispielhof, Musterstadt · Hybrid: in person and online via Zoom</p>'),
				'card: place and attendance'
			);
			$this->assertTrue(str_contains($html, '<p class="koyomi-card-price">€380 · Only a few places left</p>'), 'card: price and availability as text');
			$this->assertTrue(str_contains($html, '<p class="koyomi-card-excerpt">Three days of silence.</p>'), 'card: excerpt');
			$this->assertTrue(!str_contains($html, 'koyomi-card-status') && !str_contains($html, 'koyomi-card-badge'), 'card: scheduled and upcoming show no status or badge');

			$html = koyomi_snippet_html($kirby, 'koyomi/card', ['event' => $retreat, 'level' => 4]);
			$this->assertTrue(str_contains($html, '<h4 class="koyomi-card-title">'), 'card: level 4 heading');

			$cancelled = koyomi_snippet_event([
				'id' => 'evt-cancelled', 'title' => 'Breathing Workshop', 'url' => 'https://example.test/events/workshop-cancelled',
				'startDate' => '2027-03-25', 'startTime' => '', 'endTime' => '', 'allDay' => true,
				'status' => 'cancelled', 'statusNote' => 'Due to illness.',
			]);
			$html = koyomi_snippet_html($kirby, 'koyomi/card', ['event' => $cancelled]);
			$this->assertTrue(str_contains($html, '<article class="koyomi-card koyomi-card--cancelled">'), 'card cancelled: modifier');
			$this->assertTrue(str_contains($html, '<p class="koyomi-card-status"><strong>Cancelled</strong></p>'), 'card cancelled: status as text');
			$this->assertTrue(str_contains($html, '<time datetime="2027-03-25">Thursday, 25 March 2027</time>'), 'card all-day: date only in datetime');

			$ongoing = koyomi_snippet_event([
				'id' => 'evt-ongoing', 'title' => 'Spring Retreat', 'startDate' => '2027-03-08', 'endDate' => '2027-03-11',
				'startTime' => '', 'endTime' => '', 'allDay' => true,
			]);
			$html = koyomi_snippet_html($kirby, 'koyomi/card', ['event' => $ongoing]);
			$this->assertTrue(
				str_contains($html, 'koyomi-card--ongoing') && str_contains($html, '<p class="koyomi-card-badge">Happening now</p>'),
				'card ongoing: "Happening now" as text'
			);

			$past = koyomi_snippet_event([
				'id' => 'evt-past', 'title' => 'Past Workshop', 'startDate' => '2027-02-01', 'startTime' => '10:00', 'endTime' => '17:00',
			]);
			$html = koyomi_snippet_html($kirby, 'koyomi/card', ['event' => $past]);
			$this->assertTrue(
				str_contains($html, 'koyomi-card--past') && str_contains($html, '<p class="koyomi-card-badge">Has taken place</p>'),
				'card past: "Has taken place" as text'
			);

			$concert = koyomi_snippet_event([
				'id' => 'evt-concert', 'title' => 'Evening Concert', 'startDate' => '2027-03-20', 'startTime' => '20:00', 'endTime' => '',
				'timezone' => 'Europe/Prague', 'status' => 'rescheduled', 'previousStart' => '2027-02-20 20:00:00',
				'isFree' => false, 'price' => '450', 'currency' => 'CZK',
			]);
			$html = koyomi_snippet_html($kirby, 'koyomi/card', ['event' => $concert]);
			$this->assertTrue(
				str_contains($html, '<time datetime="2027-03-20T20:00:00+01:00">Saturday, 20 March 2027, 20:00 (Europe/Prague)</time>'),
				'card: foreign zone hint and offset'
			);
			$this->assertTrue(str_contains($html, '<p class="koyomi-card-status"><strong>Rescheduled</strong></p>'), 'card: rescheduled as text');
			$this->assertTrue(str_contains($html, '<p class="koyomi-card-price">CZK 450</p>'), 'card: CZK money');

			// A page-less card for a non-event renders nothing.
			$this->assertSame('', trim(koyomi_snippet_html($kirby, 'koyomi/card', ['event' => 'nope'])), 'card: not an event prints nothing');

			// ---- German ---------------------------------------------------------------
			$kirby = KoyomiFixture::app($root, ['koyomi.language' => 'de']);
			$html = koyomi_snippet_html($kirby, 'koyomi/card', ['event' => $retreat]);
			$this->assertTrue(str_contains($html, 'Freitag, 12. März 2027, 18:00 Uhr – Sonntag, 14. März 2027, 13:00 Uhr'), 'de card: German when');
			$this->assertTrue(str_contains($html, '<p class="koyomi-card-price">380 € · Nur noch wenige Plätze</p>'), 'de card: German money and availability');
			$this->assertTrue(str_contains($html, 'Läuft gerade') === false, 'de card: upcoming shows no ongoing badge');
			$html = koyomi_snippet_html($kirby, 'koyomi/card', ['event' => $ongoing]);
			$this->assertTrue(str_contains($html, 'Läuft gerade'), 'de card: ongoing badge');
			$html = koyomi_snippet_html($kirby, 'koyomi/card', ['event' => $cancelled]);
			$this->assertTrue(str_contains($html, '<strong>Abgesagt</strong>'), 'de card: cancelled');

			// ---- event (English) ------------------------------------------------------
			$kirby = KoyomiFixture::app($root);
			$html = koyomi_snippet_html($kirby, 'koyomi/event', ['event' => $retreat, 'calendarUrl' => 'https://example.test/events']);
			$this->assertTrue(str_contains($html, '<div class="koyomi-event koyomi-event--scheduled koyomi-event--featured">'), 'event: classes');
			$this->assertTrue(
				str_contains($html, '<dd class="koyomi-when"><time datetime="2027-03-12T18:00:00+01:00">Friday, 12 March 2027, 18:00 – Sunday, 14 March 2027, 13:00</time></dd>'),
				'event: when with offset'
			);
			$this->assertTrue(
				str_contains($html, '<p class="koyomi-venue-name"><a href="https://example.test/venues/beispielhof">Seminarhaus Beispielhof</a></p>'),
				'event: venue inside where, compact'
			);
			$this->assertTrue(str_contains($html, '<p class="koyomi-attendance">Hybrid: in person and online via Zoom</p>'), 'event: attendance label');
			$this->assertTrue(!str_contains($html, 'koyomi-venue-directions') && !str_contains($html, 'koyomi-venue-map'), 'event: venue compact (no directions, no map)');
			$this->assertTrue(
				str_contains(
					$html,
					'<dd class="koyomi-price">€380 <span class="koyomi-price-note">Frühbucher bis 1. Februar</span> <span'
						. ' class="koyomi-availability">Only a few places left</span></dd>'
				),
				'event: price, note and availability'
			);
			$this->assertTrue(str_contains($html, '<dd class="koyomi-languages">German, Czech</dd>'), 'event: languages');
			$this->assertTrue(str_contains($html, '<dd class="koyomi-categories">Retreat, Meditation</dd>'), 'event: categories');
			$this->assertTrue(
				str_contains(
					$html,
					'<a class="koyomi-booking-link" href="https://booking.example/retreat"'
						. ' target="_blank" rel="noopener">Register<span class="koyomi-sr-only"> (opens in a new tab)</span></a>'
				),
				'event: booking link'
			);
			$this->assertTrue(str_contains($html, '<a class="koyomi-calendar-ics" href="https://example.test/events/retreat.ics" download>Download (.ics)</a>'), 'event: ics link');
			$this->assertTrue(
				str_contains($html, '<a class="koyomi-calendar-google" href="https://calendar.google.com/calendar/render?action=TEMPLATE&amp;text=Silent%20Retreat'),
				'event: Google link, escaped'
			);
			$this->assertTrue(str_contains($html, 'mailto:') === false, 'event: organizer without public email has no mailto');
			$this->assertTrue(
				str_contains($html, '<dt>Organizer</dt>') && str_contains($html, '<a href="https://anna.example">Anna Beispiel</a>'),
				'event: organizer name linked to its website'
			);
			$this->assertTrue(str_contains($html, '<p class="koyomi-status') === false, 'event: scheduled has no status line');

			// Expiry: the page is not cached past the start.
			$kirby = KoyomiFixture::app($root);
			koyomi_snippet_html($kirby, 'koyomi/event', ['event' => $retreat, 'calendarUrl' => 'https://example.test/events']);
			$this->assertSame(
				(new DateTimeImmutable('2027-03-12 18:00', $berlin))->getTimestamp(),
				$kirby->response()->expires(),
				'event: koyomi_expires() sets the expiry to the start'
			);

			// Public email becomes a mailto link; the organizer's website is the link.
			$sayamaEvent = koyomi_snippet_event([
				'organizers' => [['id' => 'org-sayama', 'name' => 'Sayama', 'kind' => 'organization', 'email' => 'info@sayama.example', 'website' => 'https://sayama.example']],
			]);
			$html = koyomi_snippet_html($kirby, 'koyomi/event', ['event' => $sayamaEvent, 'calendarUrl' => 'https://example.test/events']);
			$this->assertTrue(str_contains($html, '<a class="koyomi-organizer-email" href="mailto:info@sayama.example">info@sayama.example</a>'), 'event: public email as mailto');
			$this->assertTrue(str_contains($html, '<dt>Organizer</dt>'), 'event: one organizer uses the singular label');

			// Cancelled: status text and note; no booking, no calendar links.
			$cancelledWithBooking = koyomi_snippet_event([
				'id' => 'evt-cancelled', 'title' => 'Breathing Workshop', 'startDate' => '2027-03-25', 'startTime' => '', 'endTime' => '',
				'allDay' => true, 'status' => 'cancelled', 'statusNote' => 'Due to illness.', 'bookingUrl' => 'https://booking.example/x',
			]);
			$html = koyomi_snippet_html($kirby, 'koyomi/event', ['event' => $cancelledWithBooking, 'calendarUrl' => 'https://example.test/events']);
			$this->assertTrue(
				str_contains($html, '<p class="koyomi-status koyomi-status--cancelled" role="status"><strong>Cancelled</strong> Due to illness.</p>'),
				'event cancelled: status text, note'
			);
			$this->assertTrue(!str_contains($html, 'koyomi-booking') && !str_contains($html, 'koyomi-calendar-links'), 'event cancelled: no booking and no calendar links');

			// Sold out: a text instead of the booking link.
			$soldout = koyomi_snippet_event([
				'id' => 'evt-soldout', 'title' => 'Full Workshop', 'startDate' => '2027-04-02', 'startTime' => '10:00', 'endTime' => '12:00',
				'availability' => 'soldout', 'bookingUrl' => 'https://booking.example/full',
			]);
			$html = koyomi_snippet_html($kirby, 'koyomi/event', ['event' => $soldout, 'calendarUrl' => 'https://example.test/events']);
			$this->assertTrue(str_contains($html, '<p class="koyomi-booking koyomi-booking--soldout">Sold out</p>'), 'event sold out: text instead of booking');
			$this->assertTrue(!str_contains($html, 'koyomi-booking-link'), 'event sold out: no booking link');

			// Past: note and no booking, no calendar links.
			$pastWithBooking = koyomi_snippet_event([
				'id' => 'evt-past', 'title' => 'Past Workshop', 'startDate' => '2027-02-01', 'startTime' => '10:00', 'endTime' => '17:00',
				'bookingUrl' => 'https://booking.example/past',
			]);
			$html = koyomi_snippet_html($kirby, 'koyomi/event', ['event' => $pastWithBooking, 'calendarUrl' => 'https://example.test/events']);
			$this->assertTrue(str_contains($html, 'This event has already taken place.'), 'event past: note');
			$this->assertTrue(!str_contains($html, 'koyomi-booking') && !str_contains($html, 'koyomi-calendar-links'), 'event past: no booking, no calendar links');

			// Switches: ics and google off.
			$kirby = KoyomiFixture::app($root, ['koyomi.ics' => false, 'koyomi.google' => false]);
			$html = koyomi_snippet_html($kirby, 'koyomi/event', ['event' => $retreat, 'calendarUrl' => 'https://example.test/events']);
			$this->assertTrue(
				!str_contains($html, 'download>') && !str_contains($html, 'calendar.google.com') && !str_contains($html, 'koyomi-calendar-links'),
				'event: ics and google switched off'
			);
			$this->assertTrue(str_contains($html, 'koyomi-booking-link'), 'event: booking unaffected by the switches');

			// Series navigation.
			$series = koyomi_snippet_event([
				'id' => 'evt-series-1', 'title' => 'Mantra Course 1', 'url' => 'https://example.test/events/series-part-1',
				'startDate' => '2027-04-01', 'startTime' => '10:00', 'endTime' => '12:00', 'isFree' => true, 'seriesPart' => '1',
				'series' => [[
					'id' => 'evt-series-2',
					'title' => 'Mantra Course 2',
					'url' => 'https://example.test/events/series-part-2',
					'startDate' => '2027-04-08',
					'startTime' => '10:00'
				]],
			]);
			$kirby = KoyomiFixture::app($root);
			$html = koyomi_snippet_html($kirby, 'koyomi/event', ['event' => $series, 'calendarUrl' => 'https://example.test/events']);
			$this->assertTrue(str_contains($html, '<nav class="koyomi-series" aria-label="All dates in this series">'), 'series: navigation landmark');
			$this->assertTrue(str_contains($html, '<p>Part 1 of 2</p>'), 'series: part of total');
			$this->assertTrue(str_contains($html, '<span aria-current="page">Mantra Course 1</span> (this event)'), 'series: current item marked');
			$this->assertTrue(str_contains($html, '<a href="https://example.test/events/series-part-2">Mantra Course 2</a>'), 'series: link to the other part');
			$this->assertTrue(str_contains($html, 'Free') && str_contains($html, '<dd class="koyomi-price">Free</dd>'), 'free event: price text Free');

			// Event page via a page object is not testable here (page models are another task);
			// a non-event yields nothing.
			$this->assertSame('', trim(koyomi_snippet_html($kirby, 'koyomi/event', ['event' => 'nope', 'calendarUrl' => 'x'])), 'event: not an event prints nothing');

			// German event.
			$kirby = KoyomiFixture::app($root, ['koyomi.language' => 'de']);
			$html = koyomi_snippet_html($kirby, 'koyomi/event', ['event' => $retreat, 'calendarUrl' => 'https://example.test/events']);
			$this->assertTrue(str_contains($html, 'Zur Anmeldung'), 'de event: booking label');
			$this->assertTrue(
				str_contains($html, 'In den Kalender') && str_contains($html, 'Herunterladen (.ics)') && str_contains($html, 'Google Kalender'),
				'de event: calendar links'
			);
			$this->assertTrue(str_contains($html, '<dd class="koyomi-languages">Deutsch, Tschechisch</dd>'), 'de event: languages');
			$this->assertTrue(
				str_contains(
					$html,
					'<dd class="koyomi-price">380 € <span class="koyomi-price-note">Frühbucher bis 1. Februar</span>'
						. ' <span class="koyomi-availability">Nur noch wenige Plätze</span></dd>'
				),
				'de event: price in German'
			);
			$this->assertTrue(str_contains($html, 'Veranstalter'), 'de event: organizer label');
			$html = koyomi_snippet_html($kirby, 'koyomi/event', ['event' => $cancelledWithBooking, 'calendarUrl' => 'https://example.test/events']);
			$this->assertTrue(str_contains($html, '<strong>Abgesagt</strong> Due to illness.'), 'de event: cancelled label, the note is the stored text');

			// ---- past -----------------------------------------------------------------
			$kirby = KoyomiFixture::app($root);
			$html = koyomi_snippet_html($kirby, 'koyomi/past', ['event' => $past, 'calendarUrl' => 'https://example.test/events']);
			$this->assertSame(
				'<p class="koyomi-past" role="note">This event has already taken place. <a href="https://example.test/events">See upcoming events</a></p>',
				trim($html),
				'past: note with link back'
			);
			$this->assertSame(
				'',
				trim(koyomi_snippet_html($kirby, 'koyomi/past', ['event' => $retreat, 'calendarUrl' => 'https://example.test/events'])),
				'past: upcoming event prints nothing'
			);
			$html = koyomi_snippet_html($kirby, 'koyomi/past', ['event' => $past, 'calendarUrl' => '']);
			$this->assertTrue(str_contains($html, 'This event has already taken place.') && !str_contains($html, '<a '), 'past: no link without a URL');
			$kirby = KoyomiFixture::app($root, ['koyomi.language' => 'de']);
			$html = koyomi_snippet_html($kirby, 'koyomi/past', ['event' => $past, 'calendarUrl' => 'https://example.test/events']);
			$this->assertTrue(
				str_contains($html, 'Diese Veranstaltung hat bereits stattgefunden.') && str_contains($html, 'Zu den kommenden Terminen'),
				'de past: note and link text'
			);

			// ---- venue ------------------------------------------------------------------
			$kirby = KoyomiFixture::app($root);
			$venue = Venue::fromArray(koyomi_raw_venue([
				'mapImage' => 'https://example.test/media/map.jpg', 'mapImageAlt' => 'Map of the Beispielhof area', 'showMap' => true,
			]));
			$html = koyomi_snippet_html($kirby, 'koyomi/venue', ['venue' => $venue]);
			$this->assertTrue(str_contains($html, '<div class="koyomi-venue">'), 'venue: wrapper');
			$this->assertTrue(
				str_contains($html, '<p class="koyomi-venue-name"><a href="https://example.test/venues/beispielhof">Seminarhaus Beispielhof</a></p>'),
				'venue: name linked'
			);
			$this->assertTrue(str_contains($html, '<p class="koyomi-venue-address">Musterstraße 1<br>12345 Musterstadt<br>Germany</p>'), 'venue: address lines, country by name');
			$this->assertTrue(str_contains($html, '<p class="koyomi-venue-directions"><strong>Directions</strong><br>Ten minutes from the station.</p>'), 'venue: directions');
			$this->assertTrue(
				str_contains($html, '<img class="koyomi-venue-map" src="https://example.test/media/map.jpg" alt="Map of the Beispielhof area" loading="lazy">'),
				'venue: map image when showMap'
			);
			$this->assertTrue(
				str_contains($html, '<a href="https://www.openstreetmap.org/?mlat=48.123&amp;mlon=11.456#map=17/48.123/11.456" target="_blank" rel="noopener">Show on map</a>'),
				'venue: OpenStreetMap link'
			);
			$this->assertTrue(str_contains($html, '<a href="https://beispielhof.example" rel="noopener">Website</a>'), 'venue: website link');

			$noMap = Venue::fromArray(koyomi_raw_venue(['mapImage' => 'https://example.test/media/map.jpg', 'showMap' => false]));
			$html = koyomi_snippet_html($kirby, 'koyomi/venue', ['venue' => $noMap]);
			$this->assertTrue(!str_contains($html, 'koyomi-venue-map'), 'venue: no map image when showMap is off');

			$html = koyomi_snippet_html($kirby, 'koyomi/venue', ['venue' => $venue, 'compact' => true]);
			$this->assertTrue(
				str_contains($html, 'Musterstraße 1') && !str_contains($html, 'koyomi-venue-directions') && !str_contains($html, 'koyomi-venue-map'),
				'venue compact: address only'
			);
			$this->assertTrue(str_contains($html, 'koyomi-venue-links'), 'venue compact: links stay');

			$unnamedUrl = Venue::fromArray(['id' => 'venue-x', 'name' => 'Stadthalle Musterstadt', 'city' => 'Musterstadt', 'country' => 'CZ']);
			$html = koyomi_snippet_html($kirby, 'koyomi/venue', ['venue' => $unnamedUrl]);
			$this->assertTrue(str_contains($html, '<p class="koyomi-venue-name">Stadthalle Musterstadt</p>'), 'venue without URL: plain name');
			$this->assertTrue(str_contains($html, '<p class="koyomi-venue-address">Musterstadt<br>Czechia</p>'), 'venue: address without street');
			$malta = Venue::fromArray(['id' => 'venue-mt', 'name' => 'Valletta', 'city' => 'Valletta', 'country' => 'MT']);
			$this->assertTrue(
				str_contains(koyomi_snippet_html($kirby, 'koyomi/venue', ['venue' => $malta]), '<p class="koyomi-venue-address">Valletta<br>MT</p>'),
				'venue: unknown country code printed as is'
			);

			$kirby = KoyomiFixture::app($root, ['koyomi.country' => 'de']);
			$this->assertTrue(
				str_contains(koyomi_snippet_html($kirby, 'koyomi/venue', ['venue' => $venue]), '<p class="koyomi-venue-address">Musterstraße 1<br>12345 Musterstadt</p>'),
				'venue: home country (koyomi.country) left out'
			);
			$this->assertTrue(
				str_contains(koyomi_snippet_html($kirby, 'koyomi/venue', ['venue' => $unnamedUrl]), '<p class="koyomi-venue-address">Musterstadt<br>Czechia</p>'),
				'venue: other country kept with koyomi.country'
			);

			$kirby = KoyomiFixture::app($root, ['koyomi.language' => 'de']);
			$html = koyomi_snippet_html($kirby, 'koyomi/venue', ['venue' => $venue]);
			$this->assertTrue(
				str_contains($html, 'Anfahrt') && str_contains($html, 'Auf Karte zeigen') && str_contains($html, 'Karte: Seminarhaus Beispielhof') === false,
				'de venue: German labels'
			);
			$this->assertTrue(str_contains($html, '12345 Musterstadt<br>Deutschland</p>'), 'de venue: German country name');

			// ---- list -------------------------------------------------------------------
			$kirby = KoyomiFixture::app($root);
			$html = koyomi_snippet_html($kirby, 'koyomi/list', ['events' => [$retreat, $sayamaEvent]]);
			$this->assertTrue(str_contains($html, '<section class="koyomi-list koyomi-list--upcoming">'), 'list: section and view class');
			$this->assertTrue(str_contains($html, '<h2 class="koyomi-list-title">Upcoming events</h2>'), 'list: default upcoming title');
			$this->assertTrue(str_contains($html, '<ol class="koyomi-events">') && substr_count($html, 'class="koyomi-events-item"') === 2, 'list: one item per event');
			// Default layout: timeline (koyomi.groupBy 'month') — months at level 3, cards at level 4.
			$this->assertTrue(str_contains($html, '<div class="koyomi-timeline">') && str_contains($html, 'class="koyomi-today"'), 'list: timeline with today marker');
			$this->assertTrue(substr_count($html, '<h4 class="koyomi-card-title">') === 2, 'list: cards at level 4 under the month headings');
			$this->assertTrue(
				str_contains($html, '<h3 class="koyomi-month-title" id="koyomi-month-2027-03"><time datetime="2027-03">March 2027</time></h3>'),
				'list: month heading with datetime'
			);
			$this->assertTrue(
				str_contains($html, '<p class="koyomi-card-date" aria-hidden="true"><span class="koyomi-card-weekday">Fri</span> <span class="koyomi-card-day">12–14</span>'),
				'card: calendar-sheet date of a multi-day event'
			);
			$this->assertTrue(str_contains($html, '<p class="koyomi-card-time" aria-hidden="true">Fri 18:00 – Sun 13:00</p>'), 'card: time of day for a multi-day event');
			$flat = koyomi_snippet_html($kirby, 'koyomi/list', ['events' => [$retreat, $sayamaEvent], 'group' => false]);
			$this->assertTrue(
				!str_contains($flat, 'koyomi-timeline') && substr_count($flat, '<h3 class="koyomi-card-title">') === 2,
				'list group false: flat list, cards at level 3'
			);
			$kirbyFlat = KoyomiFixture::app($root, ['koyomi.groupBy' => false]);
			$this->assertTrue(!str_contains(koyomi_snippet_html($kirbyFlat, 'koyomi/list', ['events' => [$retreat]]), 'koyomi-timeline'), 'koyomi.groupBy false: flat by default');
			$kirby = KoyomiFixture::app($root);
			$this->assertTrue(!str_contains($html, 'koyomi-list-switch'), 'list with events only: no switch link');

			$html = koyomi_snippet_html($kirby, 'koyomi/list', ['events' => [], 'title' => 'Next dates', 'level' => 3]);
			$this->assertTrue(str_contains($html, '<h3 class="koyomi-list-title">Next dates</h3>'), 'list: custom title and level');
			$this->assertTrue(str_contains($html, '<p class="koyomi-empty">There are no upcoming events at the moment.</p>'), 'list: empty text');
			$html = koyomi_snippet_html($kirby, 'koyomi/list', ['events' => [], 'title' => false]);
			$this->assertTrue(!str_contains($html, 'koyomi-list-title'), 'list: title false = no heading');

			$html = koyomi_snippet_html($kirby, 'koyomi/list', ['events' => [$retreat], 'level' => 3, 'group' => false]);
			$this->assertTrue(str_contains($html, '<h4 class="koyomi-card-title">'), 'list: cards one level below the list heading');
			$html = koyomi_snippet_html($kirby, 'koyomi/list', ['events' => [$retreat], 'level' => 3]);
			$this->assertTrue(
				str_contains($html, '<h4 class="koyomi-month-title"') && str_contains($html, '<h5 class="koyomi-card-title">'),
				'timeline: month one level below, cards two levels below the list heading'
			);

			$list = [
				'view' => 'upcoming', 'filters' => ['category' => null, 'attendance' => null, 'language' => null, 'featured' => false, 'view' => 'upcoming', 'page' => 1],
				'events' => [$retreat], 'pagination' => [
					'page' => 1,
					'pages' => 1,
					'total' => 1,
					'perPage' => 12,
					'hasPrev' => false,
					'hasNext' => false,
					'prevUrl' => null,
					'nextUrl' => null
				],
				'facets' => ['categories' => [], 'languages' => [], 'attendance' => ['offline' => 0, 'online' => 0, 'mixed' => 1], 'featured' => 1],
				'title' => 'Events', 'baseUrl' => 'https://example.test/events', 'upcomingUrl' => 'https://example.test/events',
				'pastUrl' => 'https://example.test/events?view=past', 'filtered' => false,
			];
			$html = koyomi_snippet_html($kirby, 'koyomi/list', ['list' => $list]);
			$this->assertTrue(
				str_contains($html, '<p class="koyomi-list-switch"><a href="https://example.test/events?view=past">Past events</a></p>'),
				'list array: switch to past'
			);
			$this->assertTrue(str_contains($html, 'Silent Retreat'), 'list array: events of the list');

			$past_list = $list;
			$past_list['view'] = 'past';
			$past_list['events'] = [];
			$past_list['filtered'] = true;
			$html = koyomi_snippet_html($kirby, 'koyomi/list', ['list' => $past_list]);
			$this->assertTrue(str_contains($html, '<section class="koyomi-list koyomi-list--past">'), 'list past: view class');
			$this->assertTrue(str_contains($html, '<h2 class="koyomi-list-title">Past events</h2>'), 'list past: title');
			$this->assertTrue(str_contains($html, '<p class="koyomi-empty">No events match these filters.</p>'), 'list past filtered: empty text');
			$this->assertTrue(
				str_contains($html, '<p class="koyomi-list-switch"><a href="https://example.test/events">Upcoming events</a></p>'),
				'list past: switch back to upcoming'
			);

			$past_list['filtered'] = false;
			$html = koyomi_snippet_html($kirby, 'koyomi/list', ['list' => $past_list]);
			$this->assertTrue(str_contains($html, '<p class="koyomi-empty">No past events.</p>'), 'list past unfiltered: empty text');

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
			$this->assertTrue(
				str_contains($html, '<form class="koyomi-filters" method="get" action="https://example.test/events" aria-label="Filter events">'),
				'filters: GET form to the base URL'
			);
			$this->assertTrue(str_contains($html, '<option value="">All</option>'), 'filters: "All" option');
			$this->assertTrue(str_contains($html, '<option value="Retreat" selected>Retreat (1)</option>'), 'filters: selected category');
			$this->assertTrue(str_contains($html, '<option value="Meditation">Meditation (2)</option>'), 'filters: other category');
			$this->assertTrue(
				str_contains($html, '<option value="online" selected>Online (3)</option>') && str_contains($html, '<option value="offline">In person (2)</option>'),
				'filters: attendance with counts, selected'
			);
			$this->assertTrue(!str_contains($html, 'value="mixed"'), 'filters: attendance with count 0 is left out');
			$this->assertTrue(
				str_contains($html, '<option value="cs" selected>Czech (1)</option>') && str_contains($html, '<option value="de">German (3)</option>'),
				'filters: language with names and counts'
			);
			$this->assertTrue(str_contains($html, '<input type="checkbox" name="featured" value="1" checked>'), 'filters: featured checked');
			$this->assertTrue(str_contains($html, '<button type="submit">Apply</button>'), 'filters: submit button');
			$this->assertTrue(str_contains($html, '<a class="koyomi-filters-reset" href="https://example.test/events">Reset filters</a>'), 'filters: reset link when filtered');
			$this->assertTrue(!str_contains($html, 'name="view"'), 'filters upcoming: no hidden view');

			$pastFilters = $filterList;
			$pastFilters['view'] = 'past';
			$pastFilters['filtered'] = false;
			$pastFilters['filters']['category'] = null;
			$html = koyomi_snippet_html($kirby, 'koyomi/filters', ['list' => $pastFilters]);
			$this->assertTrue(str_contains($html, '<input type="hidden" name="view" value="past">'), 'filters past: hidden view');
			$this->assertTrue(!str_contains($html, 'koyomi-filters-reset'), 'filters: no reset link when nothing is filtered');
			$this->assertTrue(str_contains($html, '<option value="Retreat">Retreat (1)</option>'), 'filters: no category selected');

			$nothing = [
				'view' => 'upcoming',
				'filters' => [],
				'facets' => ['categories' => [], 'languages' => [], 'attendance' => [], 'featured' => 0],
				'filtered' => false,
				'baseUrl' => 'https://example.test/events'
			];
			$this->assertSame('', trim(koyomi_snippet_html($kirby, 'koyomi/filters', ['list' => $nothing])), 'filters: nothing to filter by prints nothing');

			$kirby = KoyomiFixture::app($root, ['koyomi.languages' => ['de' => 'Deutsch (Deutschland)', 'cs' => 'Čeština']]);
			$html = koyomi_snippet_html($kirby, 'koyomi/filters', ['list' => $filterList]);
			$this->assertTrue(str_contains($html, 'Čeština (1)') && str_contains($html, 'Deutsch (Deutschland) (3)'), 'filters: custom language labels from the options');

			// ---- pagination -------------------------------------------------------------
			$kirby = KoyomiFixture::app($root);
			$pager = [
				'page' => 2,
				'pages' => 3,
				'total' => 7,
				'perPage' => 3,
				'hasPrev' => true,
				'hasNext' => true,
				'prevUrl' => 'https://example.test/events?page=1',
				'nextUrl' => 'https://example.test/events?page=3'
			];
			$html = koyomi_snippet_html($kirby, 'koyomi/pagination', ['list' => ['pagination' => $pager]]);
			$this->assertTrue(str_contains($html, '<nav class="koyomi-pagination" aria-label="Pagination">'), 'pagination: landmark');
			$this->assertTrue(str_contains($html, '<a rel="prev" href="https://example.test/events?page=1">Previous page</a>'), 'pagination: prev link');
			$this->assertTrue(str_contains($html, '<span>Page 2 of 3</span>'), 'pagination: page of pages');
			$this->assertTrue(str_contains($html, '<a rel="next" href="https://example.test/events?page=3">Next page</a>'), 'pagination: next link');

			$first = array_merge($pager, ['page' => 1, 'hasPrev' => false, 'prevUrl' => null]);
			$html = koyomi_snippet_html($kirby, 'koyomi/pagination', ['list' => ['pagination' => $first]]);
			$this->assertTrue(!str_contains($html, 'rel="prev"') && str_contains($html, 'rel="next"'), 'pagination: first page has no prev link');

			$single = array_merge($pager, ['pages' => 1, 'page' => 1]);
			$this->assertSame('', trim(koyomi_snippet_html($kirby, 'koyomi/pagination', ['list' => ['pagination' => $single]])), 'pagination: one page prints nothing');

			// ---- subscribe ----------------------------------------------------------------
			$calendar = $kirby->page('events');
			$html = koyomi_snippet_html($kirby, 'koyomi/subscribe', ['calendar' => $calendar]);
			$this->assertTrue(str_contains($html, '<div class="koyomi-subscribe">'), 'subscribe: wrapper');
			$this->assertTrue(str_contains($html, '<a href="webcal://example.test/events.ics">Subscribe to calendar</a>'), 'subscribe: webcal link');
			$this->assertTrue(
				str_contains(
					$html,
					'<p class="koyomi-subscribe-hint">Paste this address into your calendar app to see new dates automatically. <code>https://example.test/events.ics</code></p>'
				),
				'subscribe: hint with the ics address'
			);
			$this->assertTrue(str_contains($html, '<a href="https://example.test/events.rss" type="application/rss+xml">RSS feed</a>'), 'subscribe: rss link');

			$kirby = KoyomiFixture::app($root, ['koyomi.feed' => false]);
			$html = koyomi_snippet_html($kirby, 'koyomi/subscribe', ['calendar' => $kirby->page('events')]);
			$this->assertTrue(!str_contains($html, 'webcal') && str_contains($html, 'events.rss'), 'subscribe: feed off, rss still there');

			$kirby = KoyomiFixture::app($root, ['koyomi.feed' => false, 'koyomi.rss' => false]);
			$this->assertSame('', trim(koyomi_snippet_html($kirby, 'koyomi/subscribe', ['calendar' => $kirby->page('events')])), 'subscribe: both off prints nothing');

			// ---- layout -------------------------------------------------------------------
			$kirby = KoyomiFixture::app($root);
			$html = koyomi_snippet_html($kirby, 'koyomi/layout', ['page' => $kirby->page('events')]);
			$this->assertTrue(str_contains($html, '<html lang="en">'), 'layout: lang en');
			$this->assertTrue(str_contains($html, '<meta charset="utf-8">') && str_contains($html, 'width=device-width'), 'layout: charset and viewport');
			$this->assertTrue(str_contains($html, '<title>Events – Koyomi Test Site</title>'), 'layout: page title – site title');
			$this->assertTrue(str_contains($html, '<main class="koyomi-main">'), 'layout: main landmark');
			$this->assertTrue(!str_contains($html, 'application/ld+json'), 'layout: no JSON-LD on a calendar page');
			$kirby = KoyomiFixture::app($root, ['koyomi.language' => 'de', 'koyomi.active' => false]);
			$html = koyomi_snippet_html($kirby, 'koyomi/layout', ['page' => $kirby->page('events')]);
			$this->assertTrue(str_contains($html, '<html lang="de">') && str_contains($html, '<main class="koyomi-main">'), 'layout: prints even when inactive, German lang');

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
				$this->assertSame('', trim(koyomi_snippet_html($kirby, $name, $data)), "inactive: $name prints nothing");
			}

			// ---- site override -------------------------------------------------------------
			$kirby = KoyomiFixture::app($root);
			@mkdir($root . '/site/snippets/koyomi', 0700, true);
			file_put_contents($root . '/site/snippets/koyomi/card.php', 'OWN CARD <?= esc($event->title) ?>');
			$this->assertSame('OWN CARD Silent Retreat', trim(koyomi_snippet_html($kirby, 'koyomi/card', ['event' => $retreat])), 'site/snippets/koyomi/card.php wins');
			unlink($root . '/site/snippets/koyomi/card.php');

		} finally {
			KoyomiFixture::remove($root);
		}
	}
}

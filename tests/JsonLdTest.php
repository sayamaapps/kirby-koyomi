<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Koyomi\Attendance;
use Sayamaapps\Koyomi\Availability;
use Sayamaapps\Koyomi\Event;
use Sayamaapps\Koyomi\JsonLd;
use Sayamaapps\Koyomi\Status;

/** The retreat (the reference object) as raw content. */
function jsonld_retreat_fields(): array
{
	return [
		'id' => 'evt-retreat', 'title' => 'Silent Retreat', 'url' => 'https://example.test/events/retreat',
		'endDate' => '2027-03-14', 'endTime' => '13:00',
		'description' => 'Three days of silence.', 'attendance' => 'mixed', 'venue' => koyomi_raw_venue(),
		'onlinePlatform' => 'Zoom', 'categories' => 'Retreat, Meditation', 'languages' => 'de, cs',
		'price' => '380', 'availability' => 'limited', 'bookingUrl' => 'https://booking.example/retreat',
		'organizers' => [['id' => 'org-anna', 'name' => 'Anna Beispiel', 'kind' => 'person', 'website' => 'https://anna.example']],
		'image' => 'https://example.test/media/cover.jpg', 'modified' => 1791000000,
	];
}

/** The retreat with some fields replaced (array_replace, so null and '' are real values). */
function jsonld_retreat(array $changes = []): Event
{
	return Event::fromArray(koyomi_raw_event(array_replace(jsonld_retreat_fields(), $changes)));
}

/** The exact JSON-LD array of the reference retreat. */
function jsonld_reference(): array
{
	return [
		'@context' => 'https://schema.org',
		'@type' => 'Event',
		'name' => 'Silent Retreat',
		'description' => 'Three days of silence.',
		'url' => 'https://example.test/events/retreat',
		'image' => ['https://example.test/media/cover.jpg'],
		'startDate' => '2027-03-12T18:00:00+01:00',
		'endDate' => '2027-03-14T13:00:00+01:00',
		'eventAttendanceMode' => 'https://schema.org/MixedEventAttendanceMode',
		'eventStatus' => 'https://schema.org/EventScheduled',
		'location' => [
			['@type' => 'Place', 'name' => 'Seminarhaus Beispielhof',
				'address' => ['@type' => 'PostalAddress', 'streetAddress' => 'Musterstraße 1', 'postalCode' => '12345',
					'addressLocality' => 'Musterstadt', 'addressRegion' => 'Musterland', 'addressCountry' => 'DE'],
				'geo' => ['@type' => 'GeoCoordinates', 'latitude' => 48.123, 'longitude' => 11.456],
				'url' => 'https://beispielhof.example'],
			['@type' => 'VirtualLocation', 'url' => 'https://example.test/events/retreat'],
		],
		'offers' => ['@type' => 'Offer', 'url' => 'https://booking.example/retreat', 'price' => 380.0,
			'priceCurrency' => 'EUR', 'availability' => 'https://schema.org/LimitedAvailability'],
		'organizer' => ['@type' => 'Person', 'name' => 'Anna Beispiel', 'url' => 'https://anna.example'],
		'inLanguage' => ['de', 'cs'],
	];
}

/**
 * schema.org JSON-LD: the reference array of the retreat, every
 * rule (all-day, location, offers, organizer, language), the enumeration
 * mappings, encode()/script(), and the koyomi/jsonld snippet rendered by Kirby.
 */
#[CoversClass(JsonLd::class)]
final class JsonLdTest extends TestCase
{
	public function testSuite(): void
	{
		koyomi_require_core();


		$root = KoyomiFixture::create();

		try {

			// The whole array, key order included (=== on arrays compares order).
			$retreat = jsonld_retreat();
			expect(jsonld_reference(), JsonLd::event($retreat), 'reference array of the retreat');
			expect(array_keys(jsonld_reference()), array_keys(JsonLd::event($retreat)), 'key order');

			// Dates: all-day uses dates, end is the INCLUSIVE last day.
			$allDay = Event::fromArray(koyomi_raw_event(['id' => 'evt-workshop', 'title' => 'Breathing', 'startDate' => '2027-03-25',
				'startTime' => '', 'endDate' => '', 'endTime' => '', 'allDay' => true, 'attendance' => 'offline', 'venue' => null]));
			$d = JsonLd::event($allDay);
			expect('2027-03-25', $d['startDate'], 'all-day single day: start date');
			expect('2027-03-25', $d['endDate'], 'all-day single day: inclusive end date (exclusive DTEND minus one day)');

			$multi = jsonld_retreat(['allDay' => true, 'startTime' => '', 'endTime' => '', 'endDate' => '2027-03-14']);
			$d = JsonLd::event($multi);
			expect('2027-03-12', $d['startDate'], 'all-day multi-day: start date');
			expect('2027-03-14', $d['endDate'], 'all-day multi-day: last day inclusive');

			$utc = JsonLd::event(jsonld_retreat(['timezone' => 'UTC']));
			expect('2027-03-12T18:00:00+00:00', $utc['startDate'], 'UTC zone keeps +00:00 offset');

			// previousStartDate: only for rescheduled, after eventStatus.
			$concert = Event::fromArray(koyomi_raw_event(['id' => 'evt-concert', 'title' => 'Evening Concert', 'startDate' => '2027-03-20',
				'startTime' => '20:00', 'endDate' => '', 'endTime' => '', 'timezone' => 'Europe/Prague', 'status' => 'rescheduled',
				'previousStart' => '2027-02-20 20:00:00', 'attendance' => 'offline', 'venue' => koyomi_raw_venue()]));
			$d = JsonLd::event($concert);
			expect('2027-02-20T20:00:00+01:00', $d['previousStartDate'] ?? null, 'rescheduled: previousStartDate ISO');
			$keys = array_keys($d);
			holds(array_search('previousStartDate', $keys, true) === array_search('eventStatus', $keys, true) + 1, 'previousStartDate sits right after eventStatus');
			expect('https://schema.org/EventRescheduled', $d['eventStatus'], 'rescheduled: EventRescheduled');

			$allDayRescheduled = Event::fromArray(koyomi_raw_event(['startDate' => '2027-03-20', 'startTime' => '', 'allDay' => true,
				'endDate' => '', 'endTime' => '', 'status' => 'rescheduled', 'previousStart' => '2027-02-20', 'attendance' => 'offline', 'venue' => null]));
			expect('2027-02-20', JsonLd::event($allDayRescheduled)['previousStartDate'] ?? null, 'all-day rescheduled: previousStartDate as date');

			holds(!array_key_exists('previousStartDate', JsonLd::event(jsonld_retreat())), 'scheduled event: no previousStartDate');
			holds(
				!array_key_exists('previousStartDate', JsonLd::event(jsonld_retreat(['status' => 'cancelled', 'previousStart' => '2027-02-20 18:00']))),
				'cancelled event: no previousStartDate (only rescheduled)'
			);

			// Location: Place, VirtualLocation, lists and single objects.
			holds(
				!array_key_exists('location', JsonLd::event(jsonld_retreat(['attendance' => 'offline', 'venue' => null]))),
				'offline without venue: no location key'
			);

			$online = JsonLd::event(jsonld_retreat(['attendance' => 'online', 'venue' => null]));
			expect(
				['@type' => 'VirtualLocation', 'url' => 'https://example.test/events/retreat'],
				$online['location'],
				'online only: one VirtualLocation object (not a list) with the EVENT PAGE url'
			);
			expect('https://schema.org/OnlineEventAttendanceMode', $online['eventAttendanceMode'], 'online: OnlineEventAttendanceMode');

			$mixedNoVenue = JsonLd::event(jsonld_retreat(['venue' => null]));
			expect(
				['@type' => 'VirtualLocation', 'url' => 'https://example.test/events/retreat'],
				$mixedNoVenue['location'],
				'mixed without venue: only the VirtualLocation, as an object'
			);

			$movedOnline = JsonLd::event(jsonld_retreat(['status' => 'movedOnline']));
			expect(
				['@type' => 'VirtualLocation', 'url' => 'https://example.test/events/retreat'],
				$movedOnline['location'],
				'movedOnline with a venue: VirtualLocation only'
			);
			expect('https://schema.org/EventMovedOnline', $movedOnline['eventStatus'], 'movedOnline: EventMovedOnline');
			expect('https://schema.org/OnlineEventAttendanceMode', $movedOnline['eventAttendanceMode'], 'movedOnline: effective attendance online');

			$offlineVenue = JsonLd::event(jsonld_retreat(['attendance' => 'offline']));
			expect(['@type' => 'Place', 'name' => 'Seminarhaus Beispielhof',
				'address' => ['@type' => 'PostalAddress', 'streetAddress' => 'Musterstraße 1', 'postalCode' => '12345',
					'addressLocality' => 'Musterstadt', 'addressRegion' => 'Musterland', 'addressCountry' => 'DE'],
				'geo' => ['@type' => 'GeoCoordinates', 'latitude' => 48.123, 'longitude' => 11.456],
				'url' => 'https://beispielhof.example'], $offlineVenue['location'], 'offline with venue: one Place object (not a list)');

			$bare = JsonLd::event(jsonld_retreat(['attendance' => 'offline', 'venue' => ['name' => 'Garden']]));
			expect(['@type' => 'Place', 'name' => 'Garden'], $bare['location'], 'Place without address, geo or url: only the name');

			$equator = JsonLd::event(jsonld_retreat(['attendance' => 'offline', 'venue' => ['name' => 'Equator', 'lat' => '0', 'lng' => '0']]));
			expect(['@type' => 'GeoCoordinates', 'latitude' => 0.0, 'longitude' => 0.0], $equator['location']['geo'], 'coordinates 0 are kept');

			// The meeting link never appears, even when the raw data carries one.
			$withSecret = jsonld_retreat(['onlineUrl' => 'https://zoom.us/j/SECRET-LINK-1']);
			$json = JsonLd::encode(JsonLd::event($withSecret));
			holds(!str_contains($json, 'SECRET') && !str_contains($json, 'zoom.us'), 'onlineUrl never reaches the JSON-LD');

			// Offers.
			expect(
				['@type' => 'Offer', 'url' => 'https://example.test/events/retreat', 'price' => 0.0, 'priceCurrency' => 'CZK',
					'availability' => 'https://schema.org/LimitedAvailability'],
				JsonLd::event(jsonld_retreat(['isFree' => true, 'price' => '', 'currency' => 'CZK', 'bookingUrl' => '']))['offers'],
				'free event: price 0.0 (float), currency, url = event page'
			);

			expect(
				['@type' => 'Offer', 'url' => 'https://booking.example/retreat', 'availability' => 'https://schema.org/LimitedAvailability'],
				JsonLd::event(jsonld_retreat(['price' => null, 'isFree' => false]))['offers'],
				'booking link only: offers without price and priceCurrency'
			);

			expect(
				['@type' => 'Offer', 'url' => 'https://example.test/events/retreat', 'price' => 380.0, 'priceCurrency' => 'EUR',
					'availability' => 'https://schema.org/LimitedAvailability'],
				JsonLd::event(jsonld_retreat(['bookingUrl' => '']))['offers'],
				'price without booking link: url = event page'
			);

			holds(
				!array_key_exists('offers', JsonLd::event(jsonld_retreat(['price' => null, 'bookingUrl' => '', 'isFree' => false]))),
				'no price, not free, no booking: no offers key'
			);

			holds(
				array_keys(JsonLd::event(jsonld_retreat())['offers']) === ['@type', 'url', 'price', 'priceCurrency', 'availability'],
				'offers key order'
			);

			expect('https://schema.org/SoldOut', JsonLd::event(jsonld_retreat(['availability' => 'soldout']))['offers']['availability'], 'soldout inside offers');
			expect(
				'https://schema.org/InStock',
				JsonLd::event(jsonld_retreat(['availability' => 'available', 'price' => null, 'bookingUrl' => 'https://b.example/x']))['offers']['availability'],
				'available inside offers'
			);

			// Organizers: object for one, list for several, email only when public.
			expect(
				['@type' => 'Person', 'name' => 'Anna Beispiel', 'url' => 'https://anna.example'],
				JsonLd::event($retreat)['organizer'],
				'one organizer: an object, url = website, no email'
			);

			$two = JsonLd::event(jsonld_retreat(['organizers' => [
				['id' => 'org-anna', 'name' => 'Anna Beispiel', 'kind' => 'person', 'website' => 'https://anna.example'],
				['id' => 'org-sayama', 'name' => 'Sayama', 'kind' => 'organization', 'url' => 'https://example.test/organizers/sayama',
					'email' => 'info@sayama.example'],
			]]));
			expect([
				['@type' => 'Person', 'name' => 'Anna Beispiel', 'url' => 'https://anna.example'],
				['@type' => 'Organization', 'name' => 'Sayama', 'url' => 'https://example.test/organizers/sayama', 'email' => 'info@sayama.example'],
			], $two['organizer'], 'two organizers: a list; organization type; public email; url falls back to the organizer page');

			$hiddenEmail = JsonLd::event(jsonld_retreat(['organizers' => [['id' => 'org-x', 'name' => 'Anna Beispiel', 'kind' => 'person', 'email' => '']]]));
			holds(
				!array_key_exists('email', $hiddenEmail['organizer']) && !array_key_exists('url', $hiddenEmail['organizer']),
				'organizer without public email or url: neither key'
			);

			holds(!array_key_exists('organizer', JsonLd::event(jsonld_retreat(['organizers' => []]))), 'no organizers: no organizer key');

			// Languages.
			expect('de', JsonLd::event(jsonld_retreat(['languages' => 'de']))['inLanguage'], 'one language: a string');
			expect(['de', 'cs'], JsonLd::event(jsonld_retreat())['inLanguage'], 'several languages: a list');
			holds(!array_key_exists('inLanguage', JsonLd::event(jsonld_retreat(['languages' => '']))), 'no language: key omitted');

			// Empty values are omitted; free price 0 is not.
			$bareEvent = JsonLd::event(Event::fromArray(koyomi_raw_event(['id' => 'evt-bare', 'title' => 'Bare', 'url' => '',
				'startDate' => '2027-03-12', 'startTime' => '18:00', 'endDate' => '', 'endTime' => '', 'attendance' => 'offline'])));
			holds(
				!array_key_exists('url', $bareEvent) && !array_key_exists('image', $bareEvent) && !array_key_exists('description', $bareEvent),
				'empty url, image and description are omitted'
			);
			holds(!array_key_exists('location', $bareEvent) && !array_key_exists('offers', $bareEvent), 'no venue and no price: no location, no offers');

			// Every status mapping → full schema.org URLs.
			expect([
				Status::SCHEDULED => 'https://schema.org/EventScheduled',
				Status::CANCELLED => 'https://schema.org/EventCancelled',
				Status::POSTPONED => 'https://schema.org/EventPostponed',
				Status::RESCHEDULED => 'https://schema.org/EventRescheduled',
				Status::MOVED_ONLINE => 'https://schema.org/EventMovedOnline',
			], array_combine(Status::ALL, array_map(fn ($s) => JsonLd::eventStatus($s), Status::ALL)), 'eventStatus() of every Status');
			expect([
				Attendance::OFFLINE => 'https://schema.org/OfflineEventAttendanceMode',
				Attendance::ONLINE => 'https://schema.org/OnlineEventAttendanceMode',
				Attendance::MIXED => 'https://schema.org/MixedEventAttendanceMode',
			], array_combine(Attendance::ALL, array_map(fn ($a) => JsonLd::attendanceMode($a), Attendance::ALL)), 'attendanceMode() of every Attendance');
			expect([
				Availability::AVAILABLE => 'https://schema.org/InStock',
				Availability::LIMITED => 'https://schema.org/LimitedAvailability',
				Availability::SOLDOUT => 'https://schema.org/SoldOut',
			], array_combine(Availability::ALL, array_map(fn ($a) => JsonLd::availability($a), Availability::ALL)), 'availability() of every Availability');

			// encode() and script().
			$hostile = JsonLd::event(jsonld_retreat(['title' => 'A </script><script>alert(1)</script> & "B"']));
			$script = JsonLd::script($hostile);
			expect(1, substr_count($script, '</script>'), 'a </script> in the title cannot close the element early');
			$hex = '\\' . 'u'; // a literal backslash-u, as JSON_HEX_TAG writes it
			holds(
				str_contains($script, $hex . '003C/script' . $hex . '003E') && str_contains($script, $hex . '0026'),
				'angle brackets and ampersand are hex-escaped'
			);
			expect(
				'{"a":"/x","b":"Ä","c":380.0,"d":1.0}',
				JsonLd::encode(['a' => '/x', 'b' => 'Ä', 'c' => 380.0, 'd' => 1.0]),
				'slashes and unicode unescaped, zero fractions kept'
			);
			expect('{"@context":"https://schema.org"}', JsonLd::encode(['@context' => 'https://schema.org']), 'slashes unescaped');
			expect(['name' => 'A </script>'], json_decode(JsonLd::encode(['name' => 'A </script>']), true), 'encoded JSON decodes to the same value');
			expect('<script type="application/ld+json">{"@type":"Event"}</script>', JsonLd::script(['@type' => 'Event']), 'script() wrapper, no whitespace added');
			holds(str_contains(JsonLd::encode(JsonLd::event(jsonld_retreat())), '"price":380.0'), 'price 380.0 keeps its fraction in the output');
			holds(
				str_contains(JsonLd::encode(JsonLd::event(jsonld_retreat(['isFree' => true, 'price' => '']))), '"price":0.0'),
				'free price is 0.0 in the output'
			);

			// Kirby: the snippet renders exactly one script element.
			$kirby = KoyomiFixture::app($root);
			$html = (string)$kirby->snippet('koyomi/jsonld', ['event' => $retreat], true);
			expect(JsonLd::script(JsonLd::event($retreat)), $html, 'snippet output is exactly JsonLd::script(JsonLd::event($e))');
			expect(1, substr_count($html, '<script type="application/ld+json">'), 'snippet prints one script element');
			expect(1, substr_count($html, '</script>'), 'snippet prints one closing tag');

			$viaPage = (string)$kirby->snippet('koyomi/jsonld', ['page' => $retreat], true);
			expect($html, $viaPage, 'snippet also accepts the event as $page');

			expect('', (string)$kirby->snippet('koyomi/jsonld', ['event' => 'not an event'], true), 'snippet prints nothing for non-events');

			// No end in the content (review): no endDate, the default duration is not invented; an explicit end keeps it.
			$noEnd = JsonLd::event(Event::fromArray(koyomi_raw_event(['startTime' => '23:00', 'endTime' => ''])));
			holds(!array_key_exists('endDate', $noEnd) && $noEnd['startDate'] === '2027-03-12T23:00:00+01:00', 'JSON-LD: no endDate for a timed event without an end');
			expect('2027-03-14T00:00:00+01:00', JsonLd::event(Event::fromArray(koyomi_raw_event(['startTime' => '23:00', 'endTime' => '', 'endDate' => '2027-03-13'])))['endDate'], 'JSON-LD: an end date given keeps endDate');

			$inactive = KoyomiFixture::app($root, ['sayamaapps.koyomi.active' => false]);
			expect('', (string)$inactive->snippet('koyomi/jsonld', ['event' => $retreat], true), 'inactive: the snippet prints nothing');

		} finally {
			KoyomiFixture::remove($root);
		}
	}
}

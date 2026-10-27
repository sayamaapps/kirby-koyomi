<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Koyomi\Event;
use Sayamaapps\Koyomi\GoogleLink;
use Sayamaapps\Koyomi\I18n;

/** The query of a Google link, decoded (parse_str undoes the RFC 3986 encoding). */
function google_test_query(string $url): array
{
	parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
	return $query;
}

/**
 * GoogleLink: the example URL exactly, all-day and UTC events,
 * the location rules, the description cut, the details without a URL, and
 * that the language argument is restored. Kirby-free.
 */
#[CoversClass(GoogleLink::class)]
final class GoogleLinkTest extends TestCase
{
	public function testSuite(): void
	{
		koyomi_require_core();

		try {

			I18n::setLanguage('en');

			// The example URL, byte for byte.
			$retreat = Event::fromArray(koyomi_raw_event([
				'id' => 'evt-retreat', 'title' => 'Silent Retreat', 'url' => 'https://example.test/events/retreat',
				'endDate' => '2027-03-14', 'endTime' => '13:00',
				'description' => 'Three days of silence.', 'attendance' => 'mixed', 'venue' => koyomi_raw_venue(),
				'onlinePlatform' => 'Zoom', 'categories' => 'Retreat, Meditation', 'languages' => 'de, cs',
				'price' => '380', 'availability' => 'limited', 'bookingUrl' => 'https://booking.example/retreat',
				'organizers' => [['id' => 'org-anna', 'name' => 'Anna Beispiel', 'kind' => 'person', 'website' => 'https://anna.example']],
				'image' => 'https://example.test/media/cover.jpg', 'modified' => 1791000000,
			]));
			expect(
				'https://calendar.google.com/calendar/render?action=TEMPLATE&text=Silent%20Retreat&dates=20270312T180000%2F20270314T130000&ctz=Europe%2FBerlin&details=Three%20days%20of%20silence.%0A%0Ahttps%3A%2F%2Fexample.test%2Fevents%2Fretreat&location=Seminarhaus%20Beispielhof%2C%20Musterstra%C3%9Fe%201%2C%2012345%20Musterstadt%2C%20DE',
				GoogleLink::url($retreat, 'en'),
				'example URL'
			);

			// All-day: date form, exclusive end, no ctz.
			$allDay = Event::fromArray(koyomi_raw_event([
				'startDate' => '2027-03-25', 'startTime' => '', 'endDate' => '', 'endTime' => '', 'allDay' => true,
			]));
			$url = GoogleLink::url($allDay, 'en');
			holds(str_contains($url, '&dates=20270325%2F20270326'), 'all-day dates, end exclusive');
			holds(!str_contains($url, 'ctz='), 'all-day: no ctz');

			// A multi-day all-day event ends on the day after the last day.
			$multi = Event::fromArray(koyomi_raw_event([
				'startDate' => '2027-03-08', 'startTime' => '', 'endDate' => '2027-03-11', 'endTime' => '', 'allDay' => true,
			]));
			holds(str_contains(GoogleLink::url($multi, 'en'), 'dates=20270308%2F20270312'), 'all-day multi-day: end exclusive');

			// UTC zone: Z form, no ctz.
			// The wall time 18:00–20:00 is read as UTC here, so no offset is involved.
			$utc = Event::fromArray(koyomi_raw_event(['timezone' => 'UTC']));
			$url = GoogleLink::url($utc, 'en');
			holds(str_contains($url, 'dates=20270312T180000Z%2F20270312T200000Z'), 'UTC: Z form');
			holds(!str_contains($url, 'ctz='), 'UTC: no ctz');

			// Online only: the location is the word "Online".
			$online = Event::fromArray(koyomi_raw_event(['attendance' => 'online', 'onlinePlatform' => 'Zoom']));
			holds(str_ends_with(GoogleLink::url($online, 'en'), '&location=Online'), 'online only: location Online');
			expect('Online', google_test_query(GoogleLink::url($online, 'en'))['location'] ?? null, 'online only: decoded location');

			// The language argument changes the words, then is restored.
			I18n::setLanguage('en');
			holds(str_ends_with(GoogleLink::url($online, 'de'), '&location=Online'), 'online location uses the given language (de: Online)');
			expect('en', I18n::language(), 'language restored after url() with de');

			// No venue and not online: no location parameter. No description and no url: no details.
			$plain = Event::fromArray(koyomi_raw_event(['url' => '']));
			$url = GoogleLink::url($plain, 'en');
			holds(!str_contains($url, 'location='), 'no venue, not online: no location');
			holds(!str_contains($url, 'details='), 'no description, no url: no details');

			// Long description: 500 characters and an ellipsis, then the URL.
			$long = Event::fromArray(koyomi_raw_event(['description' => str_repeat('a', 600)]));
			$query = google_test_query(GoogleLink::url($long, 'en'));
			expect(str_repeat('a', 500) . "\u{2026}" . "\n\n" . 'https://example.test/events/test-event', $query['details'] ?? null, 'long description cut at 500 + ellipsis, then the url');

			// No url: the details contain only the description, no blank lines.
			$noUrl = Event::fromArray(koyomi_raw_event(['url' => '', 'description' => 'Short text.']));
			$query = google_test_query(GoogleLink::url($noUrl, 'en'));
			expect('Short text.', $query['details'] ?? null, 'no url: details without blank lines');

			// Description and url, no venue: details is the two parts only.
			$withUrl = Event::fromArray(koyomi_raw_event(['description' => 'Bring a cushion.']));
			$query = google_test_query(GoogleLink::url($withUrl, 'en'));
			expect("Bring a cushion.\n\nhttps://example.test/events/test-event", $query['details'] ?? null, 'details: description, blank line, url');

			// Title is encoded; spaces are %20, ampersand is %26.
			$titled = Event::fromArray(koyomi_raw_event(['title' => 'Tea & Breath']));
			holds(str_contains(GoogleLink::url($titled, 'en'), 'text=Tea%20%26%20Breath'), 'title encoded RFC 3986');

			// Parameter order: action, text, dates, ctz, details, location.
			$order = array_keys(google_test_query(GoogleLink::url($retreat, 'en')));
			expect(['action', 'text', 'dates', 'ctz', 'details', 'location'], $order, 'parameter order');

		} finally {
			I18n::setLanguage('en');
		}
	}
}

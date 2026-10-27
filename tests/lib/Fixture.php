<?php
/**
 * A throwaway Kirby site with a calendar, venues, organizers and events —
 * the shared fixture of every Kirby-booting test. Not a test itself.
 *
 *   $root = KoyomiFixture::create();
 *   $kirby = KoyomiFixture::app($root);                 // koyomi.active on, clock 2027-03-10 12:00
 *   $page = $kirby->page('events/retreat');
 *   $response = $kirby->render('events/retreat.ics');   // Kirby\Http\Response|null
 *   KoyomiFixture::remove($root);                       // in a finally block
 *
 * Content (clock 2027-03-10 12:00 Europe/Berlin; full field values below):
 *   events/ (koyomi-calendar, listed)
 *     retreat               listed   2027-03-12 18:00 – 03-14 13:00, mixed, Beispielhof, Zoom, 380 EUR, limited, featured, de+cs, Retreat+Meditation
 *     zoom-session          listed   2027-03-11 19:00–20:30, online, Zoom, free, en, organizer Sayama (public email)
 *     concert               listed   2027-03-20 20:00 (no end), Europe/Prague, Stadthalle Musterstadt, 450 CZK, rescheduled from 2027-02-20 20:00
 *     workshop-cancelled    listed   2027-03-25 all day, cancelled, Beispielhof
 *     past-workshop         listed   2027-02-01 10:00–17:00 (past), Beispielhof
 *     ongoing-retreat       listed   2027-03-08 – 03-11 all day (ongoing), movedOnline, Jitsi
 *     series-part-1/-2      listed   2027-04-01 / 2027-04-08 10:00–12:00, a series of two, Workshop
 *     unlisted-event        unlisted 2027-03-16 10:00
 *     draft-event           draft    2027-03-15 10:00
 *   venues/ (koyomi-venues, unlisted): beispielhof (DE, with map image), stadthalle (CZ, no coordinates)
 *   organizers/ (koyomi-organizers, unlisted): anna (person, email HIDDEN), sayama (organization, email public)
 *
 * Every event with online attendance stores an `onlineUrl` from SECRETS;
 * PrivacyTest asserts that none of them, and not HIDDEN_EMAIL, appears in
 * any output.
 *
 * Kirby keeps one Responder per App, and Page::kirby() returns the most
 * recently created App. A test that renders a 404 and then a 200 on the same
 * App gets the 404 twice, so it creates a fresh App for the second render.
 * Creating a second App also changes what $page->kirby() returns for pages of
 * the first, so re-create the main App before using its pages again.
 */

use Kirby\Cms\App;
use Kirby\Cms\Blueprint;
use Kirby\Template\Snippet;

final class KoyomiFixture
{
	public const HIDDEN_EMAIL = 'private-anna@example.org';
	public const NOW = '2027-03-10 12:00';
	public const PUBLIC_EMAIL = 'info@sayama.example';

	/** Fragments that must never appear anywhere either (the IDs without the URL around them). */
	public const SECRET_FRAGMENTS = ['SECRET-RETREAT', 'SECRET-ZOOM', 'SECRET-ONGOING-ROOM', 'SECRET-UNLISTED', '1111111111', '2222222222', '3333333333'];
	public const SECRETS = [
		'https://zoom.us/j/1111111111?pwd=SECRET-RETREAT',
		'https://us02web.zoom.us/j/2222222222?pwd=SECRET-ZOOM',
		'https://meet.jit.si/SECRET-ONGOING-ROOM',
		'https://zoom.us/j/3333333333?pwd=SECRET-UNLISTED',
	];
	public const URL = 'https://example.test';

	/**
	 * A fresh App on the fixture. Defaults: url https://example.test,
	 * koyomi.active true, koyomi.now 2027-03-10 12:00, debug on. $options
	 * are merged over them (pass 'koyomi.active' => false to switch off).
	 * Clears Kirby's per-process snippet and blueprint caches and Koyomi's
	 * Repository cache, so options of this App take effect.
	 */
	public static function app(string $root, array $options = []): App
	{
		Snippet::$cache = [];
		Blueprint::$loaded = [];
		return new App([
			'roots' => [
				'index' => $root . '/public',
				'base' => $root,
				'content' => $root . '/content',
				'site' => $root . '/site',
				'storage' => $root . '/storage',
				'media' => $root . '/public/media',
			],
			'options' => array_merge([
				'url' => self::URL,
				'debug' => true,
				'koyomi.active' => true,
				'koyomi.now' => self::NOW,
			], $options),
		]);
	}

	/** Creates the site in a new temp directory and returns its root. */
	public static function create(): string
	{
		$root = sys_get_temp_dir() . '/koyomi-fixture-' . bin2hex(random_bytes(6));
		foreach (['public', 'site/plugins', 'site/templates', 'site/snippets', 'site/blueprints/pages', 'storage', 'content'] as $dir) {
			mkdir($root . '/' . $dir, 0700, true);
		}
		symlink(dirname(__DIR__, 2), $root . '/site/plugins/koyomi');
		file_put_contents($root . '/site/templates/default.php', '<?= $page->title()->escape() ?>');

		$files = [
			'site.txt' => ['Title' => 'Koyomi Test Site'],
			'home/home.txt' => ['Title' => 'Home'],
			'error/error.txt' => ['Title' => 'Error'],

			'1_events/koyomi-calendar.txt' => ['Title' => 'Events', 'Intro' => 'All dates.', 'Uuid' => 'calendar-main'],

			'1_events/20270312_retreat/koyomi-event.txt' => [
				'Title' => 'Silent Retreat', 'Uuid' => 'evt-retreat',
				'Excerpt' => 'Three days of silence in the mountains.',
				'Text' => '[{"content":{"text":"<p>Details of the retreat.</p>"},"id":"b1","isHidden":false,"type":"text"}]',
				'Cover' => '- file://file-retreat-cover',
				'StartDate' => '2027-03-12', 'StartTime' => '18:00:00', 'EndDate' => '2027-03-14', 'EndTime' => '13:00:00',
				'AllDay' => 'false', 'Timezone' => 'Europe/Berlin',
				'InPerson' => 'true', 'Online' => 'true',
				'Venue' => '- page://venue-beispielhof', 'OnlinePlatform' => 'Zoom', 'OnlineUrl' => self::SECRETS[0],
				'EventStatus' => 'scheduled',
				'IsFree' => 'false', 'Price' => '380', 'Currency' => 'EUR', 'PriceNote' => 'Frühbucher bis 1. Februar',
				'Availability' => 'limited', 'BookingUrl' => 'https://booking.example/retreat', 'BookingLabel' => '',
				'Organizers' => '- page://org-anna', 'Categories' => 'Retreat, Meditation', 'EventLanguages' => 'de, cs',
				'Featured' => 'true',
			],
			'1_events/20270312_retreat/cover.jpg.txt' => ['Uuid' => 'file-retreat-cover', 'Alt' => 'Sunrise over the mountains'],

			'1_events/20270311_zoom-session/koyomi-event.txt' => [
				'Title' => 'Evening Meditation on Zoom', 'Uuid' => 'evt-zoom',
				'Excerpt' => 'Meditate together from home.',
				'StartDate' => '2027-03-11', 'StartTime' => '19:00:00', 'EndDate' => '', 'EndTime' => '20:30:00',
				'AllDay' => 'false', 'Timezone' => 'Europe/Berlin',
				'InPerson' => 'false', 'Online' => 'true', 'OnlinePlatform' => 'Zoom', 'OnlineUrl' => self::SECRETS[1],
				'EventStatus' => 'scheduled', 'IsFree' => 'true', 'Availability' => 'available',
				'Organizers' => '- page://org-sayama', 'Categories' => 'Meditation', 'EventLanguages' => 'en',
			],

			'1_events/20270320_concert/koyomi-event.txt' => [
				'Title' => 'Evening Concert', 'Uuid' => 'evt-concert',
				'Excerpt' => 'Mantra concert.',
				'StartDate' => '2027-03-20', 'StartTime' => '20:00:00', 'EndDate' => '', 'EndTime' => '',
				'AllDay' => 'false', 'Timezone' => 'Europe/Prague',
				'InPerson' => 'true', 'Online' => 'false', 'Venue' => '- page://venue-stadthalle',
				'EventStatus' => 'rescheduled', 'PreviousStart' => '2027-02-20 20:00:00', 'StatusNote' => 'New date after the venue flooded.',
				'IsFree' => 'false', 'Price' => '450', 'Currency' => 'CZK', 'Availability' => 'available',
				'BookingUrl' => 'https://tickets.example/concert', 'BookingLabel' => 'Tickets',
				'Organizers' => '- page://org-anna', 'Categories' => 'Concert', 'EventLanguages' => 'cs, de',
			],

			'1_events/20270325_workshop-cancelled/koyomi-event.txt' => [
				'Title' => 'Breathing Workshop', 'Uuid' => 'evt-cancelled',
				'StartDate' => '2027-03-25', 'StartTime' => '', 'EndDate' => '', 'EndTime' => '',
				'AllDay' => 'true', 'Timezone' => 'Europe/Berlin',
				'InPerson' => 'true', 'Online' => 'false', 'Venue' => '- page://venue-beispielhof',
				'EventStatus' => 'cancelled', 'StatusNote' => 'Wegen Krankheit abgesagt.',
				'IsFree' => 'false', 'Price' => '90', 'Currency' => 'EUR', 'Availability' => 'available',
				'Categories' => 'Workshop', 'EventLanguages' => 'de',
			],

			'1_events/20270201_past-workshop/koyomi-event.txt' => [
				'Title' => 'Past Workshop', 'Uuid' => 'evt-past',
				'StartDate' => '2027-02-01', 'StartTime' => '10:00:00', 'EndDate' => '', 'EndTime' => '17:00:00',
				'AllDay' => 'false', 'Timezone' => 'Europe/Berlin',
				'InPerson' => 'true', 'Online' => 'false', 'Venue' => '- page://venue-beispielhof',
				'EventStatus' => 'scheduled', 'IsFree' => 'false', 'Price' => '120', 'Currency' => 'EUR',
				'Categories' => 'Workshop', 'EventLanguages' => 'de',
			],

			'1_events/20270308_ongoing-retreat/koyomi-event.txt' => [
				'Title' => 'Spring Retreat', 'Uuid' => 'evt-ongoing',
				'StartDate' => '2027-03-08', 'StartTime' => '', 'EndDate' => '2027-03-11', 'EndTime' => '',
				'AllDay' => 'true', 'Timezone' => 'Europe/Berlin',
				'InPerson' => 'true', 'Online' => 'false', 'Venue' => '- page://venue-beispielhof',
				'OnlinePlatform' => 'Jitsi', 'OnlineUrl' => self::SECRETS[2],
				'EventStatus' => 'movedOnline', 'StatusNote' => 'Now online only.',
				'IsFree' => 'false', 'Price' => '250', 'Currency' => 'EUR', 'Availability' => 'soldout',
				'Categories' => 'Retreat', 'EventLanguages' => 'de',
			],

			'1_events/20270401_series-part-1/koyomi-event.txt' => [
				'Title' => 'Mantra Course 1', 'Uuid' => 'evt-series-1',
				'StartDate' => '2027-04-01', 'StartTime' => '10:00:00', 'EndDate' => '', 'EndTime' => '12:00:00',
				'Timezone' => 'Europe/Berlin', 'InPerson' => 'true', 'Online' => 'false', 'Venue' => '- page://venue-beispielhof',
				'IsFree' => 'true', 'Categories' => 'Workshop', 'EventLanguages' => 'de',
				'Series' => '- page://evt-series-2', 'SeriesPart' => '1',
			],
			'1_events/20270408_series-part-2/koyomi-event.txt' => [
				'Title' => 'Mantra Course 2', 'Uuid' => 'evt-series-2',
				'StartDate' => '2027-04-08', 'StartTime' => '10:00:00', 'EndDate' => '', 'EndTime' => '12:00:00',
				'Timezone' => 'Europe/Berlin', 'InPerson' => 'true', 'Online' => 'false', 'Venue' => '- page://venue-beispielhof',
				'IsFree' => 'true', 'Categories' => 'Workshop', 'EventLanguages' => 'de',
				'Series' => '- page://evt-series-1', 'SeriesPart' => '2',
			],

			'1_events/unlisted-event/koyomi-event.txt' => [
				'Title' => 'Unlisted Event', 'Uuid' => 'evt-unlisted',
				'StartDate' => '2027-03-16', 'StartTime' => '10:00:00', 'EndTime' => '11:00:00', 'Timezone' => 'Europe/Berlin',
				'InPerson' => 'false', 'Online' => 'true', 'OnlinePlatform' => 'Zoom', 'OnlineUrl' => self::SECRETS[3],
				'IsFree' => 'true',
			],
			'1_events/_drafts/draft-event/koyomi-event.txt' => [
				'Title' => 'Draft Event', 'Uuid' => 'evt-draft',
				'StartDate' => '2027-03-15', 'StartTime' => '10:00:00', 'Timezone' => 'Europe/Berlin',
				'InPerson' => 'true', 'Online' => 'false',
			],

			'venues/koyomi-venues.txt' => ['Title' => 'Venues', 'Uuid' => 'venues-main'],
			'venues/beispielhof/koyomi-venue.txt' => [
				'Title' => 'Seminarhaus Beispielhof', 'Uuid' => 'venue-beispielhof',
				'Street' => 'Musterstraße 1', 'PostalCode' => '12345', 'City' => 'Musterstadt', 'Region' => 'Musterland', 'Country' => 'DE',
				'Lat' => '48.123', 'Lng' => '11.456', 'MapImage' => '- file://file-beispielhof-map', 'ShowMap' => 'true',
				'Website' => 'https://beispielhof.example', 'Directions' => 'Ten minutes from the station.',
			],
			'venues/beispielhof/map.jpg.txt' => ['Uuid' => 'file-beispielhof-map', 'Alt' => 'Map of the Beispielhof area'],
			'venues/stadthalle/koyomi-venue.txt' => [
				'Title' => 'Stadthalle Musterstadt', 'Uuid' => 'venue-stadthalle',
				'Street' => 'Musterstraße 1', 'PostalCode' => '12345', 'City' => 'Musterstadt', 'Country' => 'CZ',
				'ShowMap' => 'false',
			],

			'organizers/koyomi-organizers.txt' => ['Title' => 'Organizers', 'Uuid' => 'organizers-main'],
			'organizers/anna/koyomi-organizer.txt' => [
				'Title' => 'Anna Beispiel', 'Uuid' => 'org-anna', 'Kind' => 'person',
				'Email' => self::HIDDEN_EMAIL, 'ShowEmail' => 'false', 'Website' => 'https://anna.example',
			],
			'organizers/sayama/koyomi-organizer.txt' => [
				'Title' => 'Sayama', 'Uuid' => 'org-sayama', 'Kind' => 'organization',
				'Email' => self::PUBLIC_EMAIL, 'ShowEmail' => 'true', 'Website' => 'https://sayama.example',
			],
		];

		foreach ($files as $path => $fields) {
			self::write($root . '/content/' . $path, $fields);
		}
		// Image files referenced above (bytes do not matter; never resized in tests).
		file_put_contents($root . '/content/1_events/20270312_retreat/cover.jpg', 'jpg');
		file_put_contents($root . '/content/venues/beispielhof/map.jpg', 'jpg');
		return $root;
	}

	public static function remove(string $root): void
	{
		if (!is_dir($root) || !str_contains($root, 'koyomi-fixture-')) {
			return;
		}
		$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($it as $file) {
			$file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}
		rmdir($root);
	}

	/** Writes a Kirby content file (fields separated by "----"; multi-line values start on a new line). */
	public static function write(string $file, array $fields): void
	{
		@mkdir(dirname($file), 0700, true);
		$parts = [];
		foreach ($fields as $key => $value) {
			$value = (string)$value;
			$parts[] = $key . ':' . (str_contains($value, "\n") || str_starts_with($value, '- ') ? "\n\n" : ' ') . $value;
		}
		file_put_contents($file, implode("\n\n----\n\n", $parts) . "\n");
	}
}

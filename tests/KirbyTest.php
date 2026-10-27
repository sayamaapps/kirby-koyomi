<?php

use Kirby\Cms\App;
use Kirby\Uuid\Uuids;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Koyomi\Event;
use Sayamaapps\Koyomi\Mapper;
use Sayamaapps\Koyomi\Models\CalendarPage;
use Sayamaapps\Koyomi\Models\EventPage;
use Sayamaapps\Koyomi\Models\OrganizerPage;
use Sayamaapps\Koyomi\Models\VenuePage;
use Sayamaapps\Koyomi\Normalize;
use Sayamaapps\Koyomi\Organizer;
use Sayamaapps\Koyomi\Repository;
use Sayamaapps\Koyomi\Venue;

/** Runs $fn with error_log() going to $file; returns what was logged there. */
function koyomi_logged(string $file, callable $fn): string
{
	@unlink($file);
	$previous = ini_get('error_log');
	ini_set('error_log', $file);
	try {
		$fn();
	} finally {
		ini_set('error_log', $previous === false ? '' : $previous);
	}
	return is_file($file) ? (string)file_get_contents($file) : '';
}

/** Titles of a list of events, in order. */
function koyomi_titles(array $events): array
{
	return array_map(fn (Event $e) => $e->title, $events);
}

/** Expiry of a response as a timestamp, for a wall time in Europe/Berlin. */
function koyomi_berlin(string $wall): int
{
	return (new DateTimeImmutable($wall, new DateTimeZone('Europe/Berlin')))->getTimestamp();
}

/**
 * The Kirby glue on a real site (tests/lib/Fixture.php): Mapper, Repository
 * (cache, lists, venues, organizers), the page models, the collections and
 * the representation gating and headers of Output.
 *
 * The bodies of .ics/.rss/.json are checked by their own tests and by
 * AcceptanceTest; this file checks status codes, headers, expiry and data.
 */
#[CoversNothing]
final class KirbyTest extends TestCase
{
	public function testSuite(): void
	{
		$root = KoyomiFixture::create();

		$extra = KoyomiFixture::create();

		$upcomingTitles = ['Spring Retreat', 'Evening Meditation on Zoom', 'Silent Retreat', 'Evening Concert', 'Breathing Workshop', 'Mantra Course 1', 'Mantra Course 2'];


		// The extra site: an event with an impossible date, and two whose description comes from text blocks.
		KoyomiFixture::write($extra . '/content/1_events/20270399_broken/koyomi-event.txt', [
			'Title' => 'Broken Event', 'Uuid' => 'evt-broken',
			'StartDate' => '2027-02-30', 'StartTime' => '10:00:00', 'Timezone' => 'Europe/Berlin', 'InPerson' => 'true', 'Online' => 'false',
		]);

		KoyomiFixture::write($extra . '/content/1_events/20270402_fallback-text/koyomi-event.txt', [
			'Title' => 'Fallback Text', 'Uuid' => 'evt-fallback',
			'StartDate' => '2027-04-02', 'StartTime' => '10:00:00', 'Timezone' => 'Europe/Berlin', 'InPerson' => 'true', 'Online' => 'false',
			'Text' => '[{"content":{"text":"<p>First &amp; second paragraph.</p>"},"id":"b1","isHidden":false,"type":"text"},'
				. '{"content":{"text":"<p>Second block with <strong>bold</strong> text.</p>"},"id":"b2","isHidden":false,"type":"text"}]',
		]);

		KoyomiFixture::write($extra . '/content/1_events/20270403_long-text/koyomi-event.txt', [
			'Title' => 'Long Text', 'Uuid' => 'evt-long',
			'StartDate' => '2027-04-03', 'StartTime' => '10:00:00', 'Timezone' => 'Europe/Berlin', 'InPerson' => 'true', 'Online' => 'false',
			'Text' => '[{"content":{"text":"<p>' . str_repeat('Long words here and there. ', 40) . '</p>"},"id":"b1","isHidden":false,"type":"text"}]',
		]);

		$logFile = $extra . '/storage/error.log';


		$partOne = function () use ($root, $upcomingTitles) {
			$code = fn (App $kirby, string $path) => $kirby->render($path)?->code() ?? 404;

			// 1. Mapper::raw() of the fixture events.
			$kirby = KoyomiFixture::app($root);
			$raw = fn (string $path): array => Mapper::raw($kirby->page($path));

			$retreat = $raw('events/retreat');
			$this->assertSame('evt-retreat', $retreat['id'], 'raw: id from the page UUID');
			$this->assertSame('mixed', $retreat['attendance'], 'raw: attendance from the two toggles (in person and online)');
			$this->assertTrue($retreat['venue'] instanceof Venue && $retreat['venue']->id === 'venue-beispielhof', 'raw: venue resolved from page://venue-beispielhof');
			$this->assertSame('Sunrise over the mountains', $retreat['imageAlt'], 'raw: cover alt text');
			$this->assertTrue(str_starts_with($retreat['image'], 'https://example.test/') && str_ends_with($retreat['image'], '/cover.jpg'), 'raw: cover is an absolute URL');
			$this->assertTrue($retreat['organizers'][0] instanceof Organizer && $retreat['organizers'][0]->id === 'org-anna', 'raw: organizer resolved');
			$this->assertSame('', $retreat['organizers'][0]->email, 'raw: hidden organizer email stays out');
			$this->assertSame('Three days of silence in the mountains.', $retreat['description'], 'raw: the excerpt wins over text blocks');

			$zoom = $raw('events/zoom-session');
			$this->assertSame('online', $zoom['attendance'], 'raw: inPerson off and online on is online');
			$this->assertSame(null, $zoom['venue'], 'raw: no venue');
			$this->assertSame(KoyomiFixture::PUBLIC_EMAIL, $zoom['organizers'][0]->email, 'raw: showEmail on keeps the public email');

			$concert = $raw('events/concert');
			$this->assertSame('offline', $concert['attendance'], 'raw: in person only is offline');
			$this->assertSame(['rescheduled', '2027-02-20 20:00:00'], [$concert['status'], $concert['previousStart']], 'raw: status and previousStart as stored');

			$ongoing = $raw('events/ongoing-retreat');
			$this->assertSame(['offline', 'movedOnline'], [$ongoing['attendance'], $ongoing['status']], 'raw: stored attendance stays, status moves it online');

			$cancelled = $raw('events/workshop-cancelled');
			$this->assertSame(true, Normalize::bool($cancelled['allDay']), 'raw: all-day toggle');
			$this->assertSame('', $cancelled['description'], 'raw: no excerpt and no text gives an empty description');

			$series = $raw('events/series-part-1');
			$this->assertSame(
				['evt-series-2', 'Mantra Course 2', '2'],
				[$series['series'][0]['id'], $series['series'][0]['title'], $series['series'][0]['part']],
				'raw: series sibling resolved with its part'
			);
			$this->assertSame('1', $series['seriesPart'], 'raw: own seriesPart');

			$event = Mapper::event($kirby->page('events/retreat'), ['timezone' => 'Europe/Berlin', 'currency' => 'EUR', 'defaultDuration' => 120]);
			$this->assertSame(['Silent Retreat', 'mixed', ['de', 'cs']], [$event->title, $event->attendance, $event->languages], 'Mapper::event builds the Event');

			// Privacy: no onlineUrl and no hidden email anywhere in the mapped data.
			$mapped = '';
			foreach (['events/retreat', 'events/zoom-session', 'events/concert', 'events/workshop-cancelled', 'events/past-workshop',
				'events/ongoing-retreat', 'events/series-part-1', 'events/series-part-2'] as $path) {
				$mapped .= json_encode(Mapper::raw($kirby->page($path)));
			}
			$mapped .= json_encode(Mapper::organizer($kirby->page('organizers/anna')));
			foreach (KoyomiFixture::SECRET_FRAGMENTS as $fragment) {
				$this->assertTrue(!str_contains($mapped, $fragment), 'privacy: no online meeting secret in mapped data: ' . $fragment);
			}
			$this->assertTrue(!str_contains($mapped, KoyomiFixture::HIDDEN_EMAIL), 'privacy: hidden organizer email absent');
			$this->assertTrue(!str_contains(json_encode(Mapper::event($kirby->page('events/retreat'))->toArray()), 'onlineUrl'), 'privacy: Event::toArray() has no onlineUrl');

			// 2. Mapper::id(), with and without UUIDs.
			$this->assertSame('evt-retreat', Mapper::id($kirby->page('events/retreat')), 'id: UUID id without page://');
			Uuids::$enabled = false;
			try {
				$this->assertSame('events-retreat', Mapper::id($kirby->page('events/retreat')), 'id: page id with "/" replaced when UUIDs are off');
			} finally {
				Uuids::$enabled = null;
			}

			// 3. Venues and organizers.
			$beispielhof = Mapper::venue($kirby->page('venues/beispielhof'));
			$this->assertSame(['venue-beispielhof', 'Seminarhaus Beispielhof', 'DE'], [$beispielhof->id, $beispielhof->name, $beispielhof->country], 'venue: id, name, country');
			$this->assertTrue($beispielhof->hasCoordinates() && abs($beispielhof->lat - 48.123) < 1e-9, 'venue: coordinates');
			$this->assertSame(
				'https://www.openstreetmap.org/?mlat=48.123&mlon=11.456#map=17/48.123/11.456',
				$beispielhof->mapLink(),
				'venue: OpenStreetMap link from the coordinates'
			);
			$this->assertTrue($beispielhof->showMap && str_ends_with($beispielhof->toArray()['mapImage'], '/map.jpg'), 'venue: map image shown with showMap');
			$this->assertSame('Map of the Beispielhof area', $beispielhof->mapImageAlt, 'venue: map image alt');
			$stadthalle = Mapper::venue($kirby->page('venues/stadthalle'));
			$this->assertTrue(!$stadthalle->hasCoordinates() && !$stadthalle->showMap && $stadthalle->mapImage === '', 'venue without coordinates, map or showMap');
			$this->assertSame(
				'https://www.openstreetmap.org/search?query=' . rawurlencode('Musterstraße 1, 12345 Musterstadt, CZ'),
				$stadthalle->mapLink(),
				'venue: address search link without coordinates'
			);
			$customMap = KoyomiFixture::app($root, ['sayamaapps.koyomi.mapUrl' => fn (Venue $v): string => 'https://maps.example/' . $v->id]);
			$this->assertSame('https://maps.example/venue-beispielhof', Mapper::venue($customMap->page('venues/beispielhof'))->mapLink(), 'venue: koyomi.mapUrl closure');
			// Page::kirby() is App::instance(), the most recently created App: re-create the main one before using its pages.
			$kirby = KoyomiFixture::app($root);

			$anna = Mapper::organizer($kirby->page('organizers/anna'));
			$this->assertSame(['', 'person', 'https://anna.example'], [$anna->email, $anna->kind, $anna->website], 'organizer: hidden email stays empty');
			$sayama = Mapper::organizer($kirby->page('organizers/sayama'));
			$this->assertSame([KoyomiFixture::PUBLIC_EMAIL, 'organization'], [$sayama->email, $sayama->kind], 'organizer: public email kept');

			// 4. Repository: containers, collections, listed events only.
			$this->assertSame('events', Repository::calendar($kirby)?->id(), 'calendar found by template');
			$this->assertSame(
				'events',
				KoyomiFixture::app($root, ['sayamaapps.koyomi.calendar' => 'venues'])->site()->koyomiCalendar()?->id(),
				'calendar option naming another template falls back'
			);
			$this->assertSame(
				'events',
				KoyomiFixture::app($root, ['sayamaapps.koyomi.calendar' => 'nope'])->site()->koyomiCalendar()?->id(),
				'calendar option naming a missing page falls back'
			);
			// Page::kirby() is App::instance(), the most recently created App: re-create the main one before using its pages.
			$kirby = KoyomiFixture::app($root);
			$this->assertSame('venues', Repository::venuesPage($kirby)?->id(), 'venues container found');
			$this->assertSame(
				'venues',
				Repository::venuesPage(KoyomiFixture::app($root, ['sayamaapps.koyomi.venues' => 'events']))?->id(),
				'venues option naming another template falls back'
			);
			// Page::kirby() is App::instance(), the most recently created App: re-create the main one before using its pages.
			$kirby = KoyomiFixture::app($root);
			$this->assertSame('organizers', Repository::organizersPage($kirby)?->id(), 'organizers container found');
			$venueSlugs = array_map(fn ($p) => $p->slug(), iterator_to_array(Repository::venuePages($kirby)));
			sort($venueSlugs);
			$this->assertSame(['beispielhof', 'stadthalle'], $venueSlugs, 'venue pages: the children of the container');
			$this->assertSame(2, count(Repository::organizerPages($kirby)), 'organizer pages: two');
			$this->assertSame(2, $kirby->collection('koyomi/venues')->count(), 'collection koyomi/venues');
			$this->assertSame(2, $kirby->collection('koyomi/organizers')->count(), 'collection koyomi/organizers');
			$this->assertSame(8, $kirby->collection('koyomi/events')->count(), 'collection koyomi/events: listed events only');
			$eventIds = array_map(fn ($p) => $p->id(), iterator_to_array(Repository::eventPages($kirby->page('events'))));
			sort($eventIds);
			$this->assertSame(
				[
					'events/concert',
					'events/ongoing-retreat',
					'events/past-workshop',
					'events/retreat',
					'events/series-part-1',
					'events/series-part-2',
					'events/workshop-cancelled',
					'events/zoom-session'
				],
				$eventIds,
				'eventPages: unlisted and draft events are left out'
			);
			$this->assertSame(0, count(Repository::eventPages(null)), 'eventPages without a calendar is empty');

			$calendar = $kirby->page('events');
			$this->assertSame($upcomingTitles, koyomi_titles(Repository::upcoming($calendar)), 'upcoming: ongoing first, by start, cancelled listed');
			$this->assertSame(['Past Workshop'], koyomi_titles(Repository::past($calendar)), 'past');
			$this->assertSame(['Silent Retreat', 'Evening Concert'], koyomi_titles(Repository::upcoming($calendar, ['language' => 'cs'])), 'upcoming with a filter');
			$this->assertSame(
				array_values(array_diff($upcomingTitles, ['Breathing Workshop'])),
				koyomi_titles(Repository::upcoming(KoyomiFixture::app($root, ['sayamaapps.koyomi.hideCancelled' => true])->page('events'))),
				'hideCancelled removes the cancelled event from upcoming'
			);
			// Page::kirby() is App::instance(), the most recently created App: re-create the main one before using its pages.
			$kirby = KoyomiFixture::app($root);
			$this->assertSame(
				['Silent Retreat', 'Breathing Workshop', 'Mantra Course 1', 'Mantra Course 2'],
				koyomi_titles(Repository::forVenue($kirby, $kirby->page('venues/beispielhof'))),
				'forVenue: upcoming in person at the venue; the moved-online retreat is left out'
			);
			$this->assertSame(['Evening Concert'], koyomi_titles(Repository::forVenue($kirby, $kirby->page('venues/stadthalle'))), 'forVenue: the other venue');
			$this->assertSame(
				['Silent Retreat', 'Evening Concert'],
				koyomi_titles(Repository::forOrganizer($kirby, $kirby->page('organizers/anna'))),
				'forOrganizer: Anna Beispiel'
			);
			$this->assertSame(['Evening Meditation on Zoom'], koyomi_titles(Repository::forOrganizer($kirby, $kirby->page('organizers/sayama'))), 'forOrganizer: Sayama');

			// 5. list(): shape, facets, filters, URLs, paging.
			$list = Repository::list($calendar, []);
			$this->assertSame(
				['view', 'filters', 'events', 'pagination', 'facets', 'title', 'baseUrl', 'upcomingUrl', 'pastUrl', 'filtered'],
				array_keys($list),
				'list: keys of the list shape'
			);
			$this->assertSame(['page', 'pages', 'total', 'perPage', 'hasPrev', 'hasNext', 'prevUrl', 'nextUrl'], array_keys($list['pagination']), 'list: pagination keys');
			$this->assertSame($upcomingTitles, koyomi_titles($list['events']), 'list: default view is upcoming, one page');
			$this->assertSame(
				[1, 1, 7, 12, null, null],
				[
					$list['pagination']['page'],
					$list['pagination']['pages'],
					$list['pagination']['total'],
					$list['pagination']['perPage'],
					$list['pagination']['prevUrl'],
					$list['pagination']['nextUrl']
				],
				'list: one page of seven'
			);
			$this->assertSame(
				['https://example.test/events', 'https://example.test/events?view=past', 'Events', false],
				[$list['baseUrl'], $list['pastUrl'], $list['title'], $list['filtered']],
				'list: URLs, title, filtered'
			);
			$this->assertSame('https://example.test/events', $list['upcomingUrl'], 'list: upcomingUrl');
			$this->assertSame(
				['Concert' => 1, 'Meditation' => 2, 'Retreat' => 2, 'Workshop' => 3],
				$list['facets']['categories'],
				'list: category facets over the unfiltered view'
			);
			$this->assertSame(['cs' => 2, 'de' => 6, 'en' => 1], $list['facets']['languages'], 'list: language facets');
			$this->assertSame(['offline' => 5, 'online' => 3, 'mixed' => 1], $list['facets']['attendance'], 'list: attendance facets');

			$filtered = Repository::list($calendar, ['category' => 'retreat']);
			$this->assertSame(true, $filtered['filtered'], 'list: a category filter marks the list as filtered');
			$this->assertSame(['Spring Retreat', 'Silent Retreat'], koyomi_titles($filtered['events']), 'list: category filter, case-insensitive');
			$this->assertSame(['Concert' => 1, 'Meditation' => 2, 'Retreat' => 2, 'Workshop' => 3], $filtered['facets']['categories'], 'list: facets ignore the filter');

			$beyond = Repository::list($calendar, ['category' => 'retreat', 'page' => '9']);
			$this->assertSame([1, 1], [$beyond['pagination']['page'], $beyond['pagination']['pages']], 'list: a page beyond the end shows the last page');

			$perPageOne = KoyomiFixture::app($root, ['sayamaapps.koyomi.perPage' => 1]);
			$pagedOne = Repository::list($perPageOne->page('events'), ['category' => 'retreat', 'page' => '2']);
			$this->assertSame(['Silent Retreat'], koyomi_titles($pagedOne['events']), 'list: page 2 with one event per page');
			$this->assertSame(
				[2, 2, 2],
				[$pagedOne['pagination']['page'], $pagedOne['pagination']['pages'], $pagedOne['pagination']['total']],
				'list: pagination numbers of the filtered list'
			);
			$this->assertSame('https://example.test/events?category=retreat', $pagedOne['pagination']['prevUrl'], 'list: previous URL keeps the filter and drops page 1');
			$this->assertSame(null, $pagedOne['pagination']['nextUrl'], 'list: no next URL on the last page');
			$secondPage = KoyomiFixture::app($root, ['sayamaapps.koyomi.perPage' => 3])->page('events');
			$this->assertSame('https://example.test/events?page=3', Repository::list($secondPage, ['page' => '2'])['pagination']['nextUrl'], 'list: next URL of page 2 of three');
			// Page::kirby() is App::instance(), the most recently created App: re-create the main one before using its pages.
			$kirby = KoyomiFixture::app($root);

			$past = Repository::list($calendar, ['view' => 'past']);
			$this->assertSame(['past', ['Past Workshop']], [$past['view'], koyomi_titles($past['events'])], 'list: past view');
			$this->assertSame(['Workshop' => 1], $past['facets']['categories'], 'list: past facets cover the past events');
			$this->assertSame(false, $past['filtered'], 'list: the view alone does not filter');

			// 6. Models.
			$page = $kirby->page('events/retreat');
			$this->assertTrue($page instanceof EventPage && $page->koyomi() === $page->koyomi() && $page->koyomi() instanceof Event, 'EventPage::koyomi() is memoized');
			$this->assertTrue($kirby->page('events/past-workshop')->isPast() && !$page->isPast(), 'isPast()');
			$this->assertTrue($kirby->page('events/ongoing-retreat')->isOngoing() && !$page->isOngoing(), 'isOngoing()');
			$this->assertSame('https://example.test/events/retreat.ics', $page->icsUrl(), 'icsUrl()');
			$this->assertTrue(str_starts_with($page->googleCalendarUrl(), 'https://calendar.google.com/calendar/render?action=TEMPLATE'), 'googleCalendarUrl() with the option on');
			$this->assertSame(
				'',
				KoyomiFixture::app($root, ['sayamaapps.koyomi.google' => false])->page('events/retreat')->googleCalendarUrl(),
				'googleCalendarUrl() is empty when switched off'
			);
			// Page::kirby() is App::instance(), the most recently created App: re-create the main one before using its pages.
			$kirby = KoyomiFixture::app($root);
			$data = $page->koyomiData();
			$this->assertTrue(
				isset($data['labels'], $data['isPast'], $data['isOngoing'], $data['googleCalendarUrl']) && $data['isPast'] === false && $data['title'] === 'Silent Retreat',
				'koyomiData(): event data plus labels, isPast, isOngoing, googleCalendarUrl'
			);
			$this->assertSame('events', $page->koyomiCalendar()?->id(), 'koyomiCalendar() is the parent calendar');

			$calendarPage = $kirby->page('events');
			$this->assertTrue($calendarPage instanceof CalendarPage, 'the calendar page uses the model');
			$this->assertSame(
				['https://example.test/events.ics', 'webcal://example.test/events.ics', 'https://example.test/events.rss', 'https://example.test/events.json'],
				[$calendarPage->icsUrl(), $calendarPage->webcalUrl(), $calendarPage->rssUrl(), $calendarPage->jsonUrl()],
				'calendar URLs'
			);
			$this->assertSame($upcomingTitles, koyomi_titles($calendarPage->koyomiUpcoming()), 'koyomiUpcoming()');
			$this->assertSame(['Past Workshop'], koyomi_titles($calendarPage->koyomiPast()), 'koyomiPast()');
			$this->assertSame(7, count($calendarPage->koyomiList()['events']), 'koyomiList() without a query reads the request (empty here)');
			$this->assertSame(2, count($calendarPage->koyomiList(['category' => 'retreat'])['events']), 'koyomiList() with an explicit query');

			$venuePage = $kirby->page('venues/beispielhof');
			$this->assertTrue($venuePage instanceof VenuePage && $venuePage->koyomiVenue() === $venuePage->koyomiVenue(), 'VenuePage::koyomiVenue() is memoized');
			$this->assertSame(
				['Silent Retreat', 'Breathing Workshop', 'Mantra Course 1', 'Mantra Course 2'],
				koyomi_titles($venuePage->upcomingEvents()),
				'VenuePage::upcomingEvents()'
			);
			$organizerPage = $kirby->page('organizers/anna');
			$this->assertTrue($organizerPage instanceof OrganizerPage && $organizerPage->koyomiOrganizer()->email === '', 'OrganizerPage: hidden email');
			$this->assertSame(['Silent Retreat', 'Evening Concert'], koyomi_titles($organizerPage->upcomingEvents()), 'OrganizerPage::upcomingEvents()');
			$this->assertTrue($kirby->page('organizers/sayama')->koyomiOrganizer()->email === KoyomiFixture::PUBLIC_EMAIL, 'OrganizerPage: public email');
			$this->assertSame('events', $kirby->site()->koyomiCalendar()?->id(), 'site()->koyomiCalendar()');

			// 7. Output gating: status codes. Kirby keeps the response of an App, so every render gets its own App.
			$status = fn (array $options, string $path): int => KoyomiFixture::app($root, $options)->render($path)?->code() ?? 404;
			foreach (['events.ics', 'events.rss', 'events.json', 'events/retreat.ics', 'events/unlisted-event.ics'] as $path) {
				$this->assertSame(404, $status(['sayamaapps.koyomi.active' => false], $path), "inactive: $path is 404");
			}
			$this->assertSame(200, $status([], 'events.ics'), 'feed on by default');
			$this->assertSame(200, $status([], 'events/retreat.ics'), 'event ics on by default');
			$this->assertSame(200, $status([], 'events.rss'), 'rss on by default');
			$this->assertSame(404, $status([], 'events.json'), 'json off by default');
			$this->assertSame(200, $status(['sayamaapps.koyomi.json' => true], 'events.json'), 'json switched on');
			$this->assertSame(404, $status(['sayamaapps.koyomi.feed' => false], 'events.ics'), 'feed switch off');
			$this->assertSame(404, $status(['sayamaapps.koyomi.rss' => false], 'events.rss'), 'rss switch off');
			$this->assertSame(
				200,
				$status(['sayamaapps.koyomi.feed' => false, 'sayamaapps.koyomi.rss' => false], 'events/retreat.ics'),
				'event ics independent of the feed and rss switches'
			);
			$this->assertSame(404, $status(['sayamaapps.koyomi.ics' => false], 'events/retreat.ics'), 'event ics switch off');
			$this->assertSame(404, $status([], 'events/draft-event.ics'), 'draft event .ics is 404');
			$this->assertTrue(KoyomiFixture::app($root)->page('events/draft-event')?->isDraft() === true, 'the draft is really a draft');
			$this->assertSame(200, $status([], 'events/unlisted-event.ics'), 'unlisted event .ics works on its own URL');
			$this->assertSame(404, $status([], 'venues/beispielhof.ics'), 'venues have no .ics representation');

			// 8. Output headers and expiry (a fresh App per render: the expiry only ever lowers).
			$eventApp = KoyomiFixture::app($root);
			$event = $eventApp->render('events/retreat.ics');
			$this->assertSame('attachment; filename="retreat.ics"', $event->headers()['Content-Disposition'] ?? null, 'event .ics: download header');
			$this->assertTrue(str_starts_with((string)$event->type(), 'text/calendar') && $event->charset() === 'UTF-8', 'event .ics: text/calendar, UTF-8');
			$this->assertSame(koyomi_berlin('2027-03-12 18:00'), $eventApp->response()->expires(), 'event .ics: expires at the event start');

			$feedApp = KoyomiFixture::app($root);
			$feed = $feedApp->render('events.ics');
			$this->assertSame('inline; filename="events.ics"', $feed->headers()['Content-Disposition'] ?? null, 'feed: inline header');
			$this->assertTrue(str_starts_with((string)$feed->type(), 'text/calendar'), 'feed: text/calendar');
			$this->assertSame(koyomi_berlin('2027-03-11 19:00'), $feedApp->response()->expires(), 'feed: expires at the next start (Zoom session)');

			$rssApp = KoyomiFixture::app($root);
			$rss = $rssApp->render('events.rss');
			$this->assertTrue(str_starts_with((string)$rss->type(), 'application/rss+xml'), 'rss: application/rss+xml');
			$this->assertSame(koyomi_berlin('2027-03-11 19:00'), $rssApp->response()->expires(), 'rss: expires at the next start');

			$json = KoyomiFixture::app($root, ['sayamaapps.koyomi.json' => true])->render('events.json');
			$this->assertTrue(str_starts_with((string)$json->type(), 'application/json'), 'json: application/json');
		};


		$partTwo = function () use ($extra, $logFile) {
			// Invalid events are skipped with one log line; the rest of the list is intact.
			$app = KoyomiFixture::app($extra);
			$calendar = $app->page('events');
			$events = [];
			$log = koyomi_logged($logFile, function () use (&$events, $calendar) {
				$events = Repository::events($calendar);
			});
			$this->assertSame(10, count($events), 'events: eight fixture events plus two extra; the broken one is skipped');
			$this->assertSame(1, substr_count($log, 'Koyomi: skipped events/broken: '), 'events: one log line for the broken event');
			$this->assertTrue(!in_array('Broken Event', koyomi_titles($events), true), 'events: the broken event is not in the list');

			// The page model: null, memoized, logged once.
			$broken = $app->page('events/broken');
			$this->assertTrue($broken instanceof EventPage, 'the broken event uses the event model');
			$first = null;
			$logBroken = koyomi_logged($logFile, function () use ($broken, &$first) {
				$first = $broken->koyomi();
				$broken->koyomi();
			});
			$this->assertSame([null, 1], [$first, substr_count($logBroken, 'Koyomi: skipped events/broken: ')], 'koyomi() is null for an invalid date and logs once');
			$this->assertTrue(!$broken->isPast() && !$broken->isOngoing(), 'isPast() and isOngoing() are false without an Event');
			$this->assertSame([], $broken->koyomiData(), 'koyomiData() is [] for an invalid event');
			$this->assertSame('', $broken->googleCalendarUrl(), 'googleCalendarUrl() is empty for an invalid event');
			$brokenStatus = null;
			$brokenOutput = koyomi_logged($logFile, function () use ($extra, &$brokenStatus) {
				$brokenStatus = KoyomiFixture::app($extra)->render('events/broken.ics')?->code() ?? 404;
			});
			$this->assertSame(404, $brokenStatus, 'an invalid event has no .ics: 404');
			$this->assertSame(1, substr_count($brokenOutput, 'Koyomi: skipped events/broken: '), 'the .ics route logs the skip once');

			// Description from text blocks: entities decoded, paragraphs separated, markup gone.
			$this->assertSame(
				'First & second paragraph. Second block with bold text.',
				Mapper::raw($app->page('events/fallback-text'))['description'],
				'description from text blocks'
			);
			$long = Mapper::raw($app->page('events/long-text'))['description'];
			$this->assertTrue(
				mb_strlen($long) <= Mapper::EXCERPT_LENGTH + 2 && str_ends_with($long, ' …') && !str_contains($long, '<'),
				'description cut to about 300 characters, no markup'
			);

			// The cache: the same App returns the same Event objects; reset() makes new ones; a fresh App scans the disk.
			// (Kirby keeps its own page inventory per App, so the same App cannot see a new folder even after reset().)
			koyomi_logged($logFile, function () use ($extra) {
				$cacheCalendar = KoyomiFixture::app($extra)->page('events');
				$cached = Repository::events($cacheCalendar);
				$before = count($cached);
				KoyomiFixture::write($extra . '/content/1_events/20270405_cache-probe/koyomi-event.txt', [
					'Title' => 'Cache Probe', 'Uuid' => 'evt-cache-probe', 'StartDate' => '2027-04-05', 'StartTime' => '10:00:00',
					'Timezone' => 'Europe/Berlin', 'InPerson' => 'true', 'Online' => 'false',
				]);
				$this->assertTrue(Repository::events($cacheCalendar)[0] === $cached[0], 'cache: the same App returns the same objects');
				(new ReflectionProperty(Repository::class, 'cache'))->setValue(null, null);
				$rescanned = Repository::events($cacheCalendar);
				$this->assertTrue($rescanned[0] !== $cached[0] && count($rescanned) === $before, 'cache: reset() scans again (new objects)');
				$this->assertSame($before + 1, count(Repository::events(KoyomiFixture::app($extra)->page('events'))), 'cache: a fresh App sees the new event');
			});
		};

		try {

			$partOne();
			$partTwo();

		} finally {

			KoyomiFixture::remove($root);
			KoyomiFixture::remove($extra);

		}
	}
}

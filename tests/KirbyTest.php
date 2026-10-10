<?php
/**
 * The Kirby glue on a real site (tests/lib/Fixture.php): Mapper, Repository
 * (cache, lists, venues, organizers), the page models, the collections and
 * the representation gating and headers of Output. Contract: SPEC.md §8.9,
 * §8.10, §8.11 and §9 (KirbyTest row).
 *
 * The bodies of .ics/.rss/.json are checked by their own tests and by
 * AcceptanceTest; this file checks status codes, headers, expiry and data.
 *   php tests/KirbyTest.php
 */
require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/lib/expect.php';
require __DIR__ . '/lib/Fixture.php';

use Kirby\Cms\App;
use Kirby\Uuid\Uuids;
use Koyomi\Event;
use Koyomi\Mapper;
use Koyomi\Models\CalendarPage;
use Koyomi\Models\EventPage;
use Koyomi\Models\OrganizerPage;
use Koyomi\Models\VenuePage;
use Koyomi\Normalize;
use Koyomi\Organizer;
use Koyomi\Repository;
use Koyomi\Venue;

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
    return is_file($file) ? (string) file_get_contents($file) : '';
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

$root = KoyomiFixture::create();
$extra = KoyomiFixture::create();
$upcomingTitles = ['Spring Retreat', 'Evening Meditation on Zoom', 'Silent Retreat', 'Concert in Prague', 'Breathing Workshop', 'Mantra Course 1', 'Mantra Course 2'];

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
    expect('evt-retreat', $retreat['id'], 'raw: id from the page UUID');
    expect('mixed', $retreat['attendance'], 'raw: attendance from the two toggles (in person and online)');
    holds($retreat['venue'] instanceof Venue && $retreat['venue']->id === 'venue-sonnenhof', 'raw: venue resolved from page://venue-sonnenhof');
    expect('Sunrise over the mountains', $retreat['imageAlt'], 'raw: cover alt text');
    holds(str_starts_with($retreat['image'], 'https://example.test/') && str_ends_with($retreat['image'], '/cover.jpg'), 'raw: cover is an absolute URL');
    holds($retreat['organizers'][0] instanceof Organizer && $retreat['organizers'][0]->id === 'org-anna', 'raw: organizer resolved');
    expect('', $retreat['organizers'][0]->email, 'raw: hidden organizer email stays out');
    expect('Three days of silence in the mountains.', $retreat['description'], 'raw: the excerpt wins over text blocks');

    $zoom = $raw('events/zoom-session');
    expect('online', $zoom['attendance'], 'raw: inPerson off and online on is online');
    expect(null, $zoom['venue'], 'raw: no venue');
    expect(KoyomiFixture::PUBLIC_EMAIL, $zoom['organizers'][0]->email, 'raw: showEmail on keeps the public email');

    $concert = $raw('events/concert');
    expect('offline', $concert['attendance'], 'raw: in person only is offline');
    expect(['rescheduled', '2027-02-20 20:00:00'], [$concert['status'], $concert['previousStart']], 'raw: status and previousStart as stored');

    $ongoing = $raw('events/ongoing-retreat');
    expect(['offline', 'movedOnline'], [$ongoing['attendance'], $ongoing['status']], 'raw: stored attendance stays, status moves it online');

    $cancelled = $raw('events/workshop-cancelled');
    expect(true, Normalize::bool($cancelled['allDay']), 'raw: all-day toggle');
    expect('', $cancelled['description'], 'raw: no excerpt and no text gives an empty description');

    $series = $raw('events/series-part-1');
    expect(['evt-series-2', 'Mantra Course 2', '2'], [$series['series'][0]['id'], $series['series'][0]['title'], $series['series'][0]['part']], 'raw: series sibling resolved with its part');
    expect('1', $series['seriesPart'], 'raw: own seriesPart');

    $event = Mapper::event($kirby->page('events/retreat'), ['timezone' => 'Europe/Berlin', 'currency' => 'EUR', 'defaultDuration' => 120]);
    expect(['Silent Retreat', 'mixed', ['de', 'cs']], [$event->title, $event->attendance, $event->languages], 'Mapper::event builds the Event');

    // Privacy: no onlineUrl and no hidden email anywhere in the mapped data.
    $mapped = '';
    foreach (['events/retreat', 'events/zoom-session', 'events/concert', 'events/workshop-cancelled', 'events/past-workshop',
        'events/ongoing-retreat', 'events/series-part-1', 'events/series-part-2'] as $path) {
        $mapped .= json_encode(Mapper::raw($kirby->page($path)));
    }
    $mapped .= json_encode(Mapper::organizer($kirby->page('organizers/anna')));
    foreach (KoyomiFixture::SECRET_FRAGMENTS as $fragment) {
        holds(!str_contains($mapped, $fragment), 'privacy: no online meeting secret in mapped data: ' . $fragment);
    }
    holds(!str_contains($mapped, KoyomiFixture::HIDDEN_EMAIL), 'privacy: hidden organizer email absent');
    holds(!str_contains(json_encode(Mapper::event($kirby->page('events/retreat'))->toArray()), 'onlineUrl'), 'privacy: Event::toArray() has no onlineUrl');

    // 2. Mapper::id(), with and without UUIDs.
    expect('evt-retreat', Mapper::id($kirby->page('events/retreat')), 'id: UUID id without page://');
    Uuids::$enabled = false;
    try {
        expect('events-retreat', Mapper::id($kirby->page('events/retreat')), 'id: page id with "/" replaced when UUIDs are off');
    } finally {
        Uuids::$enabled = null;
    }

    // 3. Venues and organizers.
    $sonnenhof = Mapper::venue($kirby->page('venues/sonnenhof'));
    expect(['venue-sonnenhof', 'Seminarhaus Beispielhof', 'DE'], [$sonnenhof->id, $sonnenhof->name, $sonnenhof->country], 'venue: id, name, country');
    holds($sonnenhof->hasCoordinates() && abs($sonnenhof->lat - 47.856) < 1e-9, 'venue: coordinates');
    expect('https://www.openstreetmap.org/?mlat=47.856&mlon=12.128#map=17/47.856/12.128', $sonnenhof->mapLink(), 'venue: OpenStreetMap link from the coordinates');
    holds($sonnenhof->showMap && str_ends_with($sonnenhof->toArray()['mapImage'], '/map.jpg'), 'venue: map image shown with showMap');
    expect('Map of the Sonnenhof area', $sonnenhof->mapImageAlt, 'venue: map image alt');
    $kulturhaus = Mapper::venue($kirby->page('venues/kulturhaus'));
    holds(!$kulturhaus->hasCoordinates() && !$kulturhaus->showMap && $kulturhaus->mapImage === '', 'venue without coordinates, map or showMap');
    expect('https://www.openstreetmap.org/search?query=' . rawurlencode('Národní 1, 110 00 Praha, CZ'), $kulturhaus->mapLink(), 'venue: address search link without coordinates');
    $customMap = KoyomiFixture::app($root, ['koyomi.mapUrl' => fn (Venue $v): string => 'https://maps.example/' . $v->id]);
    expect('https://maps.example/venue-sonnenhof', Mapper::venue($customMap->page('venues/sonnenhof'))->mapLink(), 'venue: koyomi.mapUrl closure');
    // Page::kirby() is App::instance(), the most recently created App: re-create the main one before using its pages.
    $kirby = KoyomiFixture::app($root);

    $anna = Mapper::organizer($kirby->page('organizers/anna'));
    expect(['', 'person', 'https://anna.example'], [$anna->email, $anna->kind, $anna->website], 'organizer: hidden email stays empty');
    $sayama = Mapper::organizer($kirby->page('organizers/sayama'));
    expect([KoyomiFixture::PUBLIC_EMAIL, 'organization'], [$sayama->email, $sayama->kind], 'organizer: public email kept');

    // 4. Repository: containers, collections, listed events only.
    expect('events', Repository::calendar($kirby)?->id(), 'calendar found by template');
    expect('events', KoyomiFixture::app($root, ['koyomi.calendar' => 'venues'])->site()->koyomiCalendar()?->id(), 'calendar option naming another template falls back');
    expect('events', KoyomiFixture::app($root, ['koyomi.calendar' => 'nope'])->site()->koyomiCalendar()?->id(), 'calendar option naming a missing page falls back');
    // Page::kirby() is App::instance(), the most recently created App: re-create the main one before using its pages.
    $kirby = KoyomiFixture::app($root);
    expect('venues', Repository::venuesPage($kirby)?->id(), 'venues container found');
    expect('venues', Repository::venuesPage(KoyomiFixture::app($root, ['koyomi.venues' => 'events']))?->id(), 'venues option naming another template falls back');
    // Page::kirby() is App::instance(), the most recently created App: re-create the main one before using its pages.
    $kirby = KoyomiFixture::app($root);
    expect('organizers', Repository::organizersPage($kirby)?->id(), 'organizers container found');
    $venueSlugs = array_map(fn ($p) => $p->slug(), iterator_to_array(Repository::venuePages($kirby)));
    sort($venueSlugs);
    expect(['kulturhaus', 'sonnenhof'], $venueSlugs, 'venue pages: the children of the container');
    expect(2, count(Repository::organizerPages($kirby)), 'organizer pages: two');
    expect(2, $kirby->collection('koyomi/venues')->count(), 'collection koyomi/venues');
    expect(2, $kirby->collection('koyomi/organizers')->count(), 'collection koyomi/organizers');
    expect(8, $kirby->collection('koyomi/events')->count(), 'collection koyomi/events: listed events only');
    $eventIds = array_map(fn ($p) => $p->id(), iterator_to_array(Repository::eventPages($kirby->page('events'))));
    sort($eventIds);
    expect(['events/concert', 'events/ongoing-retreat', 'events/past-workshop', 'events/retreat', 'events/series-part-1', 'events/series-part-2', 'events/workshop-cancelled', 'events/zoom-session'],
        $eventIds, 'eventPages: unlisted and draft events are left out');
    expect(0, count(Repository::eventPages(null)), 'eventPages without a calendar is empty');

    $calendar = $kirby->page('events');
    expect($upcomingTitles, koyomi_titles(Repository::upcoming($calendar)), 'upcoming: ongoing first, by start, cancelled listed');
    expect(['Past Workshop'], koyomi_titles(Repository::past($calendar)), 'past');
    expect(['Silent Retreat', 'Concert in Prague'], koyomi_titles(Repository::upcoming($calendar, ['language' => 'cs'])), 'upcoming with a filter');
    expect(array_values(array_diff($upcomingTitles, ['Breathing Workshop'])), koyomi_titles(Repository::upcoming(KoyomiFixture::app($root, ['koyomi.hideCancelled' => true])->page('events'))), 'hideCancelled removes the cancelled event from upcoming');
    // Page::kirby() is App::instance(), the most recently created App: re-create the main one before using its pages.
    $kirby = KoyomiFixture::app($root);
    expect(['Silent Retreat', 'Breathing Workshop', 'Mantra Course 1', 'Mantra Course 2'], koyomi_titles(Repository::forVenue($kirby, $kirby->page('venues/sonnenhof'))), 'forVenue: upcoming in person at the venue; the moved-online retreat is left out');
    expect(['Concert in Prague'], koyomi_titles(Repository::forVenue($kirby, $kirby->page('venues/kulturhaus'))), 'forVenue: the other venue');
    expect(['Silent Retreat', 'Concert in Prague'], koyomi_titles(Repository::forOrganizer($kirby, $kirby->page('organizers/anna'))), 'forOrganizer: Anna');
    expect(['Evening Meditation on Zoom'], koyomi_titles(Repository::forOrganizer($kirby, $kirby->page('organizers/sayama'))), 'forOrganizer: Sayama');

    // 5. list(): shape, facets, filters, URLs, paging.
    $list = Repository::list($calendar, []);
    expect(['view', 'filters', 'events', 'pagination', 'facets', 'title', 'baseUrl', 'upcomingUrl', 'pastUrl', 'filtered'], array_keys($list), 'list: keys of the SPEC §8.9 shape');
    expect(['page', 'pages', 'total', 'perPage', 'hasPrev', 'hasNext', 'prevUrl', 'nextUrl'], array_keys($list['pagination']), 'list: pagination keys');
    expect($upcomingTitles, koyomi_titles($list['events']), 'list: default view is upcoming, one page');
    expect([1, 1, 7, 12, null, null], [$list['pagination']['page'], $list['pagination']['pages'], $list['pagination']['total'], $list['pagination']['perPage'], $list['pagination']['prevUrl'], $list['pagination']['nextUrl']], 'list: one page of seven');
    expect(['https://example.test/events', 'https://example.test/events?view=past', 'Events', false], [$list['baseUrl'], $list['pastUrl'], $list['title'], $list['filtered']], 'list: URLs, title, filtered');
    expect('https://example.test/events', $list['upcomingUrl'], 'list: upcomingUrl');
    expect(['Concert' => 1, 'Meditation' => 2, 'Retreat' => 2, 'Workshop' => 3], $list['facets']['categories'], 'list: category facets over the unfiltered view');
    expect(['cs' => 2, 'de' => 6, 'en' => 1], $list['facets']['languages'], 'list: language facets');
    expect(['offline' => 5, 'online' => 3, 'mixed' => 1], $list['facets']['attendance'], 'list: attendance facets');

    $filtered = Repository::list($calendar, ['category' => 'retreat']);
    expect(true, $filtered['filtered'], 'list: a category filter marks the list as filtered');
    expect(['Spring Retreat', 'Silent Retreat'], koyomi_titles($filtered['events']), 'list: category filter, case-insensitive');
    expect(['Concert' => 1, 'Meditation' => 2, 'Retreat' => 2, 'Workshop' => 3], $filtered['facets']['categories'], 'list: facets ignore the filter');

    $beyond = Repository::list($calendar, ['category' => 'retreat', 'page' => '9']);
    expect([1, 1], [$beyond['pagination']['page'], $beyond['pagination']['pages']], 'list: a page beyond the end shows the last page');

    $perPageOne = KoyomiFixture::app($root, ['koyomi.perPage' => 1]);
    $pagedOne = Repository::list($perPageOne->page('events'), ['category' => 'retreat', 'page' => '2']);
    expect(['Silent Retreat'], koyomi_titles($pagedOne['events']), 'list: page 2 with one event per page');
    expect([2, 2, 2], [$pagedOne['pagination']['page'], $pagedOne['pagination']['pages'], $pagedOne['pagination']['total']], 'list: pagination numbers of the filtered list');
    expect('https://example.test/events?category=retreat', $pagedOne['pagination']['prevUrl'], 'list: previous URL keeps the filter and drops page 1');
    expect(null, $pagedOne['pagination']['nextUrl'], 'list: no next URL on the last page');
    $secondPage = KoyomiFixture::app($root, ['koyomi.perPage' => 3])->page('events');
    expect('https://example.test/events?page=3', Repository::list($secondPage, ['page' => '2'])['pagination']['nextUrl'], 'list: next URL of page 2 of three');
    // Page::kirby() is App::instance(), the most recently created App: re-create the main one before using its pages.
    $kirby = KoyomiFixture::app($root);

    $past = Repository::list($calendar, ['view' => 'past']);
    expect(['past', ['Past Workshop']], [$past['view'], koyomi_titles($past['events'])], 'list: past view');
    expect(['Workshop' => 1], $past['facets']['categories'], 'list: past facets cover the past events');
    expect(false, $past['filtered'], 'list: the view alone does not filter');

    // 6. Models.
    $page = $kirby->page('events/retreat');
    holds($page instanceof EventPage && $page->koyomi() === $page->koyomi() && $page->koyomi() instanceof Event, 'EventPage::koyomi() is memoized');
    holds($kirby->page('events/past-workshop')->isPast() && !$page->isPast(), 'isPast()');
    holds($kirby->page('events/ongoing-retreat')->isOngoing() && !$page->isOngoing(), 'isOngoing()');
    expect('https://example.test/events/retreat.ics', $page->icsUrl(), 'icsUrl()');
    holds(str_starts_with($page->googleCalendarUrl(), 'https://calendar.google.com/calendar/render?action=TEMPLATE'), 'googleCalendarUrl() with the option on');
    expect('', KoyomiFixture::app($root, ['koyomi.google' => false])->page('events/retreat')->googleCalendarUrl(), 'googleCalendarUrl() is empty when switched off');
    // Page::kirby() is App::instance(), the most recently created App: re-create the main one before using its pages.
    $kirby = KoyomiFixture::app($root);
    $data = $page->koyomiData();
    holds(isset($data['labels'], $data['isPast'], $data['isOngoing'], $data['googleCalendarUrl']) && $data['isPast'] === false && $data['title'] === 'Silent Retreat', 'koyomiData(): event data plus labels, isPast, isOngoing, googleCalendarUrl');
    expect('events', $page->koyomiCalendar()?->id(), 'koyomiCalendar() is the parent calendar');

    $calendarPage = $kirby->page('events');
    holds($calendarPage instanceof CalendarPage, 'the calendar page uses the model');
    expect(['https://example.test/events.ics', 'webcal://example.test/events.ics', 'https://example.test/events.rss', 'https://example.test/events.json'],
        [$calendarPage->icsUrl(), $calendarPage->webcalUrl(), $calendarPage->rssUrl(), $calendarPage->jsonUrl()], 'calendar URLs');
    expect($upcomingTitles, koyomi_titles($calendarPage->koyomiUpcoming()), 'koyomiUpcoming()');
    expect(['Past Workshop'], koyomi_titles($calendarPage->koyomiPast()), 'koyomiPast()');
    expect(7, count($calendarPage->koyomiList()['events']), 'koyomiList() without a query reads the request (empty here)');
    expect(2, count($calendarPage->koyomiList(['category' => 'retreat'])['events']), 'koyomiList() with an explicit query');

    $venuePage = $kirby->page('venues/sonnenhof');
    holds($venuePage instanceof VenuePage && $venuePage->koyomiVenue() === $venuePage->koyomiVenue(), 'VenuePage::koyomiVenue() is memoized');
    expect(['Silent Retreat', 'Breathing Workshop', 'Mantra Course 1', 'Mantra Course 2'], koyomi_titles($venuePage->upcomingEvents()), 'VenuePage::upcomingEvents()');
    $organizerPage = $kirby->page('organizers/anna');
    holds($organizerPage instanceof OrganizerPage && $organizerPage->koyomiOrganizer()->email === '', 'OrganizerPage: hidden email');
    expect(['Silent Retreat', 'Concert in Prague'], koyomi_titles($organizerPage->upcomingEvents()), 'OrganizerPage::upcomingEvents()');
    holds($kirby->page('organizers/sayama')->koyomiOrganizer()->email === KoyomiFixture::PUBLIC_EMAIL, 'OrganizerPage: public email');
    expect('events', $kirby->site()->koyomiCalendar()?->id(), 'site()->koyomiCalendar()');

    // 7. Output gating: status codes. Kirby keeps the response of an App, so every render gets its own App.
    $status = fn (array $options, string $path): int => KoyomiFixture::app($root, $options)->render($path)?->code() ?? 404;
    foreach (['events.ics', 'events.rss', 'events.json', 'events/retreat.ics', 'events/unlisted-event.ics'] as $path) {
        expect(404, $status(['koyomi.active' => false], $path), "inactive: $path is 404");
    }
    expect(200, $status([], 'events.ics'), 'feed on by default');
    expect(200, $status([], 'events/retreat.ics'), 'event ics on by default');
    expect(200, $status([], 'events.rss'), 'rss on by default');
    expect(404, $status([], 'events.json'), 'json off by default');
    expect(200, $status(['koyomi.json' => true], 'events.json'), 'json switched on');
    expect(404, $status(['koyomi.feed' => false], 'events.ics'), 'feed switch off');
    expect(404, $status(['koyomi.rss' => false], 'events.rss'), 'rss switch off');
    expect(200, $status(['koyomi.feed' => false, 'koyomi.rss' => false], 'events/retreat.ics'), 'event ics independent of the feed and rss switches');
    expect(404, $status(['koyomi.ics' => false], 'events/retreat.ics'), 'event ics switch off');
    expect(404, $status([], 'events/draft-event.ics'), 'draft event .ics is 404');
    holds(KoyomiFixture::app($root)->page('events/draft-event')?->isDraft() === true, 'the draft is really a draft');
    expect(200, $status([], 'events/unlisted-event.ics'), 'unlisted event .ics works on its own URL');
    expect(404, $status([], 'venues/sonnenhof.ics'), 'venues have no .ics representation');

    // 8. Output headers and expiry (a fresh App per render: the expiry only ever lowers).
    $eventApp = KoyomiFixture::app($root);
    $event = $eventApp->render('events/retreat.ics');
    expect('attachment; filename="retreat.ics"', $event->headers()['Content-Disposition'] ?? null, 'event .ics: download header');
    holds(str_starts_with((string) $event->type(), 'text/calendar') && $event->charset() === 'UTF-8', 'event .ics: text/calendar, UTF-8');
    expect(koyomi_berlin('2027-03-12 18:00'), $eventApp->response()->expires(), 'event .ics: expires at the event start');

    $feedApp = KoyomiFixture::app($root);
    $feed = $feedApp->render('events.ics');
    expect('inline; filename="events.ics"', $feed->headers()['Content-Disposition'] ?? null, 'feed: inline header');
    holds(str_starts_with((string) $feed->type(), 'text/calendar'), 'feed: text/calendar');
    expect(koyomi_berlin('2027-03-11 19:00'), $feedApp->response()->expires(), 'feed: expires at the next start (Zoom session)');

    $rssApp = KoyomiFixture::app($root);
    $rss = $rssApp->render('events.rss');
    holds(str_starts_with((string) $rss->type(), 'application/rss+xml'), 'rss: application/rss+xml');
    expect(koyomi_berlin('2027-03-11 19:00'), $rssApp->response()->expires(), 'rss: expires at the next start');

    $json = KoyomiFixture::app($root, ['koyomi.json' => true])->render('events.json');
    holds(str_starts_with((string) $json->type(), 'application/json'), 'json: application/json');
};

$partTwo = function () use ($extra, $logFile) {
    // Invalid events are skipped with one log line; the rest of the list is intact.
    $app = KoyomiFixture::app($extra);
    $calendar = $app->page('events');
    $events = [];
    $log = koyomi_logged($logFile, function () use (&$events, $calendar) {
        $events = Repository::events($calendar);
    });
    expect(10, count($events), 'events: eight fixture events plus two extra; the broken one is skipped');
    expect(1, substr_count($log, 'Koyomi: skipped events/broken: '), 'events: one log line for the broken event');
    holds(!in_array('Broken Event', koyomi_titles($events), true), 'events: the broken event is not in the list');

    // The page model: null, memoized, logged once.
    $broken = $app->page('events/broken');
    holds($broken instanceof EventPage, 'the broken event uses the event model');
    $first = null;
    $logBroken = koyomi_logged($logFile, function () use ($broken, &$first) {
        $first = $broken->koyomi();
        $broken->koyomi();
    });
    expect([null, 1], [$first, substr_count($logBroken, 'Koyomi: skipped events/broken: ')], 'koyomi() is null for an invalid date and logs once');
    holds(!$broken->isPast() && !$broken->isOngoing(), 'isPast() and isOngoing() are false without an Event');
    expect([], $broken->koyomiData(), 'koyomiData() is [] for an invalid event');
    expect('', $broken->googleCalendarUrl(), 'googleCalendarUrl() is empty for an invalid event');
    $brokenStatus = null;
    $brokenOutput = koyomi_logged($logFile, function () use ($extra, &$brokenStatus) {
        $brokenStatus = KoyomiFixture::app($extra)->render('events/broken.ics')?->code() ?? 404;
    });
    expect(404, $brokenStatus, 'an invalid event has no .ics: 404');
    expect(1, substr_count($brokenOutput, 'Koyomi: skipped events/broken: '), 'the .ics route logs the skip once');

    // Description from text blocks: entities decoded, paragraphs separated, markup gone.
    expect('First & second paragraph. Second block with bold text.', Mapper::raw($app->page('events/fallback-text'))['description'], 'description from text blocks');
    $long = Mapper::raw($app->page('events/long-text'))['description'];
    holds(mb_strlen($long) <= Mapper::EXCERPT_LENGTH + 2 && str_ends_with($long, ' …') && !str_contains($long, '<'), 'description cut to about 300 characters, no markup');

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
        holds(Repository::events($cacheCalendar)[0] === $cached[0], 'cache: the same App returns the same objects');
        Repository::reset();
        $rescanned = Repository::events($cacheCalendar);
        holds($rescanned[0] !== $cached[0] && count($rescanned) === $before, 'cache: reset() scans again (new objects)');
        expect($before + 1, count(Repository::events(KoyomiFixture::app($extra)->page('events'))), 'cache: a fresh App sees the new event');
    });
};

// koyomi_test() exits the script, so both parts run inside one check and share one cleanup.
koyomi_test('Koyomi Kirby', function () use ($partOne, $partTwo) {
    $partOne();
    $partTwo();
}, function () use ($root, $extra) {
    KoyomiFixture::remove($root);
    KoyomiFixture::remove($extra);
});

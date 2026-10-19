<?php
/**
 * ACCEPTANCE (owned by the architect, not by a build task): the assembled
 * plugin on the fixture site — off switch, output switches, headers, lists
 * and filters, cache expiry, past pages, JSON-LD only on the single page,
 * duplicate reset, German, site overrides, blueprints.
 *
 * Green only when all tasks are done.
 *   php tests/AcceptanceTest.php
 */
$base = dirname(__DIR__);
if (!is_file($base . '/vendor/autoload.php')) { fwrite(STDERR, "Composer dependencies missing; run composer install first\n"); exit(1); }
require $base . '/vendor/autoload.php';
require __DIR__ . '/lib/expect.php';
require __DIR__ . '/lib/Fixture.php';

use Kirby\Cms\Blueprint;

$root = KoyomiFixture::create();

koyomi_test('Koyomi acceptance', function () use ($root) {
    $code = fn ($kirby, string $path) => $kirby->render($path)?->code() ?? 404;
    $titles = fn (array $events) => array_map(fn ($e) => $e->title, $events);

    // 1. Registered; strings as Kirby translations.
    $kirby = KoyomiFixture::app($root, ['koyomi.active' => false]);
    holds($kirby->plugin('sayamaapps/koyomi') !== null, 'plugin registered');
    expect('Abgesagt', $kirby->translation('de')->get('koyomi.status.cancelled'), 'German Kirby translation');
    holds(function_exists('koyomi_option') && function_exists('koyomi_event'), 'helpers defined');

    // 2. Off by default: feeds 404, snippets print nothing.
    foreach (['events.ics', 'events.rss', 'events.json', 'events/retreat.ics'] as $path) {
        expect(404, $code($kirby, $path), "inactive: $path is 404");
    }
    expect('', trim((string) $kirby->snippet('koyomi/list', [], true)), 'inactive: list prints nothing');
    expect('', trim((string) $kirby->snippet('koyomi/event', ['event' => $kirby->page('events/retreat')], true)), 'inactive: event prints nothing');
    expect('', trim((string) $kirby->snippet('koyomi/jsonld', ['page' => $kirby->page('events/retreat')], true)), 'inactive: no JSON-LD');
    $kirby = KoyomiFixture::app($root, ['koyomi.active' => 'yes']);
    expect(404, $code($kirby, 'events.ics'), 'only a real true switches on');

    // 3. Switches.
    $kirby = KoyomiFixture::app($root);
    expect(200, $code($kirby, 'events.ics'), 'feed on by default');
    expect(200, $code($kirby, 'events/retreat.ics'), 'event ics on by default');
    expect(200, $code($kirby, 'events.rss'), 'rss on by default');
    expect(404, $code($kirby, 'events.json'), 'json off by default');
    expect(200, $code(KoyomiFixture::app($root, ['koyomi.json' => true]), 'events.json'), 'json switched on');
    $kirby = KoyomiFixture::app($root, ['koyomi.feed' => false, 'koyomi.rss' => false]);
    expect(404, $code($kirby, 'events.ics'), 'feed off');
    expect(404, $code($kirby, 'events.rss'), 'rss off');
    // A fresh App: Kirby keeps one Responder per App, so the 404 of the
    // lines above would otherwise carry over to this render.
    expect(200, $code(KoyomiFixture::app($root, ['koyomi.feed' => false, 'koyomi.rss' => false]), 'events/retreat.ics'), 'event ics independent of feed');
    expect(404, $code(KoyomiFixture::app($root, ['koyomi.ics' => false]), 'events/retreat.ics'), 'event ics off');
    expect(404, $code(KoyomiFixture::app($root), 'events/draft-event.ics'), 'draft never served');

    // 4. Headers and body basics.
    $kirby = KoyomiFixture::app($root);
    $r = $kirby->render('events.ics');
    holds(str_starts_with((string) $r->type(), 'text/calendar'), 'feed type text/calendar');
    expect('UTF-8', strtoupper((string) $r->charset()), 'feed charset');
    $body = $r->body();
    holds(str_starts_with($body, "BEGIN:VCALENDAR\r\n") && str_ends_with($body, "END:VCALENDAR\r\n"), 'CRLF calendar');
    holds(str_contains($body, "UID:evt-retreat@example.test\r\n"), 'stable UID from the page UUID');
    holds(str_contains($body, "DTSTART;TZID=Europe/Prague:20270320T200000\r\n") && str_contains($body, "TZID:Europe/Prague\r\n"), 'concert in Prague time with VTIMEZONE');
    holds(str_contains($body, "STATUS:CANCELLED\r\n"), 'cancelled event stays in the feed as CANCELLED');
    holds(str_contains($body, 'Past Workshop') === false, 'past workshop (ended > 30 days before now) not in the feed');
    holds(!str_contains($body, 'Unlisted Event') && !str_contains($body, 'Draft Event'), 'feed: listed events only');
    $r = $kirby->render('events/retreat.ics');
    expect('attachment; filename="retreat.ics"', $r->headers()['Content-Disposition'] ?? null, 'event download filename');
    $r = KoyomiFixture::app($root)->render('events.rss');
    holds(str_starts_with((string) $r->type(), 'application/rss+xml'), 'rss type');
    holds(str_contains($r->body(), '<title>Silent Retreat</title>'), 'rss item');
    $r = KoyomiFixture::app($root, ['koyomi.json' => true])->render('events.json');
    $json = json_decode($r->body(), true);
    holds(is_array($json) && isset($json['events'][0]['id']), 'json has events');
    expect('Spring Retreat', $json['events'][0]['title'], 'json: upcoming, ongoing first');

    // 5. Lists: order, past, unlisted/draft never, hideCancelled, filters.
    $kirby = KoyomiFixture::app($root);
    $calendar = $kirby->site()->koyomiCalendar();
    expect('events', $calendar?->id(), 'calendar found');
    $upcoming = ['Spring Retreat', 'Evening Meditation on Zoom', 'Silent Retreat', 'Evening Concert', 'Breathing Workshop', 'Mantra Course 1', 'Mantra Course 2'];
    expect($upcoming, $titles($calendar->koyomiUpcoming()), 'upcoming: ongoing first, by start, cancelled listed');
    expect(['Past Workshop'], $titles($calendar->koyomiPast()), 'past');
    expect(array_values(array_diff($upcoming, ['Breathing Workshop'])), $titles(KoyomiFixture::app($root, ['koyomi.hideCancelled' => true])->page('events')->koyomiUpcoming()), 'hideCancelled');
    $list = fn (array $query) => $titles(KoyomiFixture::app($root)->page('events')->koyomiList($query)['events']);
    expect(['Spring Retreat', 'Evening Meditation on Zoom', 'Silent Retreat'], $list(['attendance' => 'online']), 'online filter: moved online + online + hybrid');
    expect(['Spring Retreat', 'Silent Retreat'], $list(['category' => 'retreat']), 'category filter, case-insensitive');
    expect(['Silent Retreat', 'Evening Concert'], $list(['language' => 'cs']), 'language filter');
    expect(['Silent Retreat'], $list(['featured' => '1']), 'featured filter');
    expect(['Past Workshop'], $list(['view' => 'past']), 'past view');
    $paged = KoyomiFixture::app($root, ['koyomi.perPage' => 3])->page('events')->koyomiList(['page' => '2']);
    expect(['Evening Concert', 'Breathing Workshop', 'Mantra Course 1'], $titles($paged['events']), 'page 2 of 3');
    expect([2, 3, 7], [$paged['pagination']['page'], $paged['pagination']['pages'], $paged['pagination']['total']], 'pagination numbers');
    expect('https://example.test/events?page=3', $paged['pagination']['nextUrl'], 'next page URL');

    // 6. Cache expiry: the next start/end after now (zoom session starts 2027-03-11 19:00 Berlin),
    // and for the timeline with its Today line the local midnight before it.
    $kirby = KoyomiFixture::app($root);
    $kirby->render('events');
    expect((new DateTimeImmutable('2027-03-11 00:00', new DateTimeZone('Europe/Berlin')))->getTimestamp(), $kirby->response()->expires(), 'calendar page (timeline, Today line) expires at local midnight');
    $kirby = KoyomiFixture::app($root, ['koyomi.groupBy' => false]);
    $kirby->render('events');
    expect((new DateTimeImmutable('2027-03-11 19:00', new DateTimeZone('Europe/Berlin')))->getTimestamp(), $kirby->response()->expires(), 'calendar page (flat list) expires when the next event starts');
    $kirby = KoyomiFixture::app($root);
    $kirby->render('events/retreat');
    expect((new DateTimeImmutable('2027-03-12 18:00', new DateTimeZone('Europe/Berlin')))->getTimestamp(), $kirby->response()->expires(), 'event page expires at its start');

    // 7. Past event page: kept, note, link back; isPast() for sitemaps.
    $kirby = KoyomiFixture::app($root);
    $page = $kirby->page('events/past-workshop');
    holds($page->isPast() && !$kirby->page('events/retreat')->isPast(), 'isPast()');
    holds($kirby->page('events/ongoing-retreat')->isOngoing(), 'isOngoing()');
    $html = $kirby->render('events/past-workshop')->body();
    holds(str_contains($html, 'This event has already taken place.') && str_contains($html, 'href="https://example.test/events"'), 'past note with link to upcoming');

    // 8. JSON-LD on the single page only.
    $html = $kirby->render('events/retreat')->body();
    holds(str_contains($html, '<script type="application/ld+json">') && str_contains($html, 'MixedEventAttendanceMode'), 'JSON-LD on the event page');
    holds(!str_contains(KoyomiFixture::app($root)->render('events')->body(), 'application/ld+json'), 'no JSON-LD on the list');
    holds(str_contains($html, '<time datetime="2027-03-12T18:00:00+01:00">'), '<time datetime> with offset');
    holds(str_contains($html, 'Only a few places left') && str_contains($html, '380 €') === false && str_contains($html, '€380'), 'availability as text, English money');
    holds(str_contains($html, 'calendar.google.com/calendar/render?action=TEMPLATE'), 'Google link on by default');
    holds(!str_contains(KoyomiFixture::app($root, ['koyomi.google' => false])->render('events/retreat')->body(), 'calendar.google.com'), 'Google link switchable');
    $html = KoyomiFixture::app($root)->render('events/series-part-1')->body();
    holds(str_contains($html, 'Part 1 of 2') && str_contains($html, 'Mantra Course 2'), 'series navigation');

    // 9. Status as text.
    $html = KoyomiFixture::app($root)->render('events/concert')->body();
    holds(str_contains($html, 'Rescheduled') && str_contains($html, 'Originally planned for'), 'rescheduled as text with previous date');
    holds(str_contains($html, '(Europe/Prague)'), 'zone hint for a foreign zone');
    $html = KoyomiFixture::app($root, ['koyomi.language' => 'de'])->render('events/workshop-cancelled')->body();
    holds(str_contains($html, 'Abgesagt') && str_contains($html, 'Wegen Krankheit abgesagt.'), 'German status text and note');
    $html = KoyomiFixture::app($root, ['locale' => 'de_DE.UTF-8'])->render('events/retreat')->body();
    holds(str_contains($html, '380 €') && str_contains($html, 'Nur noch wenige Plätze') && str_contains($html, '18:00 Uhr'), 'German via locale');

    // 10. Duplicate reset.
    $kirby = KoyomiFixture::app($root);
    $kirby->impersonate('kirby');
    $copy = $kirby->page('events/workshop-cancelled')->duplicate('workshop-copy');
    expect('scheduled', $copy->content()->get('eventStatus')->value(), 'duplicate: status reset');
    expect('', $copy->content()->get('statusNote')->value(), 'duplicate: note cleared');
    expect('2027-03-25', substr($copy->content()->get('startDate')->value(), 0, 10), 'duplicate: date kept');
    $copy->delete(true);
    $kirby = KoyomiFixture::app($root, ['koyomi.duplicateReset' => false]);
    $kirby->impersonate('kirby');
    $copy = $kirby->page('events/workshop-cancelled')->duplicate('workshop-copy-2');
    expect('cancelled', $copy->content()->get('eventStatus')->value(), 'duplicateReset false keeps the status');
    $copy->delete(true);

    // 11. Site snippet override wins.
    @mkdir($root . '/site/snippets/koyomi', 0700, true);
    file_put_contents($root . '/site/snippets/koyomi/card.php', 'OWN CARD <?= koyomi_event($event)?->title ?>');
    $kirby = KoyomiFixture::app($root);
    expect('OWN CARD Silent Retreat', trim((string) $kirby->snippet('koyomi/card', ['event' => $kirby->page('events/retreat')], true)), 'site/snippets/koyomi/card.php wins');
    unlink($root . '/site/snippets/koyomi/card.php');

    // 12. Blueprints load through Kirby, options follow the config.
    $kirby = KoyomiFixture::app($root, ['koyomi.currencies' => ['EUR', 'USD'], 'koyomi.timezone' => 'Europe/Prague']);
    $blueprint = Blueprint::find('pages/koyomi-event');
    holds(isset($blueprint['tabs']) && is_array($blueprint['tabs']), 'event blueprint has tabs');
    $encoded = json_encode($blueprint);
    holds(str_contains($encoded, '"USD"') && str_contains($encoded, 'Europe\/Prague'), 'blueprint reflects options');
    // Collect every field name (keys of any "fields" array, at any depth).
    $fieldNames = function (array $node) use (&$fieldNames): array {
        $names = [];
        foreach ($node as $key => $value) {
            if ($key === 'fields' && is_array($value)) {
                $names = [...$names, ...array_map('strtolower', array_keys($value))];
            }
            if (is_array($value)) {
                $names = [...$names, ...$fieldNames($value)];
            }
        }
        return $names;
    };
    $names = $fieldNames($blueprint);
    holds(!in_array('status', $names, true) && !in_array('image', $names, true), 'no field named status/image (Page methods)');
    foreach (['startdate', 'starttime', 'enddate', 'endtime', 'allday', 'timezone', 'inperson', 'online', 'venue', 'onlineplatform', 'onlineurl',
        'eventstatus', 'previousstart', 'statusnote', 'isfree', 'price', 'currency', 'pricenote', 'availability', 'bookingurl', 'bookinglabel',
        'organizers', 'categories', 'eventlanguages', 'featured', 'series', 'seriespart', 'excerpt', 'text', 'cover'] as $field) {
        holds(in_array($field, $names, true), "event blueprint has field $field");
    }
    foreach (['koyomi-calendar', 'koyomi-venues', 'koyomi-venue', 'koyomi-organizers', 'koyomi-organizer'] as $name) {
        holds(is_array(Blueprint::find('pages/' . $name)), "blueprint $name");
    }
    expect('pages/koyomi-event', $kirby->page('events/retreat')->blueprint()->name(), 'page blueprint instantiates');
}, fn () => KoyomiFixture::remove($root));

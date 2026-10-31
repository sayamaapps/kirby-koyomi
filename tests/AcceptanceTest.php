<?php

use Kirby\Cms\Blueprint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Koyomi\Api;

/**
 * plugin on the fixture site — off switch, output switches, headers, lists
 * and filters, cache expiry, past pages, JSON-LD only on the single page,
 * duplicate reset, German, site overrides, blueprints.
 */
#[CoversClass(Api::class)]
final class AcceptanceTest extends TestCase
{
	public function testSuite(): void
	{
		$root = KoyomiFixture::create();

		try {

			$code = fn ($kirby, string $path) => $kirby->render($path)?->code() ?? 404;
			$titles = fn (array $events) => array_map(fn ($e) => $e->title, $events);

			// 1. Registered; strings as Kirby translations.
			$kirby = KoyomiFixture::app($root, ['koyomi.active' => false]);
			$this->assertTrue($kirby->plugin('sayamaapps/koyomi') !== null, 'plugin registered');
			$this->assertSame('Abgesagt', $kirby->translation('de')->get('koyomi.status.cancelled'), 'German Kirby translation');
			$this->assertTrue(function_exists('koyomi_option') && function_exists('koyomi_event'), 'helpers defined');

			// 2. Off by default: feeds 404, snippets print nothing.
			foreach (['events.ics', 'events.rss', 'events.json', 'events/retreat.ics'] as $path) {
				$this->assertSame(404, $code($kirby, $path), "inactive: $path is 404");
			}
			$this->assertSame('', trim((string)$kirby->snippet('koyomi/list', [], true)), 'inactive: list prints nothing');
			$this->assertSame('', trim((string)$kirby->snippet('koyomi/event', ['event' => $kirby->page('events/retreat')], true)), 'inactive: event prints nothing');
			$this->assertSame('', trim((string)$kirby->snippet('koyomi/jsonld', ['page' => $kirby->page('events/retreat')], true)), 'inactive: no JSON-LD');
			$kirby = KoyomiFixture::app($root, ['koyomi.active' => 'yes']);
			$this->assertSame(404, $code($kirby, 'events.ics'), 'only a real true switches on');
			$kirby = KoyomiFixture::app($root, ['sayamaapps.koyomi.active' => true, 'koyomi.active' => false]);
			$this->assertSame(200, $code($kirby, 'events.ics'), 'sayamaapps.koyomi.active switches on (wins over koyomi.active)');
			$kirby = KoyomiFixture::app($root, ['sayamaapps.koyomi.active' => false]);
			$this->assertSame(404, $code($kirby, 'events.ics'), 'sayamaapps.koyomi.active => false switches off');

			// 3. Switches.
			$kirby = KoyomiFixture::app($root);
			$this->assertSame(200, $code($kirby, 'events.ics'), 'feed on by default');
			$this->assertSame(200, $code($kirby, 'events/retreat.ics'), 'event ics on by default');
			$this->assertSame(200, $code($kirby, 'events.rss'), 'rss on by default');
			$this->assertSame(404, $code($kirby, 'events.json'), 'json off by default');
			$this->assertSame(200, $code(KoyomiFixture::app($root, ['koyomi.json' => true]), 'events.json'), 'json switched on');
			$kirby = KoyomiFixture::app($root, ['koyomi.feed' => false, 'koyomi.rss' => false]);
			$this->assertSame(404, $code($kirby, 'events.ics'), 'feed off');
			$this->assertSame(404, $code($kirby, 'events.rss'), 'rss off');
			// A fresh App: Kirby keeps one Responder per App, so the 404 of the
			// lines above would otherwise carry over to this render.
			$this->assertSame(
				200,
				$code(KoyomiFixture::app($root, ['koyomi.feed' => false, 'koyomi.rss' => false]), 'events/retreat.ics'),
				'event ics independent of feed'
			);
			$this->assertSame(404, $code(KoyomiFixture::app($root, ['koyomi.ics' => false]), 'events/retreat.ics'), 'event ics off');
			$this->assertSame(404, $code(KoyomiFixture::app($root), 'events/draft-event.ics'), 'draft never served');

			// 4. Headers and body basics.
			$kirby = KoyomiFixture::app($root);
			$r = $kirby->render('events.ics');
			$this->assertTrue(str_starts_with((string)$r->type(), 'text/calendar'), 'feed type text/calendar');
			$this->assertSame('UTF-8', strtoupper((string)$r->charset()), 'feed charset');
			$body = $r->body();
			$this->assertTrue(str_starts_with($body, "BEGIN:VCALENDAR\r\n") && str_ends_with($body, "END:VCALENDAR\r\n"), 'CRLF calendar');
			$this->assertTrue(str_contains($body, "UID:evt-retreat@example.test\r\n"), 'stable UID from the page UUID');
			$this->assertTrue(
				str_contains($body, "DTSTART;TZID=Europe/Prague:20270320T200000\r\n") && str_contains($body, "TZID:Europe/Prague\r\n"),
				'concert in Prague time with VTIMEZONE'
			);
			$this->assertTrue(str_contains($body, "STATUS:CANCELLED\r\n"), 'cancelled event stays in the feed as CANCELLED');
			$this->assertTrue(str_contains($body, 'Past Workshop') === false, 'past workshop (ended > 30 days before now) not in the feed');
			$this->assertTrue(!str_contains($body, 'Unlisted Event') && !str_contains($body, 'Draft Event'), 'feed: listed events only');
			$r = $kirby->render('events/retreat.ics');
			$this->assertSame('attachment; filename="retreat.ics"', $r->headers()['Content-Disposition'] ?? null, 'event download filename');
			$r = KoyomiFixture::app($root)->render('events.rss');
			$this->assertTrue(str_starts_with((string)$r->type(), 'application/rss+xml'), 'rss type');
			$this->assertTrue(str_contains($r->body(), '<title>Silent Retreat</title>'), 'rss item');
			$r = KoyomiFixture::app($root, ['koyomi.json' => true])->render('events.json');
			$json = json_decode($r->body(), true);
			$this->assertTrue(is_array($json) && isset($json['events'][0]['id']), 'json has events');
			$this->assertSame('Spring Retreat', $json['events'][0]['title'], 'json: upcoming, ongoing first');

			// 5. Lists: order, past, unlisted/draft never, hideCancelled, filters.
			$kirby = KoyomiFixture::app($root);
			$calendar = $kirby->site()->koyomiCalendar();
			$this->assertSame('events', $calendar?->id(), 'calendar found');
			$upcoming = ['Spring Retreat', 'Evening Meditation on Zoom', 'Silent Retreat', 'Evening Concert', 'Breathing Workshop', 'Mantra Course 1', 'Mantra Course 2'];
			$this->assertSame($upcoming, $titles($calendar->koyomiUpcoming()), 'upcoming: ongoing first, by start, cancelled listed');
			$this->assertSame(['Past Workshop'], $titles($calendar->koyomiPast()), 'past');
			$this->assertSame(
				array_values(array_diff($upcoming, ['Breathing Workshop'])),
				$titles(KoyomiFixture::app($root, ['koyomi.hideCancelled' => true])->page('events')->koyomiUpcoming()),
				'hideCancelled'
			);
			$list = fn (array $query) => $titles(KoyomiFixture::app($root)->page('events')->koyomiList($query)['events']);
			$this->assertSame(
				['Spring Retreat', 'Evening Meditation on Zoom', 'Silent Retreat'],
				$list(['attendance' => 'online']),
				'online filter: moved online + online + hybrid'
			);
			$this->assertSame(['Spring Retreat', 'Silent Retreat'], $list(['category' => 'retreat']), 'category filter, case-insensitive');
			$this->assertSame(['Silent Retreat', 'Evening Concert'], $list(['language' => 'cs']), 'language filter');
			$this->assertSame(['Silent Retreat'], $list(['featured' => '1']), 'featured filter');
			$this->assertSame(['Past Workshop'], $list(['view' => 'past']), 'past view');
			$paged = KoyomiFixture::app($root, ['koyomi.perPage' => 3])->page('events')->koyomiList(['page' => '2']);
			$this->assertSame(['Evening Concert', 'Breathing Workshop', 'Mantra Course 1'], $titles($paged['events']), 'page 2 of 3');
			$this->assertSame([2, 3, 7], [$paged['pagination']['page'], $paged['pagination']['pages'], $paged['pagination']['total']], 'pagination numbers');
			$this->assertSame('https://example.test/events?page=3', $paged['pagination']['nextUrl'], 'next page URL');

			// 6. Cache expiry: the next start/end after now (zoom session starts 2027-03-11 19:00 Berlin),
			// and for the timeline with its Today line the local midnight before it.
			$kirby = KoyomiFixture::app($root);
			$kirby->render('events');
			$this->assertSame(
				(new DateTimeImmutable('2027-03-11 00:00', new DateTimeZone('Europe/Berlin')))->getTimestamp(),
				$kirby->response()->expires(),
				'calendar page (timeline, Today line) expires at local midnight'
			);
			$kirby = KoyomiFixture::app($root, ['koyomi.groupBy' => false]);
			$kirby->render('events');
			$this->assertSame(
				(new DateTimeImmutable('2027-03-11 19:00', new DateTimeZone('Europe/Berlin')))->getTimestamp(),
				$kirby->response()->expires(),
				'calendar page (flat list) expires when the next event starts'
			);
			$kirby = KoyomiFixture::app($root);
			$kirby->render('events/retreat');
			$this->assertSame(
				(new DateTimeImmutable('2027-03-12 18:00', new DateTimeZone('Europe/Berlin')))->getTimestamp(),
				$kirby->response()->expires(),
				'event page expires at its start'
			);

			// 7. Past event page: kept, note, link back; isPast() for sitemaps.
			$kirby = KoyomiFixture::app($root);
			$page = $kirby->page('events/past-workshop');
			$this->assertTrue($page->isPast() && !$kirby->page('events/retreat')->isPast(), 'isPast()');
			$this->assertTrue($kirby->page('events/ongoing-retreat')->isOngoing(), 'isOngoing()');
			$html = $kirby->render('events/past-workshop')->body();
			$this->assertTrue(
				str_contains($html, 'This event has already taken place.') && str_contains($html, 'href="https://example.test/events"'),
				'past note with link to upcoming'
			);

			// 8. JSON-LD on the single page only.
			$html = $kirby->render('events/retreat')->body();
			$this->assertTrue(str_contains($html, '<script type="application/ld+json">') && str_contains($html, 'MixedEventAttendanceMode'), 'JSON-LD on the event page');
			$this->assertTrue(!str_contains(KoyomiFixture::app($root)->render('events')->body(), 'application/ld+json'), 'no JSON-LD on the list');
			$this->assertTrue(str_contains($html, '<time datetime="2027-03-12T18:00:00+01:00">'), '<time datetime> with offset');
			$this->assertTrue(
				str_contains($html, 'Only a few places left') && str_contains($html, '380 €') === false && str_contains($html, '€380'),
				'availability as text, English money'
			);
			$this->assertTrue(str_contains($html, 'calendar.google.com/calendar/render?action=TEMPLATE'), 'Google link on by default');
			$this->assertTrue(
				!str_contains(KoyomiFixture::app($root, ['koyomi.google' => false])->render('events/retreat')->body(), 'calendar.google.com'),
				'Google link switchable'
			);
			$html = KoyomiFixture::app($root)->render('events/series-part-1')->body();
			$this->assertTrue(str_contains($html, 'Part 1 of 2') && str_contains($html, 'Mantra Course 2'), 'series navigation');

			// 9. Status as text.
			$html = KoyomiFixture::app($root)->render('events/concert')->body();
			$this->assertTrue(str_contains($html, 'Rescheduled') && str_contains($html, 'Originally planned for'), 'rescheduled as text with previous date');
			$this->assertTrue(str_contains($html, '(Europe/Prague)'), 'zone hint for a foreign zone');
			$html = KoyomiFixture::app($root, ['koyomi.language' => 'de'])->render('events/workshop-cancelled')->body();
			$this->assertTrue(str_contains($html, 'Abgesagt') && str_contains($html, 'Wegen Krankheit abgesagt.'), 'German status text and note');
			$html = KoyomiFixture::app($root, ['locale' => 'de_DE.UTF-8'])->render('events/retreat')->body();
			$this->assertTrue(str_contains($html, '380 €') && str_contains($html, 'Nur noch wenige Plätze') && str_contains($html, '18:00 Uhr'), 'German via locale');

			// 10. Duplicate reset.
			$kirby = KoyomiFixture::app($root);
			$kirby->impersonate('kirby');
			$copy = $kirby->page('events/workshop-cancelled')->duplicate('workshop-copy');
			$this->assertSame('scheduled', $copy->content()->get('eventStatus')->value(), 'duplicate: status reset');
			$this->assertSame('', $copy->content()->get('statusNote')->value(), 'duplicate: note cleared');
			$this->assertSame('2027-03-25', substr($copy->content()->get('startDate')->value(), 0, 10), 'duplicate: date kept');
			$copy->delete(true);
			$kirby = KoyomiFixture::app($root, ['koyomi.duplicateReset' => false]);
			$kirby->impersonate('kirby');
			$copy = $kirby->page('events/workshop-cancelled')->duplicate('workshop-copy-2');
			$this->assertSame('cancelled', $copy->content()->get('eventStatus')->value(), 'duplicateReset false keeps the status');
			$copy->delete(true);

			// 11. Site snippet override wins.
			@mkdir($root . '/site/snippets/koyomi', 0700, true);
			file_put_contents($root . '/site/snippets/koyomi/card.php', 'OWN CARD <?= \Sayamaapps\Koyomi\Api::event($event)?->title ?>');
			$kirby = KoyomiFixture::app($root);
			$this->assertSame(
				'OWN CARD Silent Retreat',
				trim((string)$kirby->snippet('koyomi/card', ['event' => $kirby->page('events/retreat')], true)),
				'site/snippets/koyomi/card.php wins'
			);
			unlink($root . '/site/snippets/koyomi/card.php');

			// 12. Blueprints load through Kirby, options follow the config.
			$kirby = KoyomiFixture::app($root, ['koyomi.currencies' => ['EUR', 'USD'], 'koyomi.timezone' => 'Europe/Prague']);
			$blueprint = Blueprint::find('pages/koyomi-event');
			$this->assertTrue(isset($blueprint['tabs']) && is_array($blueprint['tabs']), 'event blueprint has tabs');
			$encoded = json_encode($blueprint);
			$this->assertTrue(str_contains($encoded, '"USD"') && str_contains($encoded, 'Europe\/Prague'), 'blueprint reflects options');
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
			$this->assertTrue(!in_array('status', $names, true) && !in_array('image', $names, true), 'no field named status/image (Page methods)');
			foreach (['startdate', 'starttime', 'enddate', 'endtime', 'allday', 'timezone', 'inperson', 'online', 'venue', 'onlineplatform', 'onlineurl',
				'eventstatus', 'previousstart', 'statusnote', 'isfree', 'price', 'currency', 'pricenote', 'availability', 'bookingurl', 'bookinglabel',
				'organizers', 'categories', 'eventlanguages', 'featured', 'series', 'seriespart', 'excerpt', 'text', 'cover'] as $field) {
				$this->assertTrue(in_array($field, $names, true), "event blueprint has field $field");
			}
			foreach (['koyomi-calendar', 'koyomi-venues', 'koyomi-venue', 'koyomi-organizers', 'koyomi-organizer'] as $name) {
				$this->assertTrue(is_array(Blueprint::find('pages/' . $name)), "blueprint $name");
			}
			$this->assertSame('pages/koyomi-event', $kirby->page('events/retreat')->blueprint()->name(), 'page blueprint instantiates');

		} finally {
			KoyomiFixture::remove($root);
		}
	}
}

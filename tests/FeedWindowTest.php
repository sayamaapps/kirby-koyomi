<?php

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/** Berlin wall time as a timestamp (the expiry is a Unix timestamp). */
function feed_window_berlin(string $wall): int
{
	return (new DateTimeImmutable($wall, new DateTimeZone('Europe/Berlin')))->getTimestamp();
}

/** An empty site with the plugin linked in and four events around the pinned clock (2027-03-10 12:00). */
function feed_window_site(): string
{
	$root = sys_get_temp_dir() . '/koyomi-fixture-' . bin2hex(random_bytes(6));
	foreach (['public', 'site/plugins', 'site/templates', 'storage', 'content'] as $dir) {
		mkdir($root . '/' . $dir, 0700, true);
	}
	symlink(dirname(__DIR__), $root . '/site/plugins/koyomi');
	file_put_contents($root . '/site/templates/default.php', '<?= $page->title()->escape() ?>');
	KoyomiFixture::write($root . '/content/site.txt', ['Title' => 'Feed Site']);
	KoyomiFixture::write($root . '/content/1_events/koyomi-calendar.txt', ['Title' => 'Events', 'Uuid' => 'calendar-feed']);
	$event = fn (string $title, string $uuid, string $date, string $start, string $end) => [
		'Title' => $title, 'Uuid' => $uuid, 'StartDate' => $date, 'StartTime' => $start, 'EndTime' => $end,
		'AllDay' => 'false', 'Timezone' => 'Europe/Berlin', 'InPerson' => 'true', 'Online' => 'false',
	];
	// Ended 11 days before the clock: inside the default 30 days.
	KoyomiFixture::write($root . '/content/1_events/20270227_ended-recently/koyomi-event.txt', $event('Ended Recently', 'evt-recent', '2027-02-27', '10:00:00', '11:00:00'));
	// Ended 37 days before the clock: outside the default 30 days.
	KoyomiFixture::write($root . '/content/1_events/20270201_old-workshop/koyomi-event.txt', $event('Old Workshop', 'evt-old', '2027-02-01', '10:00:00', '11:00:00'));
	// Running at the clock (started 10:00, ends 18:00): always in the feed.
	KoyomiFixture::write($root . '/content/1_events/20270310_running-today/koyomi-event.txt', $event('Running Today', 'evt-running', '2027-03-10', '10:00:00', '18:00:00'));
	KoyomiFixture::write($root . '/content/1_events/20270312_plan/koyomi-event.txt', $event('Plan', 'evt-plan', '2027-03-12', '18:00:00', '20:00:00'));
	return $root;
}

/**
 * The subscription feed window (koyomi.feedPastDays) and the cache expiry of
 * own markup (review round, findings 7, 10 and 18): an event that ended inside
 * the window is in events.ics, one that ended outside it is not, and
 * $page->koyomiData() lowers the page cache expiry like the event snippet does.
 */
#[CoversNothing]
final class FeedWindowTest extends TestCase
{
	public function testSuite(): void
	{
		$root = feed_window_site();

		try {

			$feed = fn (array $options = []): string => (string)KoyomiFixture::app($root, $options)->render('events.ics')?->body();

			// Finding 7: the window keeps what ended within feedPastDays, drops what ended before it.
			$default = $feed();
			holds(str_contains($default, 'UID:evt-recent@example.test'), 'default 30 days: an event that ended 11 days ago is in events.ics');
			holds(!str_contains($default, 'UID:evt-old@example.test'), 'default 30 days: an event that ended 37 days ago is not');
			holds(str_contains($default, 'UID:evt-running@example.test') && str_contains($default, 'UID:evt-plan@example.test'), 'running and upcoming events are in the feed');

			$wider = $feed(['koyomi.feedPastDays' => 60]);
			holds(str_contains($wider, 'UID:evt-old@example.test') && str_contains($wider, 'UID:evt-recent@example.test'), 'feedPastDays 60: both past events are in the feed');

			$none = $feed(['koyomi.feedPastDays' => 0]);
			holds(!str_contains($none, 'UID:evt-recent@example.test') && !str_contains($none, 'UID:evt-old@example.test'), 'feedPastDays 0: nothing that has ended is in the feed');
			holds(str_contains($none, 'UID:evt-running@example.test') && str_contains($none, 'UID:evt-plan@example.test'), 'feedPastDays 0: running and upcoming events stay');

			// Findings 10 and 18: own markup through koyomiData() lowers the expiry like the event snippet.
			$app = KoyomiFixture::app($root);
			expect(null, $app->response()->expires(), 'precondition: no expiry before the page is read');
			$data = $app->page('events/running-today')->koyomiData();
			expect([true, false], [$data['isOngoing'], $data['isPast']], 'koyomiData: running now, not past');
			expect(feed_window_berlin('2027-03-10 18:00'), $app->response()->expires(), 'koyomiData: the page cache expires at the end of the running event');

			$app = KoyomiFixture::app($root);
			$app->page('events/plan')->koyomiData();
			expect(feed_window_berlin('2027-03-12 18:00'), $app->response()->expires(), 'koyomiData: an upcoming event expires at its start');

			$app = KoyomiFixture::app($root);
			holds($app->page('events/plan')->isOngoing() === false, 'isOngoing() is false before the start');
			expect(feed_window_berlin('2027-03-12 18:00'), $app->response()->expires(), 'isOngoing() lowers the expiry too');

			$app = KoyomiFixture::app($root);
			holds($app->page('events/ended-recently')->isPast(), 'isPast() true for the event that ended');
			holds($app->response()->expires() === null, 'isPast() of a past event with no later start: no expiry from this event alone');

			// The page cache also goes stale when an event leaves the feed window and, for a
			// timeline with its "Today" line, at local midnight — not only at starts and ends.
			// A site whose next event is weeks away: one that ended 11 days ago, one on 1 May.
			$sparse = feed_window_site();
			try {
				foreach (['20270201_old-workshop', '20270310_running-today', '20270312_plan'] as $dir) {
					unlink("$sparse/content/1_events/$dir/koyomi-event.txt");
					rmdir("$sparse/content/1_events/$dir");
				}
				mkdir("$sparse/content/1_events/20270501_may");
				KoyomiFixture::write("$sparse/content/1_events/20270501_may/koyomi-event.txt", [
					'Title' => 'May', 'Uuid' => 'evt-may', 'StartDate' => '2027-05-01', 'StartTime' => '18:00:00', 'EndTime' => '20:00:00',
					'AllDay' => 'false', 'Timezone' => 'Europe/Berlin', 'InPerson' => 'true', 'Online' => 'false',
				]);
				$app = KoyomiFixture::app($sparse);
				holds(str_contains((string)$app->render('events.ics')?->body(), 'UID:evt-recent@example.test'), 'sparse site: the event that ended 11 days ago is in the feed');
				expect(feed_window_berlin('2027-03-29 11:00'), $app->response()->expires(), 'feed: expires when that event leaves the 30-day window, not at the next start in May');
				$app = KoyomiFixture::app($sparse, ['koyomi.feedPastDays' => 0]);
				$app->render('events.ics');
				expect(feed_window_berlin('2027-05-01 18:00'), $app->response()->expires(), 'feedPastDays 0: nothing to leave, the next start');

				$app = KoyomiFixture::app($sparse, ['koyomi.groupBy' => 'month']);
				$html = (string)$app->render('events')?->body();
				holds(str_contains($html, 'class="koyomi-today"'), 'calendar page: the timeline shows its Today line');
				expect(feed_window_berlin('2027-03-11 00:00'), $app->response()->expires(), 'calendar page with a Today line: expires at local midnight');
				$app = KoyomiFixture::app($sparse, ['koyomi.groupBy' => false]);
				$app->render('events');
				expect(feed_window_berlin('2027-05-01 18:00'), $app->response()->expires(), 'flat list without a Today line: the next start');
			} finally {
				KoyomiFixture::remove($sparse);
			}

		} finally {
			KoyomiFixture::remove($root);
		}
	}
}

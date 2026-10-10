<?php
/**
 * The subscription feed window (koyomi.feedPastDays) and the cache expiry of
 * own markup (review round, findings 7, 10 and 18): an event that ended inside
 * the window is in events.ics, one that ended outside it is not, and
 * $page->koyomiData() lowers the page cache expiry like the event snippet does.
 *   php tests/FeedWindowTest.php
 */
require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/lib/expect.php';
require __DIR__ . '/lib/Fixture.php';

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

$root = feed_window_site();

koyomi_test('Koyomi feed window', function () use ($root) {
    $feed = fn (array $options = []): string => (string) KoyomiFixture::app($root, $options)->render('events.ics')?->body();

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
}, fn () => KoyomiFixture::remove($root));

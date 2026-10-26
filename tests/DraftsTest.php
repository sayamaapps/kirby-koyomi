<?php

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Koyomi\Mapper;
use Koyomi\Repository;

/**
 * An empty site with the plugin linked in (KoyomiFixture::create() would add
 * the whole shared fixture, and these tests want exactly the pages below).
 * The directory name keeps KoyomiFixture::remove() willing to delete it.
 */
function drafts_bare_root(): string
{
    $root = sys_get_temp_dir() . '/koyomi-fixture-' . bin2hex(random_bytes(6));
    foreach (['public', 'site/plugins', 'site/templates', 'site/snippets', 'site/blueprints/pages', 'storage', 'content'] as $dir) {
        mkdir($root . '/' . $dir, 0700, true);
    }
    symlink(dirname(__DIR__), $root . '/site/plugins/koyomi');
    file_put_contents($root . '/site/templates/default.php', '<?= $page->title()->escape() ?>');
    KoyomiFixture::write($root . '/content/site.txt', ['Title' => 'Koyomi Drafts Site']);
    KoyomiFixture::write($root . '/content/home/home.txt', ['Title' => 'Home']);
    KoyomiFixture::write($root . '/content/error/error.txt', ['Title' => 'Error']);
    return $root;
}

/**
 * A site with: a listed event whose venue and one organizer are drafts, a
 * listed part one whose series points at a draft part, a foreign calendar's
 * event and a listed part three; plus the draft pages themselves.
 */
function drafts_test_site(): string
{
    $root = drafts_bare_root();
    $w = fn (string $path, array $fields) => KoyomiFixture::write($root . '/content/' . $path, $fields);
    $event = fn (string $title, string $uuid, string $date, string $time, string $endTime = '') => [
        'Title' => $title, 'Uuid' => $uuid, 'StartDate' => $date, 'StartTime' => $time, 'EndTime' => $endTime,
        'AllDay' => 'false', 'Timezone' => 'Europe/Berlin', 'InPerson' => 'true', 'Online' => 'false',
    ];

    $w('1_events/koyomi-calendar.txt', ['Title' => 'Events', 'Uuid' => 'calendar-drafts']);
    $w('1_events/20270312_planned-retreat/koyomi-event.txt', $event('Planned Retreat', 'evt-planned', '2027-03-12', '18:00:00', '13:00:00')
        + ['Venue' => "- page://venue-draft", 'Organizers' => "- page://org-draft\n- page://org-public"]);
    $w('1_events/20270401_part-one/koyomi-event.txt', $event('Part One', 'evt-part-one', '2027-04-01', '10:00:00', '12:00:00')
        + ['Series' => "- page://evt-part-two\n- page://evt-foreign\n- page://evt-part-three", 'SeriesPart' => '1']);
    $w('1_events/20270415_part-three/koyomi-event.txt', $event('Part Three', 'evt-part-three', '2027-04-15', '10:00:00', '12:00:00')
        + ['Series' => "- page://evt-part-one", 'SeriesPart' => '3']);
    $w('1_events/_drafts/20270408_part-two/koyomi-event.txt', $event('Secret Part Two', 'evt-part-two', '2027-04-08', '10:00:00', '12:00:00'));

    $w('2_other/koyomi-calendar.txt', ['Title' => 'Other calendar', 'Uuid' => 'calendar-other']);
    $w('2_other/20270420_foreign/koyomi-event.txt', $event('Foreign Event', 'evt-foreign', '2027-04-20', '10:00:00', '12:00:00'));

    $w('venues/koyomi-venues.txt', ['Title' => 'Venues', 'Uuid' => 'venues-drafts']);
    $w('venues/_drafts/secret/koyomi-venue.txt', [
        'Title' => 'Secret Venue', 'Uuid' => 'venue-draft', 'Street' => 'Geheimweg 9', 'City' => 'Nirgendwo', 'Country' => 'DE', 'ShowMap' => 'false',
    ]);

    $w('organizers/koyomi-organizers.txt', ['Title' => 'Organizers', 'Uuid' => 'organizers-drafts']);
    $w('organizers/_drafts/secret/koyomi-organizer.txt', [
        'Title' => 'Draft Organizer Secret', 'Uuid' => 'org-draft', 'Kind' => 'organization',
        'Email' => 'secret@draft-org.example', 'ShowEmail' => 'true', 'Website' => 'https://draft-org.example',
    ]);
    $w('organizers/public/koyomi-organizer.txt', [
        'Title' => 'Public Host', 'Uuid' => 'org-public', 'Kind' => 'organization',
        'Email' => 'host@public.example', 'ShowEmail' => 'true', 'Website' => 'https://host.example',
    ]);
    return $root;
}

/**
 * Drafts never reach the public output (review round, findings 1, 9, 16, 17):
 * a series sibling, venue or organizer that is a draft is left out of the
 * event data, its HTML, the JSON feed and the ICS file, and a draft calendar
 * is not "the" calendar of the site. Other calendars' events are not series parts.
 */
#[CoversNothing]
final class DraftsTest extends TestCase
{
	public function testSuite(): void
	{
		$root = drafts_test_site();

		$foreignRoot = drafts_bare_root();


		$partOne = function () use ($root) {
		    $kirby = KoyomiFixture::app($root);
		    $raw = fn (string $path): array => Mapper::raw($kirby->page($path));

		    // Finding 9 and 16: a draft venue and a draft organizer are not published with the event.
		    $planned = $raw('events/planned-retreat');
		    expect(null, $planned['venue'], 'draft venue: the event shows no venue');
		    expect(['Public Host'], array_map(fn ($o) => $o->name, Mapper::event($kirby->page('events/planned-retreat'))->organizers), 'draft organizer left out, public one kept');
		    holds(!str_contains(json_encode($planned, JSON_THROW_ON_ERROR), 'Secret') && !str_contains(json_encode($planned, JSON_THROW_ON_ERROR), 'draft-org'), 'draft venue and organizer leave no trace in the mapped data');

		    // Finding 1 and 16: a draft series part (title, date, URL) is not in the series navigation.
		    // Raw: the sibling fields only (the event itself is added by Event::fromArray).
		    $partOneData = $raw('events/part-one');
		    expect(['Part Three'], array_map(fn ($s) => $s['title'], $partOneData['series']), 'series: the draft part and the foreign event are left out');
		    expect(['Part One', 'Part Three'], array_map(fn ($s) => $s['title'], Mapper::event($kirby->page('events/part-one'))->series), 'Event series: the published parts, the event itself included');
		    $event = Mapper::event($kirby->page('events/part-one'));
		    expect(2, $event->seriesTotal(), 'series: total counts only the published parts');
		    expect([1, 3], [$event->seriesPart, $event->series[1]['part']], 'series: own part kept, the sibling keeps its own number');
		    holds(!str_contains(json_encode($partOneData, JSON_THROW_ON_ERROR), 'Secret') && !str_contains(json_encode($partOneData, JSON_THROW_ON_ERROR), 'Foreign'), 'series: no draft or foreign title in the data');

		    // Finding 9: the draft venue's page is not in the Panel-independent public lists either, and the event lists stay listed-only.
		    expect(['events/part-one', 'events/part-three', 'events/planned-retreat'], (function () use ($kirby) {
		        $ids = array_map(fn ($p) => $p->id(), iterator_to_array(Repository::eventPages($kirby->page('events'))));
		        sort($ids);
		        return $ids;
		    })(), 'event pages: drafts and the foreign calendar are left out');

		    // The public HTML of the event page, the JSON feed and the ICS file show none of the drafts.
		    $html = (string) $kirby->render('events/part-one')?->body();
		    holds(str_contains($html, 'Part Three') && str_contains($html, 'Part One'), 'HTML: the published series parts are shown');
		    holds(!str_contains($html, 'Secret') && !str_contains($html, 'Foreign Event') && !str_contains($html, 'evt-part-two'), 'HTML: no draft series part, no foreign event');
		    $planHtml = (string) KoyomiFixture::app($root)->render('events/planned-retreat')?->body();
		    holds(str_contains($planHtml, 'Public Host') && !str_contains($planHtml, 'Secret') && !str_contains($planHtml, 'Geheimweg') && !str_contains($planHtml, 'draft-org'), 'HTML: draft venue and organizer are not printed, the public organizer is');
		    $json = (string) KoyomiFixture::app($root, ['koyomi.json' => true])->render('events.json')?->body();
		    holds(str_contains($json, 'Planned Retreat') && !str_contains($json, 'Secret') && !str_contains($json, 'Geheimweg') && !str_contains($json, 'draft-org') && !str_contains($json, 'Foreign'), 'JSON feed: no draft and no foreign data');
		    $ics = (string) KoyomiFixture::app($root)->render('events/part-one.ics')?->body();
		    holds(!str_contains($ics, 'Secret') && !str_contains($ics, 'Foreign') && !str_contains($ics, 'Part Two'), 'ICS: no draft series part in the event file');
		    $feed = (string) KoyomiFixture::app($root)->render('events.ics')?->body();
		    holds(!str_contains($feed, 'Secret') && !str_contains($feed, 'Geheimweg') && !str_contains($feed, 'draft-org'), 'ICS feed: no draft data');
		};


		$partTwo = function () use ($root, $foreignRoot) {
		    // Finding 17: the public calendar is the published one only. A draft calendar
		    // (here in _drafts) is not returned, not by the configured id either.
		    KoyomiFixture::write($foreignRoot . '/content/_drafts/events/koyomi-calendar.txt', ['Title' => 'Draft calendar', 'Uuid' => 'calendar-draft']);
		    KoyomiFixture::write($foreignRoot . '/content/_drafts/events/20270501_draft-talk/koyomi-event.txt', [
		        'Title' => 'Draft Calendar Talk', 'Uuid' => 'evt-draft-talk', 'StartDate' => '2027-05-01', 'StartTime' => '10:00:00',
		        'Timezone' => 'Europe/Berlin', 'InPerson' => 'true', 'Online' => 'false',
		    ]);
		    file_put_contents($foreignRoot . '/site/templates/home.php', '<?php snippet("koyomi/list") ?>');

		    $kirby = KoyomiFixture::app($foreignRoot);
		    expect(null, Repository::calendar($kirby), 'a draft calendar is not the calendar');
		    expect(null, $kirby->site()->koyomiCalendar(), 'site()->koyomiCalendar() is null for a draft calendar only');
		    expect(null, Repository::calendar(KoyomiFixture::app($foreignRoot, ['koyomi.calendar' => '_drafts/events'])), 'a configured draft calendar is not used either');
		    $home = (string) KoyomiFixture::app($foreignRoot)->render('')?->body();
		    holds(!str_contains($home, 'Draft Calendar Talk'), 'home page list: the draft calendar\'s events do not appear');
		    holds(str_contains($home, 'koyomi-empty'), 'home page list: the empty state is printed instead');

		    // Draft venues and organizers remain pickable in the Panel (collections are not changed).
		    $picker = KoyomiFixture::app($root);
		    $pickerSlugs = array_map(fn ($p) => $p->slug(), iterator_to_array(Repository::organizerPages($picker)));
		    sort($pickerSlugs);
		    expect(['public', 'secret'], $pickerSlugs, 'Panel picker still offers draft organizers');
		};

		try {

		    $partOne();
		    $partTwo();

		} finally {

		    KoyomiFixture::remove($root);
		    KoyomiFixture::remove($foreignRoot);

		}	}
}

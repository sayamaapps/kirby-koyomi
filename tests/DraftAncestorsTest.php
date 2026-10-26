<?php

use Kirby\Exception\NotFoundException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Koyomi\Mapper;
use Sayamaapps\Koyomi\Output;
use Sayamaapps\Koyomi\Repository;

function ancestors_bare_root(): string
{
	$root = sys_get_temp_dir() . '/koyomi-fixture-' . bin2hex(random_bytes(6));
	foreach (['public', 'site/plugins', 'site/templates', 'site/snippets', 'site/blueprints/pages', 'storage', 'content'] as $dir) {
		mkdir($root . '/' . $dir, 0700, true);
	}
	symlink(dirname(__DIR__), $root . '/site/plugins/koyomi');
	file_put_contents($root . '/site/templates/default.php', '<?= $page->title()->escape() ?>');
	KoyomiFixture::write($root . '/content/site.txt', ['Title' => 'Koyomi Ancestors Site']);
	KoyomiFixture::write($root . '/content/home/home.txt', ['Title' => 'Home']);
	KoyomiFixture::write($root . '/content/error/error.txt', ['Title' => 'Error']);
	return $root;
}

function ancestors_event(string $title, string $uuid, string $date): array
{
	return [
		'Title' => $title, 'Uuid' => $uuid, 'StartDate' => $date, 'StartTime' => '18:00:00', 'EndTime' => '20:00:00',
		'AllDay' => 'false', 'Timezone' => 'Europe/Berlin', 'InPerson' => 'true', 'Online' => 'false',
	];
}

/**
 * Containers "venues" and "organizers" are drafts, "region" is a draft with a
 * normal "venues" container below it; their unlisted children are normal pages.
 * "places" and "hosts" are published containers with the same kind of children.
 */
function ancestors_site(): string
{
	$root = ancestors_bare_root();
	$w = fn (string $path, array $fields) => KoyomiFixture::write($root . '/content/' . $path, $fields);
	$venue = fn (string $title, string $uuid, string $street) => [
		'Title' => $title, 'Uuid' => $uuid, 'Street' => $street, 'City' => 'Musterstadt', 'Country' => 'DE', 'ShowMap' => 'false',
	];
	$organizer = fn (string $title, string $uuid, string $email) => [
		'Title' => $title, 'Uuid' => $uuid, 'Kind' => 'organization', 'Email' => $email, 'ShowEmail' => 'true',
	];

	$w('1_events/koyomi-calendar.txt', ['Title' => 'Events', 'Uuid' => 'calendar-anc']);
	$w('1_events/20270312_hidden-places/koyomi-event.txt', ancestors_event('Hidden Places Event', 'evt-hidden', '2027-03-12')
		+ ['Venue' => '- page://venue-hidden', 'Organizers' => '- page://org-hidden']);
	$w('1_events/20270313_far-place/koyomi-event.txt', ancestors_event('Far Place Event', 'evt-far', '2027-03-13')
		+ ['Venue' => '- page://venue-far']);
	$w('1_events/20270314_open-places/koyomi-event.txt', ancestors_event('Open Places Event', 'evt-open', '2027-03-14')
		+ ['Venue' => '- page://venue-open', 'Organizers' => '- page://org-open']);

	$w('_drafts/venues/koyomi-venues.txt', ['Title' => 'Venues', 'Uuid' => 'venues-anc']);
	$w('_drafts/venues/hidden/koyomi-venue.txt', $venue('Hidden Hall', 'venue-hidden', 'Verstecktgasse 5'));
	$w('_drafts/organizers/koyomi-organizers.txt', ['Title' => 'Organizers', 'Uuid' => 'organizers-anc']);
	$w('_drafts/organizers/hidden/koyomi-organizer.txt', $organizer('Hidden Host', 'org-hidden', 'hidden-host@example.org'));
	$w('_drafts/region/default.txt', ['Title' => 'Region']);
	$w('_drafts/region/venues/koyomi-venues.txt', ['Title' => 'Region venues', 'Uuid' => 'region-venues-anc']);
	$w('_drafts/region/venues/far/koyomi-venue.txt', $venue('Faraway Hall', 'venue-far', 'Fernweg 3'));

	$w('places/koyomi-venues.txt', ['Title' => 'Places', 'Uuid' => 'places-anc']);
	$w('places/open/koyomi-venue.txt', $venue('Open Hall', 'venue-open', 'Offenstrasse 1'));
	$w('hosts/koyomi-organizers.txt', ['Title' => 'Hosts', 'Uuid' => 'hosts-anc']);
	$w('hosts/open/koyomi-organizer.txt', $organizer('Open Host', 'org-open', 'open-host@example.org'));
	return $root;
}

/**
 * A page below a draft is a draft (review finding KOY-001): a venue or
 * organizer that is itself unlisted but lives under a container the editor put
 * back into draft has isDraft() === false, yet its own URL answers 404. Events
 * that reference it, the calendar lookup and /<event>.ics must treat it as a
 * draft; the same pages under a published container stay visible.
 */
#[CoversNothing]
final class DraftAncestorsTest extends TestCase
{
	public function testSuite(): void
	{
		$root = ancestors_site();

		$archiveRoot = ancestors_bare_root();

		$jsonOptions = ['sayamaapps.koyomi.json' => true];


		$body = fn (string $root, string $path, array $options = []): string => (string)KoyomiFixture::app($root, $options)->render($path)?->body();


		$partOne = function () use ($root, $body, $jsonOptions) {
			$kirby = KoyomiFixture::app($root);

			// What the test guards against: Kirby's own isDraft() is false for the children.
			foreach ([['venues/hidden', 'venues'], ['organizers/hidden', 'organizers'], ['region/venues/far', 'region']] as [$childId, $topId]) {
				$child = $kirby->page('_drafts/' . $childId);
				holds($child !== null, $childId . ': the page below the draft resolves');
				expect(false, $child->isDraft(), $childId . ': Kirby says the page itself is no draft');
				expect(true, $kirby->page('_drafts/' . $topId)->isDraft(), $topId . ': the container is a draft');
				expect(true, Mapper::isDraft($child), $childId . ': Mapper::isDraft() sees the draft above');
				expect(null, $kirby->site()->find($childId), $childId . ': Kirby\'s Site::find() does not reach it');
			}
			expect(false, Mapper::isDraft($kirby->page('places/open')), 'a venue below a published container is no draft');
			expect(false, Mapper::isDraft($kirby->page('events/open-places')), 'a published event is no draft');

			// The mapped data.
			$hidden = Mapper::raw($kirby->page('events/hidden-places'));
			expect(null, $hidden['venue'], 'venue below a draft container: not in the event data');
			expect([], $hidden['organizers'], 'organizer below a draft container: not in the event data');
			expect(null, Mapper::raw($kirby->page('events/far-place'))['venue'], 'venue two levels below a draft: not in the event data');
			$open = Mapper::raw($kirby->page('events/open-places'));
			holds($open['venue'] !== null && count($open['organizers']) === 1, 'counter-check: venue and organizer below published containers are kept');

			// Every output of the hidden event and the far event, and the feeds.
			$secrets = ['Hidden Hall', 'Verstecktgasse', 'Hidden Host', 'hidden-host@example.org', 'Faraway Hall', 'Fernweg'];
			$outputs = [
				'event HTML' => $body($root, 'events/hidden-places'),
				'far event HTML' => $body($root, 'events/far-place'),
				'event ICS' => $body($root, 'events/hidden-places.ics'),
				'far event ICS' => $body($root, 'events/far-place.ics'),
				'calendar ICS' => $body($root, 'events.ics'),
				'calendar JSON' => $body($root, 'events.json', $jsonOptions),
				'calendar RSS' => $body($root, 'events.rss'),
			];
			foreach ($outputs as $name => $text) {
				holds(str_contains($text, 'Event') || str_contains($text, 'BEGIN:VCALENDAR'), $name . ': rendered something');
				foreach ($secrets as $secret) {
					holds(!str_contains($text, $secret), $name . ': does not contain "' . $secret . '"');
				}
			}

			// Counter-check: the same kind of pages below a published container are shown.
			$openHtml = $body($root, 'events/open-places');
			holds(str_contains($openHtml, 'Open Hall') && str_contains($openHtml, 'Open Host'), 'counter-check HTML: venue and organizer below published containers are shown');
			$openIcs = $body($root, 'events/open-places.ics');
			holds(str_contains($openIcs, 'Open Hall'), 'counter-check ICS: the venue below a published container is shown');
			holds(str_contains($body($root, 'events.json', $jsonOptions), 'Open Hall'), 'counter-check JSON: the venue below a published container is shown');

			// The direct URLs are 404.
			foreach (['venues/hidden', 'organizers/hidden', 'region/venues/far'] as $path) {
				$response = KoyomiFixture::app($root)->render($path);
				expect(404, $response?->code(), $path . ': the page\'s own URL answers 404');
			}
		};


		$partTwo = function () use ($archiveRoot) {
			// A calendar below a draft container: the configured id finds it through
			// App::page() (draft fallback), but it is not public.
			$w = fn (string $path, array $fields) => KoyomiFixture::write($archiveRoot . '/content/' . $path, $fields);
			$w('_drafts/archive/default.txt', ['Title' => 'Archive']);
			$w('_drafts/archive/events/koyomi-calendar.txt', ['Title' => 'Archived events', 'Uuid' => 'calendar-archive']);
			$w('_drafts/archive/events/20270501_buried/koyomi-event.txt', ancestors_event('Buried Event', 'evt-buried', '2027-05-01'));

			$kirby = KoyomiFixture::app($archiveRoot, ['sayamaapps.koyomi.calendar' => '_drafts/archive/events']);
			$calendarPage = $kirby->page('_drafts/archive/events');
			holds($calendarPage !== null && $calendarPage->intendedTemplate()->name() === 'koyomi-calendar', 'the configured calendar page resolves');
			expect(false, $calendarPage->isDraft(), 'the calendar page itself is no draft in Kirby\'s eyes');
			expect(null, Repository::calendar($kirby), 'a calendar below a draft container is not the calendar');
			expect(null, $kirby->site()->koyomiCalendar(), 'site()->koyomiCalendar() is null for it too');

			$kirby = KoyomiFixture::app($archiveRoot);
			$event = $kirby->page('_drafts/archive/events/buried') ?? $kirby->page('archive/events/buried');
			holds($event !== null && $event->intendedTemplate()->name() === 'koyomi-event', 'the event below the draft container resolves');
			expect(false, $event->isDraft(), 'the event itself is no draft in Kirby\'s eyes');
			throws(NotFoundException::class, fn () => Output::eventIcs($event), 'eventIcs() of an event below a draft container');
		};

		try {

			$partOne();
			$partTwo();

		} finally {

			KoyomiFixture::remove($root);
			KoyomiFixture::remove($archiveRoot);

		}
	}
}

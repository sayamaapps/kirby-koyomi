<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Koyomi\Attendance;
use Sayamaapps\Koyomi\Event;
use Sayamaapps\Koyomi\Organizer;
use Sayamaapps\Koyomi\Query;
use Sayamaapps\Koyomi\Venue;

/**
 * Query: upcoming/past, filters, sort, paging, facets, venue and organizer
 * lists. Kirby-free.
 */
#[CoversClass(Query::class)]
final class QueryTest extends TestCase
{
	public function testSuite(): void
	{
		koyomi_require_core();


		$tz = new DateTimeZone('Europe/Berlin');

		$now = new DateTimeImmutable('2027-03-10 12:00', $tz);


		/** An event from raw content; $o overrides koyomi_raw_event() defaults (2027-03-12 18:00–20:00). */
		$make = fn (array $o): Event => Event::fromArray(koyomi_raw_event($o));

		$ids = fn (array $events): array => array_map(fn (Event $e) => $e->id, $events);

		try {

			$venue = koyomi_raw_venue();
			$organizer = ['id' => 'org-anna', 'name' => 'Anna Beispiel', 'kind' => 'person'];

			// 1. Upcoming and past around the clock 2027-03-10 12:00.
			$ended = $make(['id' => 'ended', 'startDate' => '2027-03-01', 'startTime' => '18:00', 'endTime' => '20:00']);
			$boundary = $make(['id' => 'boundary', 'startDate' => '2027-03-10', 'startTime' => '10:00', 'endTime' => '12:00']);
			$ongoing = $make(['id' => 'ongoing', 'startDate' => '2027-03-10', 'startTime' => '11:00', 'endTime' => '13:00']);
			$future = $make(['id' => 'future', 'startDate' => '2027-03-12', 'startTime' => '18:00', 'endTime' => '20:00']);
			$cancelled = $make(['id' => 'cancelled', 'startDate' => '2027-03-13', 'startTime' => '18:00', 'status' => 'cancelled']);
			$all = [$future, $cancelled, $ended, $boundary, $ongoing];

			expect(['ongoing', 'future', 'cancelled'], $ids(Query::upcoming($all, $now)), 'upcoming: end == now is past, ongoing kept, sorted by start');
			expect(['ongoing', 'future'], $ids(Query::upcoming($all, $now, [], true)), 'upcoming: hideCancelled removes cancelled');
			expect(['boundary', 'ended'], $ids(Query::past($all, $now)), 'past: end <= now, newest first');
			expect(['ended', 'boundary'], $ids(array_reverse(Query::past($all, $now))), 'past: reversed, oldest first');
			holds($boundary->isPast($now) && $ended->isPast($now) && !$ongoing->isPast($now), 'isPast(): end == now counts as past');

			// 2. Sort: start, end, lower-case title, id; desc reverses the whole comparison.
			$a = $make(['id' => 'x', 'title' => 'beta', 'startDate' => '2027-03-12', 'startTime' => '18:00', 'endTime' => '20:00']);
			$b = $make(['id' => 'z', 'title' => 'Alpha', 'startDate' => '2027-03-12', 'startTime' => '18:00', 'endTime' => '20:00']);
			$c = $make(['id' => 'a', 'title' => 'alpha', 'startDate' => '2027-03-12', 'startTime' => '18:00', 'endTime' => '20:00']);
			$d = $make(['id' => 'd', 'title' => 'zz', 'startDate' => '2027-03-12', 'startTime' => '18:00', 'endTime' => '19:00']);
			$earlier = $make(['id' => 'early', 'startDate' => '2027-03-12', 'startTime' => '09:00', 'endTime' => '10:00']);
			expect(['d', 'a', 'z', 'x'], $ids(Query::sort([$a, $b, $c, $d])), 'sort: end first, then title case-insensitively, then id');
			expect(['d', 'a', 'z', 'x'], $ids(Query::sort([$d, $c, $b, $a])), 'sort does not depend on the input order');
			expect(['x', 'z', 'a', 'd'], $ids(Query::sort([$a, $b, $c, $d], 'desc')), 'sort desc reverses the whole comparison');
			expect(['early', 'd', 'a', 'z', 'x'], $ids(Query::sort([$a, $b, $c, $d, $earlier])), 'sort: start beats end and title');

			// 3. Input arrays are never modified.
			$before = $ids($all);
			Query::upcoming($all, $now, ['category' => 'x'], true);
			Query::sort($all, 'desc');
			Query::filter($all, ['featured' => true]);
			expect($before, $ids($all), 'input list unchanged by upcoming/sort/filter');

			// 4. Filters: category, attendance, language, featured, combined.
			$retreat = $make(['id' => 'retreat', 'categories' => 'Retreat, Meditation', 'languages' => 'de, cs', 'attendance' => 'mixed', 'featured' => 'true', 'venue' => $venue]);
			$online = $make(['id' => 'online', 'categories' => 'Meditation', 'languages' => 'en', 'attendance' => 'online']);
			$offline = $make(['id' => 'offline', 'categories' => 'workshop', 'languages' => 'de', 'attendance' => 'offline', 'venue' => $venue]);
			$moved = $make(['id' => 'moved', 'categories' => 'Retreat', 'languages' => 'de', 'attendance' => 'offline', 'status' => 'movedOnline', 'venue' => $venue]);
			$catalog = [$retreat, $online, $offline, $moved];

			expect(['retreat', 'moved'], $ids(Query::filter($catalog, ['category' => 'retreat'])), 'category: case-insensitive equality');
			expect(['retreat', 'online'], $ids(Query::filter($catalog, ['category' => 'MEDITATION'])), 'category: Meditation matches both');
			expect([], $ids(Query::filter($catalog, ['category' => 'Retreats'])), 'category: no substring match');
			expect(['retreat', 'online', 'moved'], $ids(Query::filter($catalog, ['attendance' => 'online'])), 'attendance online: online, mixed and movedOnline');
			expect(['retreat', 'offline'], $ids(Query::filter($catalog, ['attendance' => 'offline'])), 'attendance offline: in person (a mixed event counts, movedOnline does not)');
			expect(['retreat'], $ids(Query::filter($catalog, ['attendance' => 'mixed'])), 'attendance mixed: effective mixed only');
			expect(['retreat'], $ids(Query::filter($catalog, ['language' => 'cs'])), 'language: must be one of the languages');
			expect(['retreat', 'offline', 'moved'], $ids(Query::filter($catalog, ['language' => 'de'])), 'language de');
			expect(['retreat'], $ids(Query::filter($catalog, ['featured' => true])), 'featured true: featured events only');
			expect(['retreat'], $ids(Query::filter($catalog, ['category' => 'Meditation', 'language' => 'cs', 'attendance' => 'mixed', 'featured' => true])), 'all set filters must match');
			expect(['retreat', 'online', 'offline', 'moved'], $ids(Query::filter($catalog, [])), 'no filter keeps everything');
			expect(['retreat', 'online', 'offline', 'moved'], $ids(Query::filter($catalog, ['category' => ''])), 'empty category is no filter');
			expect(['retreat', 'moved'], $ids(Query::filter($catalog, ['category' => 'Retreat', 'view' => 'past', 'page' => 4])), 'view and page are ignored by filter()');

			// 5. Facets over unfiltered events.
			$facetEvents = [
				$make(['id' => 'f1', 'categories' => 'Retreat, Meditation', 'languages' => 'de, cs', 'attendance' => 'mixed', 'featured' => 'true']),
				$make(['id' => 'f2', 'categories' => 'meditation', 'languages' => 'de', 'attendance' => 'offline']),
				$make(['id' => 'f3', 'categories' => 'Meditation', 'languages' => 'de', 'attendance' => 'online']),
				$make(['id' => 'f4', 'categories' => 'Retreat', 'languages' => 'de', 'attendance' => 'offline', 'status' => 'movedOnline']),
			];
			expect([
				'categories' => ['Meditation' => 3, 'Retreat' => 2],
				'languages' => ['cs' => 1, 'de' => 4],
				'attendance' => ['offline' => 2, 'online' => 3, 'mixed' => 1],
				'featured' => 1,
			], Query::facets($facetEvents), 'facets: counts, first spelling wins, attendance per filter semantics');
			expect([
				'categories' => [],
				'languages' => [],
				'attendance' => ['offline' => 0, 'online' => 0, 'mixed' => 0],
				'featured' => 0,
			], Query::facets([]), 'facets of nothing: all keys present, zero');
			$naturalSort = [
				$make(['id' => 'n1', 'categories' => 'Retreat 10']),
				$make(['id' => 'n2', 'categories' => 'Retreat 2']),
				$make(['id' => 'n3', 'categories' => 'alpha']),
			];
			expect(['alpha', 'Retreat 2', 'Retreat 10'], array_keys(Query::facets($naturalSort)['categories']), 'facets: categories sorted with strnatcasecmp');
			$languageSort = [$make(['id' => 'l1', 'languages' => 'de']), $make(['id' => 'l2', 'languages' => 'cs'])];
			expect(['cs', 'de'], array_keys(Query::facets($languageSort)['languages']), 'facets: languages sorted by code');

			// 6. Paging.
			$items = [1, 2, 3, 4, 5, 6, 7];
			$p = Query::paginate($items, 2, 3);
			expect([4, 5, 6], $p['items'], 'paginate: page 2 of 3 per page');
			expect([2, 3, 7, 3, true, true], [$p['page'], $p['pages'], $p['total'], $p['perPage'], $p['hasPrev'], $p['hasNext']], 'paginate: numbers and flags');
			$last = Query::paginate($items, 9, 3);
			expect([7], $last['items'], 'paginate: page 9 clamped to the last page');
			expect([3, 3, true, false], [$last['page'], $last['pages'], $last['hasPrev'], $last['hasNext']], 'paginate: clamped numbers and flags');
			$none = Query::paginate([], 1, 12);
			expect([1, 1, 0, [], false, false], [$none['page'], $none['pages'], $none['total'], $none['items'], $none['hasPrev'], $none['hasNext']], 'paginate: nothing gives one empty page');
			expect([1, 2], [Query::paginate([1, 2], 0, 12)['page'], Query::paginate([1, 2], 1, 1)['pages']], 'paginate: page below 1 is 1');

			// 7. Venue and organizer lists.
			$beispielhof = $make(['id' => 'at-venue', 'venue' => $venue]);
			$stadthalle = $make(['id' => 'other-venue', 'venue' => koyomi_raw_venue(['id' => 'venue-stadthalle', 'name' => 'Stadthalle'])]);
			$movedAway = $make(['id' => 'moved-away', 'venue' => $venue, 'status' => 'movedOnline']);
			$noVenue = $make(['id' => 'no-venue']);
			$venueList = [$beispielhof, $stadthalle, $movedAway, $noVenue];
			expect(['at-venue'], $ids(Query::atVenue($venueList, 'venue-beispielhof')), 'atVenue: physical venue only, moved online excluded');
			expect([], $ids(Query::atVenue($venueList, 'venue-unknown')), 'atVenue: unknown id gives nothing');

			$withOrganizer = $make(['id' => 'with-org', 'organizers' => [$organizer]]);
			$withoutOrganizer = $make(['id' => 'without-org']);
			expect(['with-org'], $ids(Query::byOrganizer([$withOrganizer, $withoutOrganizer], 'org-anna')), 'byOrganizer: matched by id');
			expect([], $ids(Query::byOrganizer([$withOrganizer, $withoutOrganizer], 'org-sayama')), 'byOrganizer: other id gives nothing');
			holds(Organizer::fromArray($organizer)->id === 'org-anna', 'organizer value object accepted by the list fixture');
			holds(Venue::fromArray($venue)->id === 'venue-beispielhof', 'venue value object accepted by the list fixture');
			holds(Attendance::fromToggles(false, false) === Attendance::OFFLINE, 'both toggles off counts as offline');

		} finally {

		}
	}
}

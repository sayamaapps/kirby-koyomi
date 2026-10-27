<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Koyomi\Filters;

/**
 * Filters: query string in and out, URL building, activity. Kirby-free.
 * Every documented example is a check.
 */
#[CoversClass(Filters::class)]
final class FiltersTest extends TestCase
{
	public function testSuite(): void
	{
		koyomi_require_core();


		$defaults = ['category' => null, 'attendance' => null, 'language' => null, 'featured' => false, 'view' => 'upcoming', 'page' => 1];

		$retreatPage3 = ['category' => 'Retreat', 'attendance' => null, 'language' => null, 'featured' => false, 'view' => 'upcoming', 'page' => 3];

		try {

			// fromQuery(): the documented example.
			expect(
				['category' => 'Retreat', 'attendance' => null, 'language' => 'cs', 'featured' => true, 'view' => 'upcoming', 'page' => 3],
				Filters::fromQuery(['category' => ' Retreat ', 'attendance' => 'ONLINE', 'language' => 'CS', 'featured' => 'true', 'page' => '3', 'x' => 'y'], ['de', 'cs']),
				'fromQuery: spec example (attendance is case-sensitive, unknown keys ignored)'
			);
			expect(array_keys($defaults), array_keys(Filters::fromQuery([])), 'fromQuery always has exactly the six keys');
			expect($defaults, Filters::fromQuery([]), 'empty query gives the defaults');
			expect($defaults, Filters::fromQuery(['category' => ['Retreat'], 'attendance' => ['online'], 'language' => ['de'], 'featured' => [true], 'view' => ['past'], 'page' => [3]]), 'array values are rejected');
			expect($defaults, Filters::fromQuery(['x' => 'y'], []), 'unknown keys only');

			// category: trimmed, at most 80 characters, empty → null.
			expect(null, Filters::fromQuery(['category' => '   '])['category'], 'category: whitespace only is null');
			expect(null, Filters::fromQuery(['category' => ''])['category'], 'category: empty string is null');
			expect(str_repeat('ä', 80), Filters::fromQuery(['category' => str_repeat('ä', 100)])['category'], 'category: cut to 80 characters, not bytes');
			expect('x', Filters::fromQuery(['category' => ' x '])['category'], 'category: trimmed on both sides');

			// attendance: exactly one of Attendance::ALL.
			expect(['offline', 'online', 'mixed'], array_map(fn ($v) => Filters::fromQuery(['attendance' => $v])['attendance'], ['offline', 'online', 'mixed']), 'attendance: the three values');
			expect(null, Filters::fromQuery(['attendance' => 'ONLINE'])['attendance'], 'attendance: case-sensitive');
			expect(null, Filters::fromQuery(['attendance' => 'hybrid'])['attendance'], 'attendance: unknown value is null');

			// language: first valid code; must be in the allowed list when one is given.
			expect('cs', Filters::fromQuery(['language' => 'CS'], ['de', 'cs'])['language'], 'language: lower-cased');
			expect(null, Filters::fromQuery(['language' => 'fr'], ['de', 'cs'])['language'], 'language: not in the allowed list');
			expect('fr', Filters::fromQuery(['language' => 'fr'], [])['language'], 'language: any valid code when no list is given');
			expect('pt-br', Filters::fromQuery(['language' => 'pt-br'])['language'], 'language: regional code');
			expect(null, Filters::fromQuery(['language' => 'not a code!'])['language'], 'language: invalid code is null');

			// featured: Normalize::bool.
			expect([true, true, true, true, false, false], array_map(fn ($v) => Filters::fromQuery(['featured' => $v])['featured'], ['1', 'true', 'yes', 'on', '0', 'no']), 'featured: truthy spellings');
			expect(false, Filters::fromQuery([])['featured'], 'featured: missing is false');

			// view: only the exact value "past" is past.
			expect(['past', 'upcoming', 'upcoming'], array_map(fn ($v) => Filters::fromQuery(['view' => $v])['view'], ['past', 'Past', 'future']), 'view: exact past, everything else upcoming');

			// page: positive integer, else 1; capped at 10000.
			expect([3, 1, 1, 1, 1, 10000, 10000], array_map(fn ($v) => Filters::fromQuery(['page' => $v])['page'], ['3', '0', 'abc', '-2', '1.5', '99999', 10000]), 'page: positive integer, else 1, capped');
			expect(1, Filters::fromQuery(['page' => '2x'])['page'], 'page: trailing text is not a number');

			// toQuery(): non-default values only, fixed key order.
			expect(
				['category' => 'Retreat', 'featured' => '1', 'view' => 'past'],
				Filters::toQuery(['category' => 'Retreat', 'featured' => true, 'view' => 'past', 'page' => 1] + $defaults),
				'toQuery: spec example'
			);
			expect(
				['category' => 'Retreat', 'attendance' => 'online', 'language' => 'cs', 'featured' => '1', 'view' => 'past', 'page' => '2'],
				Filters::toQuery(['page' => 2, 'view' => 'past', 'featured' => true, 'language' => 'cs', 'attendance' => 'online', 'category' => 'Retreat']),
				'toQuery: fixed key order whatever the input order'
			);
			expect([], Filters::toQuery($defaults), 'toQuery: defaults give nothing');

			// url(): filters kept, page reset by any other change, nothing left → base.
			expect('https://ex.test/events?category=Retreat&language=cs', Filters::url('https://ex.test/events', $retreatPage3, ['language' => 'cs']), 'url: spec example, page reset');
			expect('https://ex.test/events?category=Retreat&page=2', Filters::url('https://ex.test/events', $retreatPage3, ['page' => 2]), 'url: page change keeps filters');
			expect('https://ex.test/events', Filters::url('https://ex.test/events', $retreatPage3, ['category' => null]), 'url: null removes, nothing left → base');
			expect('https://ex.test/events?category=Retreat', Filters::url('https://ex.test/events', ['category' => 'Retreat'] + $defaults, []), 'url: no change');
			expect('https://ex.test/events?category=Retreat', Filters::url('https://ex.test/events', $retreatPage3, ['page' => 1]), 'url: page 1 is not written');
			expect('https://ex.test/events?category=Retreat&view=past', Filters::url('https://ex.test/events', $retreatPage3, ['view' => 'past']), 'url: view change');
			expect('https://ex.test/events', Filters::url('https://ex.test/events', $defaults, []), 'url: defaults give the base');
			expect('https://ex.test/events?category=Atem%20%26%20St%C3%BCtze', Filters::url('https://ex.test/events', ['category' => 'Atem & Stütze'] + $defaults), 'url: RFC 3986 encoding of spaces, ampersands and umlauts');

			// Round trip: parse the built URL's query and get the same filters back.
			$languages = ['de', 'cs'];
			foreach ([
				['category' => 'Atem & Stütze', 'attendance' => 'mixed', 'language' => 'cs', 'featured' => true, 'view' => 'past', 'page' => 4],
				['category' => 'Retreat', 'attendance' => null, 'language' => null, 'featured' => false, 'view' => 'upcoming', 'page' => 2],
			] as $filters) {
				$url = Filters::url('https://ex.test/events', $filters);
				parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
				expect($filters, Filters::fromQuery($query, $languages), 'round trip: fromQuery(parsed url) gives the filters back: ' . $url);
			}

			// isActive(): view and page do not count.
			expect([false, true, true, true, true, false], [
				Filters::isActive($defaults),
				Filters::isActive(['category' => 'Retreat'] + $defaults),
				Filters::isActive(['attendance' => 'online'] + $defaults),
				Filters::isActive(['language' => 'de'] + $defaults),
				Filters::isActive(['featured' => true] + $defaults),
				Filters::isActive(['view' => 'past', 'page' => 3] + $defaults),
			], 'isActive: category, attendance, language, featured count; view and page do not');

		} finally {

		}
	}
}

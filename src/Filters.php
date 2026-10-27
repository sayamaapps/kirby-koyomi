<?php

namespace Sayamaapps\Koyomi;

/**
 * List filters from and to the URL query string, Kirby-free. No JavaScript:
 * filters are plain links and a GET form.
 *
 * The normalized filter array (used by Query::filter(), Repository::list()
 * and the koyomi/filters snippet) ALWAYS has exactly these keys:
 *
 *   ['category' => ?string, 'attendance' => ?string, 'language' => ?string,
 *    'featured' => bool, 'view' => 'upcoming'|'past', 'page' => int]
 *
 * Query string parameters: ?category=Retreat&attendance=online&language=cs&featured=1&view=past&page=2
 *
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-koyomi
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class Filters
{
	public const EMPTY = ['category' => null, 'attendance' => null, 'language' => null, 'featured' => false, 'view' => 'upcoming', 'page' => 1];

	/** Longest category text kept from the query string. */
	public const MAX_CATEGORY = 80;

	/** Upper bound for the page parameter: anything above is a bot or a typo. */
	public const MAX_PAGE = 10000;

	/**
	 * @param array<string,mixed> $query e.g. $_GET or Kirby's request()->query()->toArray()
	 * @param list<string> $languages allowed language codes; [] = any valid code
	 * @return array{category:?string,attendance:?string,language:?string,featured:bool,view:string,page:int}
	 */
	public static function fromQuery(array $query, array $languages = []): array
	{
		// Array values are never valid for a filter: they are treated as absent.
		$scalar = fn (string $key): mixed => isset($query[$key]) && !is_array($query[$key]) ? $query[$key] : null;

		$category = $scalar('category');
		$category = $category === null ? '' : mb_substr(Normalize::string($category), 0, self::MAX_CATEGORY);

		$attendance = $scalar('attendance');
		$attendance = is_string($attendance) && in_array($attendance, Attendance::ALL, true) ? $attendance : null;

		$language = $scalar('language');
		$language = $language === null ? null : (Normalize::languages($language)[0] ?? null);
		if ($language !== null && $languages !== [] && !in_array($language, $languages, true)) {
			$language = null;
		}

		$page = $scalar('page');
		$page = $page === null ? null : Normalize::positiveInt($page);

		return [
			'category' => $category === '' ? null : $category,
			'attendance' => $attendance,
			'language' => $language,
			'featured' => Normalize::bool($scalar('featured')),
			'view' => $scalar('view') === 'past' ? 'past' : 'upcoming',
			'page' => min($page ?? 1, self::MAX_PAGE),
		];
	}

	/** True when category, attendance, language or featured is set (view and page do not count). */
	public static function isActive(array $filters): bool
	{
		$filters += self::EMPTY;
		return ($filters['category'] ?? null) !== null && $filters['category'] !== '' ||
			$filters['attendance'] !== null ||
			$filters['language'] !== null ||
			Normalize::bool($filters['featured']);
	}

	/**
	 * Only non-default values, fixed key order category, attendance,
	 * language, featured ("1"), view ("past"), page (when > 1).
	 *
	 * @return array<string,string>
	 */
	public static function toQuery(array $filters): array
	{
		$filters += self::EMPTY;
		$query = [];
		if (($filters['category'] ?? null) !== null && $filters['category'] !== '') {
			$query['category'] = (string)$filters['category'];
		}
		if ($filters['attendance'] !== null) {
			$query['attendance'] = (string)$filters['attendance'];
		}
		if ($filters['language'] !== null) {
			$query['language'] = (string)$filters['language'];
		}
		if (Normalize::bool($filters['featured'])) {
			$query['featured'] = '1';
		}
		if ($filters['view'] === 'past') {
			$query['view'] = 'past';
		}
		if ((int)$filters['page'] > 1) {
			$query['page'] = (string)(int)$filters['page'];
		}
		return $query;
	}

	/**
	 * $base + "?" + query of ($filters with $change applied), or $base alone
	 * when nothing is left. A null in $change removes that filter. Any change
	 * other than `page` resets page to 1. Encoding: http_build_query(…, '', '&', PHP_QUERY_RFC3986).
	 *   url('https://ex.test/events', $f, ['category' => 'Retreat']) → 'https://ex.test/events?category=Retreat'
	 */
	public static function url(string $base, array $filters, array $change = []): string
	{
		$next = $filters + self::EMPTY;
		$pageChange = null;
		foreach ($change as $key => $value) {
			if ($key === 'page') {
				$pageChange = $value;
				continue;
			}
			$next[$key] = $value;
			$next['page'] = 1;
		}
		if ($pageChange !== null) {
			$next['page'] = $pageChange;
		}

		// Round trip through the parser: values end up in their canonical form.
		$query = self::toQuery(self::fromQuery(self::toQuery($next)));
		if ($query === []) {
			return $base;
		}
		return $base . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
	}
}

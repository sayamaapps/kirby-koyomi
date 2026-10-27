<?php

namespace Sayamaapps\Koyomi;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * One event, Kirby-free and read-only. Every output (snippets, ICS, JSON-LD,
 * RSS, JSON) is built from this object, never from the page directly.
 *
 * PRIVACY: there is deliberately no `onlineUrl` here. The meeting link lives
 * only in the content file and the Panel; since no output ever sees it, no
 * output can leak it. fromArray() ignores an `onlineUrl` key.
 *
 * Time model:
 * - `start` and `end` are instants in the event's own zone.
 * - `end` is EXCLUSIVE. For all-day events it is 00:00 of the day AFTER the
 *   last day (a retreat on 12.–14. ends at 15. 00:00), like ICS DTEND.
 * - Past = end <= now. An ongoing retreat (start <= now < end) is still
 *   listed as upcoming.
 *
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-koyomi
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class Event
{
	/** Fallbacks for fromArray(); Options::eventDefaults() supplies the configured ones. */
	public const DEFAULTS = ['timezone' => Time::DEFAULT_ZONE, 'currency' => 'EUR', 'defaultDuration' => 120];

	/**
	 * The series of the event, sorted by start; it includes this event (current = true) whenever it is not empty.
	 *
	 * @param list<Organizer> $organizers
	 * @param list<string> $categories
	 * @param list<string> $languages lower-case codes ("de", "cs")
	 * @param list<array{id:string,title:string,url:string,start:DateTimeImmutable,allDay:bool,part:int,current:bool}> $series
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $title,
		public readonly string $url,
		public readonly DateTimeImmutable $start,
		public readonly DateTimeImmutable $end,
		public readonly bool $allDay,
		public readonly bool $hasEndTime,
		public readonly string $timezone,
		public readonly string $description = '',
		public readonly string $image = '',
		public readonly string $imageAlt = '',
		public readonly string $attendance = Attendance::OFFLINE,
		public readonly Venue|null $venue = null,
		public readonly string $onlinePlatform = '',
		public readonly string $status = Status::SCHEDULED,
		public readonly DateTimeImmutable|null $previousStart = null,
		public readonly string $statusNote = '',
		public readonly bool $isFree = false,
		public readonly float|null $price = null,
		public readonly string $currency = 'EUR',
		public readonly string $priceNote = '',
		public readonly string $availability = Availability::AVAILABLE,
		public readonly string $bookingUrl = '',
		public readonly string $bookingLabel = '',
		public readonly array $organizers = [],
		public readonly array $categories = [],
		public readonly array $languages = [],
		public readonly bool $featured = false,
		public readonly array $series = [],
		public readonly int|null $seriesPart = null,
		public readonly int $modified = 0,
		/**
		 * True when the content gives an end: an end time, or an end date after
		 * the start date. False means `end` is the default duration (or the
		 * all-day fallback), which says nothing about the last day, so the event
		 * is shown as a single day even when the default duration crosses midnight.
		 */
		public readonly bool $hasEndDate = false,
	) {
	}

	/** `online` when the status is movedOnline, else the stored attendance. */
	public function effectiveAttendance(): string
	{
		return $this->status === Status::MOVED_ONLINE ? Attendance::ONLINE : $this->attendance;
	}

	/** 00:00 of the first day, event zone. */
	public function firstDay(): DateTimeImmutable
	{
		return $this->start->setTime(0, 0);
	}

	/**
	 * Builds an event from the raw content contract: all values
	 * may be strings as Kirby stores them.
	 *
	 *   Event::fromArray([
	 *     'id' => 'evt-retreat', 'title' => 'Retreat', 'url' => 'https://example.test/events/retreat',
	 *     'startDate' => '2027-03-12', 'startTime' => '18:00', 'endDate' => '2027-03-14', 'endTime' => '13:00',
	 *     'allDay' => 'false', 'timezone' => 'Europe/Berlin',
	 *     'description' => 'Three days of silence.', 'image' => 'https://…/cover.jpg', 'imageAlt' => '…',
	 *     'attendance' => 'mixed', 'venue' => Venue|array|null, 'onlinePlatform' => 'Zoom',
	 *     'status' => 'rescheduled', 'previousStart' => '2027-02-20 18:00', 'statusNote' => '…',
	 *     'isFree' => 'false', 'price' => '380', 'currency' => 'EUR', 'priceNote' => 'Early bird until 1 Feb',
	 *     'availability' => 'limited', 'bookingUrl' => 'https://…', 'bookingLabel' => '',
	 *     'organizers' => [Organizer|array, …], 'categories' => 'Retreat, Meditation', 'languages' => 'de, cs',
	 *     'featured' => 'true', 'series' => [[id, title, url, startDate, startTime, allDay, timezone?, part?], …],
	 *     'seriesPart' => '1', 'modified' => 1791000000,
	 *   ], ['timezone' => 'Europe/Berlin', 'currency' => 'EUR', 'defaultDuration' => 120]);
	 *
	 * Rules:
	 * - timezone: valid IANA name, else $defaults['timezone'].
	 * - allDay: the toggle, OR an empty/invalid startTime.
	 * - start: startDate (+ startTime unless allDay). Invalid startDate → InvalidArgumentException.
	 * - endDate: empty/invalid → startDate.
	 * - all-day end: endDate + 1 day at 00:00; if that is not after start, start + 1 day. hasEndTime = false.
	 * - timed end: endDate + endTime. If endDate was EMPTY and endTime < startTime, the end is on the
	 *   next day (concert 20:00–01:00). No endTime: endDate > startDate → 00:00 after endDate,
	 *   else start + defaultDuration minutes (elapsed, across DST too); hasEndTime = false. An end not after start → start +
	 *   defaultDuration, hasEndTime = false.
	 * - price: isFree → null; else Normalize::amount(). currency: /^[A-Z]{3}$/ after upper-casing, else default.
	 * - previousStart: parsed in the event zone, kept only when status is `rescheduled`.
	 * - url, bookingUrl, image: http(s) only, else "".
	 * - series: [] stays []; otherwise the given items plus this event (if its id is missing), each
	 *   normalized, sorted by start (then title); `part` = the item's own part, else its 1-based position;
	 *   seriesPart = $data['seriesPart'] if positive, else this event's position.
	 *
	 * @param array{timezone?:string,currency?:string,defaultDuration?:int} $defaults
	 * @throws InvalidArgumentException when startDate is not a valid date
	 */
	public static function fromArray(array $data, array $defaults = []): self
	{
		$defaults += self::DEFAULTS;
		$id = Normalize::string($data['id'] ?? '');
		$zone = Time::zone(Normalize::string($data['timezone'] ?? ''), (string)$defaults['timezone']);

		$startDate = Normalize::string($data['startDate'] ?? '');
		$startTime = Normalize::string($data['startTime'] ?? '');
		$endDateRaw = Normalize::string($data['endDate'] ?? '');
		$endTime = Normalize::string($data['endTime'] ?? '');

		$allDay = Normalize::bool($data['allDay'] ?? false) || Time::wall($startDate, $startTime, $zone) === null || $startTime === '';
		$start = Time::wall($startDate, $allDay ? '' : $startTime, $zone);
		if ($start === null) {
			throw new InvalidArgumentException('Koyomi: event "' . $id . '" has no valid start date');
		}
		[$end, $hasEndTime, $hasEndDate, $endDateGiven] = self::span(
			$start,
			$allDay,
			$startDate,
			$endDateRaw,
			$endTime,
			$zone,
			(int)$defaults['defaultDuration'],
		);

		$status = Status::normalize($data['status'] ?? '');
		$previousStart = $status === Status::RESCHEDULED
			? Time::parse(Normalize::string($data['previousStart'] ?? ''), $zone)
			: null;

		$isFree = Normalize::bool($data['isFree'] ?? false);
		$currency = strtoupper(Normalize::string($data['currency'] ?? ''));
		if (!preg_match('/^[A-Z]{3}$/', $currency)) {
			$currency = strtoupper((string)$defaults['currency']);
		}

		$venue = $data['venue'] ?? null;
		if (is_array($venue)) {
			$venue = Venue::fromArray($venue);
		}
		$organizers = [];
		foreach ((array)($data['organizers'] ?? []) as $organizer) {
			if (is_array($organizer)) {
				$organizer = Organizer::fromArray($organizer);
			}
			if ($organizer instanceof Organizer) {
				$organizers[] = $organizer;
			}
		}

		$title = Normalize::string($data['title'] ?? '');
		$url = Normalize::url($data['url'] ?? '');
		[$series, $seriesPart] = self::series(
			(array)($data['series'] ?? []),
			['id' => $id, 'title' => $title, 'url' => $url, 'start' => $start, 'allDay' => $allDay],
			Normalize::positiveInt($data['seriesPart'] ?? null),
			$zone,
			(string)$defaults['timezone'],
		);

		return new self(
			id: $id,
			title: $title,
			url: $url,
			start: $start,
			end: $end,
			allDay: $allDay,
			hasEndTime: $hasEndTime,
			timezone: $zone->getName(),
			description: Normalize::string($data['description'] ?? ''),
			image: Normalize::url($data['image'] ?? ''),
			imageAlt: Normalize::string($data['imageAlt'] ?? ''),
			attendance: Attendance::normalize($data['attendance'] ?? ''),
			venue: $venue instanceof Venue ? $venue : null,
			onlinePlatform: Normalize::string($data['onlinePlatform'] ?? ''),
			status: $status,
			previousStart: $previousStart,
			statusNote: Normalize::string($data['statusNote'] ?? ''),
			isFree: $isFree,
			price: $isFree ? null : Normalize::amount($data['price'] ?? null),
			currency: $currency,
			priceNote: Normalize::string($data['priceNote'] ?? ''),
			availability: Availability::normalize($data['availability'] ?? ''),
			bookingUrl: Normalize::url($data['bookingUrl'] ?? ''),
			bookingLabel: Normalize::string($data['bookingLabel'] ?? ''),
			organizers: $organizers,
			categories: Normalize::list($data['categories'] ?? []),
			languages: Normalize::languages($data['languages'] ?? []),
			featured: Normalize::bool($data['featured'] ?? false),
			series: $series,
			seriesPart: $seriesPart,
			modified: max(0, (int)($data['modified'] ?? 0)),
			hasEndDate: $allDay ? $endDateGiven : $hasEndDate,
		);
	}

	/** url + ".ics" (Kirby content representation of the event page), or "" without url. */
	public function icsUrl(): string
	{
		return $this->url === '' ? '' : $this->url . '.ics';
	}

	public function isCancelled(): bool
	{
		return $this->status === Status::CANCELLED;
	}

	/** Can be attended in person (offline or mixed, not moved online). */
	public function isInPerson(): bool
	{
		return in_array($this->effectiveAttendance(), [Attendance::OFFLINE, Attendance::MIXED], true);
	}

	public function isMultiDay(): bool
	{
		return $this->firstDay()->format('Y-m-d') !== $this->lastDay()->format('Y-m-d');
	}

	public function isOngoing(DateTimeImmutable $now): bool
	{
		return $this->start <= $now && $now < $this->end;
	}

	/** Can be attended online (online, mixed or moved online). */
	public function isOnline(): bool
	{
		return in_array($this->effectiveAttendance(), [Attendance::ONLINE, Attendance::MIXED], true);
	}

	public function isPast(DateTimeImmutable $now): bool
	{
		return $this->end <= $now;
	}

	public function isUpcoming(DateTimeImmutable $now): bool
	{
		return $this->start > $now;
	}

	/**
	 * 00:00 of the last day the event touches (INCLUSIVE), event zone.
	 * All-day: end − 1 day. Timed: the day of (end − 1 second), so a concert
	 * 20:00–00:00 stays a one-day event. Timed without an end given
	 * (hasEndDate false): the start day, because the default duration is not
	 * an end the organizer chose — a 23:00 concert is not a two-day event.
	 */
	public function lastDay(): DateTimeImmutable
	{
		if ($this->allDay) {
			return $this->end->modify('-1 day')->setTime(0, 0);
		}
		if (!$this->hasEndDate) {
			return $this->start->setTime(0, 0);
		}
		$last = $this->end > $this->start ? $this->end->modify('-1 second') : $this->end;
		return $last->setTime(0, 0);
	}

	/** The venue when the event takes place in person, else null. */
	public function physicalVenue(): Venue|null
	{
		return $this->isInPerson() ? $this->venue : null;
	}

	/**
	 * @return array{0: list<array{allDay: bool, current: bool, id: string, part: int, start: DateTimeImmutable, title: string, url: string}>, 1: int|null}
	 */
	private static function series(array $items, array $self, int|null $ownPart, \DateTimeZone $zone, string $fallbackZone): array
	{
		if ($items === []) {
			return [[], null];
		}
		$list = [];
		$seen = [];
		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}
			$itemId = Normalize::string($item['id'] ?? '');
			if ($itemId === '' || isset($seen[$itemId])) {
				continue;
			}
			if ($itemId === $self['id']) {
				continue; // added below with the authoritative values
			}
			$itemZone = Time::zone(Normalize::string($item['timezone'] ?? ''), $fallbackZone);
			$itemAllDay = Normalize::bool($item['allDay'] ?? false) || Normalize::string($item['startTime'] ?? '') === '';
			$itemStart = $item['start'] ?? null;
			if (!$itemStart instanceof DateTimeImmutable) {
				$itemStart = Time::wall(Normalize::string($item['startDate'] ?? ''), $itemAllDay ? '' : Normalize::string($item['startTime'] ?? ''), $itemZone);
			}
			if ($itemStart === null) {
				continue;
			}
			$seen[$itemId] = true;
			$list[] = [
				'id' => $itemId,
				'title' => Normalize::string($item['title'] ?? ''),
				'url' => Normalize::url($item['url'] ?? ''),
				'start' => $itemStart,
				'allDay' => $itemAllDay,
				'part' => Normalize::positiveInt($item['part'] ?? null),
				'current' => false,
			];
		}
		if ($list === []) {
			return [[], null];
		}
		$list[] = $self + ['part' => $ownPart, 'current' => true];
		usort($list, fn ($a, $b) => [$a['start']->getTimestamp(), $a['title'], $a['id']] <=> [$b['start']->getTimestamp(), $b['title'], $b['id']]);
		$position = null;
		foreach ($list as $index => $item) {
			$list[$index]['part'] = $item['part'] ?? $index + 1;
			if ($item['current']) {
				$position = $index + 1;
			}
		}
		/** @var list<array{allDay: bool, current: bool, id: string, part: int, start: DateTimeImmutable, title: string, url: string}> $list */
		return [$list, $ownPart ?? $position];
	}

	public function seriesTotal(): int
	{
		return count($this->series);
	}

	/**
	 * End of an event and the flags for its end date and end time. An all-day
	 * event ends at 00:00 of the day after its last day; a timed event without an
	 * end, or with an end that is not after its start, lasts $defaultDuration minutes.
	 *
	 * @return array{0: DateTimeImmutable, 1: bool, 2: bool, 3: bool} end, hasEndTime, hasEndDate, endDateGiven
	 */
	private static function span(
		DateTimeImmutable $start,
		bool $allDay,
		string $startDate,
		string $endDateRaw,
		string $endTime,
		\DateTimeZone $zone,
		int $defaultDuration,
	): array {
		// Elapsed minutes: modify() adds wall-clock minutes, an hour off across a DST change.
		$length = new DateInterval('PT' . max(0, $defaultDuration) . 'M');

		$endDateGiven = Time::wall($endDateRaw, '', $zone) !== null;
		$endDate = $endDateGiven ? substr($endDateRaw, 0, 10) : substr($startDate, 0, 10);

		$hasEndTime = false;
		$hasEndDate = false;
		if ($allDay) {
			/** @psalm-suppress PossiblyNullReference The date was checked before (see the start and end date checks). */
			$end = Time::wall($endDate, '', $zone)->modify('+1 day');
			if ($end <= $start) {
				$end = $start->modify('+1 day');
			}
		} else {
			$end = $endTime !== '' ? Time::wall($endDate, $endTime, $zone) : null;
			if ($end !== null) {
				$hasEndTime = true;
				$hasEndDate = true;
				if (!$endDateGiven && $end < $start) {
					// Overnight: the next day's wall time, not modify(), which
					// would take the other offset inside the autumn DST overlap.
					/** @psalm-suppress PossiblyNullReference The date was checked before (see the start and end date checks). */
					$nextDay = Time::wall($endDate, '', $zone)->modify('+1 day')->format('Y-m-d');
					$end = Time::wall($nextDay, $endTime, $zone);
				}
			} elseif ($endDateGiven && $endDate > substr($startDate, 0, 10)) {
				/** @psalm-suppress PossiblyNullReference The date was checked before (see the start and end date checks). */
				$end = Time::wall($endDate, '', $zone)->modify('+1 day');
				$hasEndDate = true;
			} else {
				$end = $start->add($length);
			}
			if ($hasEndTime && $end <= $start) {
				$end = $start->add($length);
				$hasEndTime = false;
				$hasEndDate = false;
			}
		}


		// Every branch above sets the end, so it is never null here.
		/** @var DateTimeImmutable $end */
		return [$end, $hasEndTime, $hasEndDate, $endDateGiven];
	}

	/**
	 * The public array — the shape of the JSON output and the base of
	 * $page->koyomiData(). Contains NO onlineUrl and no hidden email (they
	 * are not in this object).
	 *
	 * All-day events give `start`/`end` as dates, and `end` stays EXCLUSIVE
	 * (retreat 12.–14. → 'end' => '2027-03-15'); `lastDay` is the inclusive
	 * last day for display.
	 *
	 *   ['id' => 'evt-retreat', 'title' => 'Retreat', 'url' => 'https://…/events/retreat',
	 *    'start' => '2027-03-12T18:00:00+01:00', 'end' => '2027-03-14T13:00:00+01:00',
	 *    'firstDay' => '2027-03-12', 'lastDay' => '2027-03-14',
	 *    'allDay' => false, 'hasEndTime' => true, 'multiDay' => true, 'timezone' => 'Europe/Berlin',
	 *    'description' => '…', 'image' => 'https://…'|'', 'imageAlt' => '…',
	 *    'attendance' => 'mixed', 'effectiveAttendance' => 'mixed', 'onlinePlatform' => 'Zoom',
	 *    'venue' => Venue::toArray()|null   (null unless the event is in person),
	 *    'status' => 'scheduled', 'previousStart' => '2027-02-20T18:00:00+01:00'|null, 'statusNote' => '',
	 *    'isFree' => false, 'price' => 380.0|null, 'currency' => 'EUR', 'priceNote' => '…',
	 *    'availability' => 'limited', 'bookingUrl' => 'https://…'|'', 'bookingLabel' => '',
	 *    'organizers' => [Organizer::toArray(), …], 'categories' => ['Retreat', 'Meditation'],
	 *    'languages' => ['de', 'cs'], 'featured' => true,
	 *    'series' => [['id' => …, 'title' => …, 'url' => …, 'start' => ISO, 'allDay' => false, 'part' => 1, 'current' => true], …],
	 *    'seriesPart' => 1|null, 'seriesTotal' => 2|0, 'icsUrl' => 'https://…/events/retreat.ics', 'modified' => 1791000000]
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->id,
			'title' => $this->title,
			'url' => $this->url,
			'start' => $this->allDay ? Time::date($this->start) : Time::iso($this->start),
			'end' => $this->allDay ? Time::date($this->end) : Time::iso($this->end),
			'firstDay' => Time::date($this->firstDay()),
			'lastDay' => Time::date($this->lastDay()),
			'allDay' => $this->allDay,
			'hasEndTime' => $this->hasEndTime,
			'multiDay' => $this->isMultiDay(),
			'timezone' => $this->timezone,
			'description' => $this->description,
			'image' => $this->image,
			'imageAlt' => $this->imageAlt,
			'attendance' => $this->attendance,
			'effectiveAttendance' => $this->effectiveAttendance(),
			'onlinePlatform' => $this->onlinePlatform,
			'venue' => $this->physicalVenue()?->toArray(),
			'status' => $this->status,
			'previousStart' => $this->previousStart === null ? null : Time::iso($this->previousStart),
			'statusNote' => $this->statusNote,
			'isFree' => $this->isFree,
			'price' => $this->price,
			'currency' => $this->currency,
			'priceNote' => $this->priceNote,
			'availability' => $this->availability,
			'bookingUrl' => $this->bookingUrl,
			'bookingLabel' => $this->bookingLabel,
			'organizers' => array_map(fn (Organizer $o) => $o->toArray(), $this->organizers),
			'categories' => $this->categories,
			'languages' => $this->languages,
			'featured' => $this->featured,
			'series' => array_map(fn (array $s) => ['start' => Time::iso($s['start'])] + $s, $this->series),
			'seriesPart' => $this->seriesPart,
			'seriesTotal' => $this->seriesTotal(),
			'icsUrl' => $this->icsUrl(),
			'modified' => $this->modified,
		];
	}
}

<?php

namespace Koyomi;

use DateTimeImmutable;

/**
 * Filtering, sorting, paging of Event lists, Kirby-free.   OWNER: task `kirby`
 *
 * Contract: SPEC.md §8.8. All methods are pure: arrays in, arrays out, the
 * input is never modified.
 */
final class Query
{
    /**
     * Not past (end > now), sorted ascending (sort()). Ongoing events stay.
     * $hideCancelled removes status cancelled. $filters as Filters::EMPTY
     * (missing keys = not set).
     *
     * @param list<Event> $events
     * @return list<Event>
     */
    public static function upcoming(array $events, DateTimeImmutable $now, array $filters = [], bool $hideCancelled = false): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /**
     * Past (end <= now), newest first (sort($events, 'desc')).
     *
     * @param list<Event> $events
     * @return list<Event>
     */
    public static function past(array $events, DateTimeImmutable $now, array $filters = []): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /**
     * Keeps events matching ALL set filters:
     * - category: case-insensitive equality with one of the event's categories
     * - attendance 'online': isOnline(); 'offline': isInPerson(); 'mixed': effectiveAttendance() === 'mixed'
     * - language: in the event's languages
     * - featured true: featured events only
     * (`view` and `page` are ignored here.)
     *
     * @param list<Event> $events
     * @return list<Event>
     */
    public static function filter(array $events, array $filters): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /**
     * Stable order by start, then end, then mb_strtolower(title), then id.
     * 'desc' reverses the whole comparison.
     *
     * @param list<Event> $events
     * @return list<Event>
     */
    public static function sort(array $events, string $direction = 'asc'): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /**
     * @template T
     * @param list<T> $items
     * @return array{items: list<T>, page: int, pages: int, total: int, perPage: int, hasPrev: bool, hasNext: bool}
     *   page is clamped to 1…pages; pages is at least 1.
     */
    public static function paginate(array $items, int $page, int $perPage): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /**
     * Counts for the filter UI, over the given (unfiltered) events:
     *   ['categories' => ['Meditation' => 2, 'Retreat' => 1],   // first spelling wins, sorted case-insensitively by name
     *    'languages'  => ['cs' => 1, 'de' => 3],                // sorted by code
     *    'attendance' => ['offline' => 2, 'online' => 3, 'mixed' => 1], // per filter semantics above, all three keys always present
     *    'featured'   => 1]
     *
     * @param list<Event> $events
     */
    public static function facets(array $events): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /**
     * Events whose venue id is $venueId (physicalVenue() only).
     *
     * @param list<Event> $events
     * @return list<Event>
     */
    public static function atVenue(array $events, string $venueId): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /**
     * Events with an organizer whose id is $organizerId.
     *
     * @param list<Event> $events
     * @return list<Event>
     */
    public static function byOrganizer(array $events, string $organizerId): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }
}

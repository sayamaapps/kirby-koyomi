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
        $open = [];
        foreach ($events as $event) {
            if ($event->isPast($now) || ($hideCancelled && $event->isCancelled())) {
                continue;
            }
            $open[] = $event;
        }
        return self::sort(self::filter($open, $filters), 'asc');
    }

    /**
     * Past (end <= now), newest first (sort($events, 'desc')).
     *
     * @param list<Event> $events
     * @return list<Event>
     */
    public static function past(array $events, DateTimeImmutable $now, array $filters = []): array
    {
        $ended = [];
        foreach ($events as $event) {
            if ($event->isPast($now)) {
                $ended[] = $event;
            }
        }
        return self::sort(self::filter($ended, $filters), 'desc');
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
        $category = self::lower((string) ($filters['category'] ?? ''));
        $attendance = $filters['attendance'] ?? null;
        $language = $filters['language'] ?? null;
        $featured = Normalize::bool($filters['featured'] ?? false);

        $out = [];
        foreach ($events as $event) {
            if ($category !== '' && !self::hasCategory($event, $category)) {
                continue;
            }
            if ($attendance !== null && !self::matchesAttendance($event, (string) $attendance)) {
                continue;
            }
            if ($language !== null && !in_array($language, $event->languages, true)) {
                continue;
            }
            if ($featured && !$event->featured) {
                continue;
            }
            $out[] = $event;
        }
        return $out;
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
        $sign = $direction === 'desc' ? -1 : 1;
        $key = fn (Event $e): array => [$e->start->getTimestamp(), $e->end->getTimestamp(), self::lower($e->title), $e->id];
        $events = array_values($events);
        usort($events, fn (Event $a, Event $b): int => $sign * ($key($a) <=> $key($b)));
        return $events;
    }

    /**
     * @template T
     * @param list<T> $items
     * @return array{items: list<T>, page: int, pages: int, total: int, perPage: int, hasPrev: bool, hasNext: bool}
     *   page is clamped to 1…pages; pages is at least 1.
     */
    public static function paginate(array $items, int $page, int $perPage): array
    {
        $perPage = max(1, $perPage);
        $items = array_values($items);
        $total = count($items);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        return [
            'items' => array_slice($items, ($page - 1) * $perPage, $perPage),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'perPage' => $perPage,
            'hasPrev' => $page > 1,
            'hasNext' => $page < $pages,
        ];
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
        // Keyed by the lower-case name: counts ignore case, the first spelling is kept.
        $categories = [];
        $languages = [];
        $attendance = [Attendance::OFFLINE => 0, Attendance::ONLINE => 0, Attendance::MIXED => 0];
        $featured = 0;

        foreach ($events as $event) {
            foreach ($event->categories as $category) {
                $key = self::lower($category);
                $categories[$key] ??= ['name' => $category, 'count' => 0];
                $categories[$key]['count']++;
            }
            foreach ($event->languages as $code) {
                $languages[$code] = ($languages[$code] ?? 0) + 1;
            }
            foreach (Attendance::ALL as $mode) {
                if (self::matchesAttendance($event, $mode)) {
                    $attendance[$mode]++;
                }
            }
            if ($event->featured) {
                $featured++;
            }
        }

        uasort($categories, fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        $categoryCounts = [];
        foreach ($categories as $entry) {
            $categoryCounts[$entry['name']] = $entry['count'];
        }
        ksort($languages);

        return [
            'categories' => $categoryCounts,
            'languages' => $languages,
            'attendance' => $attendance,
            'featured' => $featured,
        ];
    }

    /**
     * Events whose venue id is $venueId (physicalVenue() only).
     *
     * @param list<Event> $events
     * @return list<Event>
     */
    public static function atVenue(array $events, string $venueId): array
    {
        $out = [];
        foreach ($events as $event) {
            if ($event->physicalVenue()?->id === $venueId) {
                $out[] = $event;
            }
        }
        return $out;
    }

    /**
     * Events with an organizer whose id is $organizerId.
     *
     * @param list<Event> $events
     * @return list<Event>
     */
    public static function byOrganizer(array $events, string $organizerId): array
    {
        $out = [];
        foreach ($events as $event) {
            foreach ($event->organizers as $organizer) {
                if ($organizer->id === $organizerId) {
                    $out[] = $event;
                    break;
                }
            }
        }
        return $out;
    }

    /** The attendance filter semantics of filter() and facets(). */
    private static function matchesAttendance(Event $event, string $mode): bool
    {
        return match ($mode) {
            Attendance::ONLINE => $event->isOnline(),
            Attendance::OFFLINE => $event->isInPerson(),
            Attendance::MIXED => $event->effectiveAttendance() === Attendance::MIXED,
            default => false,
        };
    }

    private static function hasCategory(Event $event, string $lowerCategory): bool
    {
        foreach ($event->categories as $category) {
            if (self::lower($category) === $lowerCategory) {
                return true;
            }
        }
        return false;
    }

    private static function lower(string $text): string
    {
        return mb_strtolower($text);
    }
}

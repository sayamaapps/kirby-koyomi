<?php

namespace Koyomi;

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;

/**
 * Finds calendar, events, venues and organizers in the content tree and
 * hands out Event lists.            OWNER: task `kirby`
 *
 * Contract: SPEC.md §8.9.
 * - Lists contain only LISTED event pages (template koyomi-event) that are
 *   children of the calendar. Drafts never; unlisted events only on their
 *   own URL (page, .ics).
 * - Pages whose start date is invalid are skipped with one error_log line.
 * - The Event list per calendar is built once per App instance (WeakMap
 *   keyed by the App object), so several snippets on a page cost one scan.
 * - Every method that returns a list for rendering calls
 *   koyomi_expires($events) so the page cache expires when the list changes.
 */
final class Repository
{
    /** The calendar page: `koyomi.calendar` page id if it is a koyomi-calendar page, else the first koyomi-calendar among site children, else in the whole index, else null. */
    public static function calendar(App $kirby): ?Page
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** Venues container (koyomi-venues): option `koyomi.venues`, else first found; null if none. */
    public static function venuesPage(App $kirby): ?Page
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** Organizers container (koyomi-organizers): option `koyomi.organizers`, else first found; null if none. */
    public static function organizersPage(App $kirby): ?Page
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** Children (all statuses) of the venues container with template koyomi-venue — the Panel pages-field query `kirby.collection("koyomi/venues")`. Empty Pages when no container. */
    public static function venuePages(App $kirby): Pages
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** Same for organizers (`kirby.collection("koyomi/organizers")`). */
    public static function organizerPages(App $kirby): Pages
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** Listed koyomi-event children of the calendar (`kirby.collection("koyomi/events")`). Empty Pages without calendar. */
    public static function eventPages(?Page $calendar): Pages
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /**
     * All listed events of the calendar as Event objects, unsorted, cached
     * per App instance.
     *
     * @return list<Event>
     */
    public static function events(Page $calendar): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** Query::upcoming() over events() with Options::now()/hideCancelled(); calls koyomi_expires(). @return list<Event> */
    public static function upcoming(Page $calendar, array $filters = []): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** Query::past() over events(); calls koyomi_expires(). @return list<Event> */
    public static function past(Page $calendar, array $filters = []): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /**
     * Everything a list page needs (SPEC.md §8.9 shape):
     *   ['view' => 'upcoming'|'past', 'filters' => Filters array, 'events' => list<Event> (this page),
     *    'pagination' => Query::paginate() result + 'prevUrl' => ?string, 'nextUrl' => ?string,
     *    'facets' => Query::facets(all events of this view, unfiltered),
     *    'baseUrl' => calendar URL, 'pastUrl' => calendar URL + '?view=past', 'upcomingUrl' => calendar URL,
     *    'filtered' => Filters::isActive()]
     *
     * @param array<string,mixed> $query  raw query parameters (Filters::fromQuery())
     */
    public static function list(Page $calendar, array $query = []): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** Upcoming events at a venue (venue page id = Mapper::id()). @return list<Event> */
    public static function forVenue(App $kirby, Page $venue): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** Upcoming events of an organizer. @return list<Event> */
    public static function forOrganizer(App $kirby, Page $organizer): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** Forget all cached lists (tests; content changes in the same process). */
    public static function reset(): void
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }
}

<?php

namespace Koyomi;

use InvalidArgumentException;
use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use WeakMap;

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
    /** @var WeakMap<App, array<string, list<Event>>>|null calendar id → events, per App */
    private static ?WeakMap $cache = null;

    /**
     * The public calendar: `koyomi.calendar` page id if it is a published koyomi-calendar
     * page, else the first one among site children, else in the whole index, else null.
     * A draft calendar is not a calendar for the site: its listed children would otherwise
     * appear on every page that shows upcoming events.
     */
    public static function calendar(App $kirby): ?Page
    {
        return self::findByTemplate($kirby, 'koyomi-calendar', Options::calendarId($kirby), false);
    }

    /**
     * Venues container (koyomi-venues): option `koyomi.venues`, else first found; null if none.
     * Drafts are found too: the Panel's venue picker works from this container, and a venue
     * draft is hidden from the public by Mapper, not here.
     */
    public static function venuesPage(App $kirby): ?Page
    {
        return self::findByTemplate($kirby, 'koyomi-venues', Options::venuesId($kirby), true);
    }

    /** Organizers container (koyomi-organizers): option `koyomi.organizers`, else first found; null if none. Drafts found too (see venuesPage()). */
    public static function organizersPage(App $kirby): ?Page
    {
        return self::findByTemplate($kirby, 'koyomi-organizers', Options::organizersId($kirby), true);
    }

    /** Children (all statuses) of the venues container with template koyomi-venue — the Panel pages-field query `kirby.collection("koyomi/venues")`. Empty Pages when no container. */
    public static function venuePages(App $kirby): Pages
    {
        return self::childrenWithTemplate(self::venuesPage($kirby), 'koyomi-venue');
    }

    /** Same for organizers (`kirby.collection("koyomi/organizers")`). */
    public static function organizerPages(App $kirby): Pages
    {
        return self::childrenWithTemplate(self::organizersPage($kirby), 'koyomi-organizer');
    }

    /** Listed koyomi-event children of the calendar (`kirby.collection("koyomi/events")`). Empty Pages without calendar. */
    public static function eventPages(?Page $calendar): Pages
    {
        if ($calendar === null) {
            return new Pages();
        }
        // children() leaves out drafts; listed() leaves out unlisted events.
        // `$kirby->page()` would also find drafts, hence the explicit filter.
        return $calendar->children()->listed()->filter(
            fn (Page $page): bool => $page->intendedTemplate()->name() === 'koyomi-event'
        );
    }

    /**
     * All listed events of the calendar as Event objects, unsorted, cached
     * per App instance.
     *
     * @return list<Event>
     */
    public static function events(Page $calendar): array
    {
        $kirby = $calendar->kirby();
        $key = $calendar->id();
        self::$cache ??= new WeakMap();
        $byCalendar = self::$cache[$kirby] ?? [];
        if (!array_key_exists($key, $byCalendar)) {
            $byCalendar[$key] = self::load($calendar);
            self::$cache[$kirby] = $byCalendar;
        }
        return $byCalendar[$key];
    }

    /** Query::upcoming() over events() with Options::now()/hideCancelled(); calls koyomi_expires(). @return list<Event> */
    public static function upcoming(Page $calendar, array $filters = []): array
    {
        $kirby = $calendar->kirby();
        $events = self::events($calendar);
        koyomi_expires($events);
        return Query::upcoming($events, Options::now($kirby), $filters, Options::hideCancelled($kirby));
    }

    /** Query::past() over events(); calls koyomi_expires(). @return list<Event> */
    public static function past(Page $calendar, array $filters = []): array
    {
        $kirby = $calendar->kirby();
        $events = self::events($calendar);
        koyomi_expires($events);
        return Query::past($events, Options::now($kirby), $filters);
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
        $kirby = $calendar->kirby();
        $now = Options::now($kirby);
        $filters = Filters::fromQuery($query, array_keys(Options::languages($kirby)));
        $events = self::events($calendar);
        koyomi_expires($events);

        // The view before filtering: facets count what the visitor could filter.
        $view = $filters['view'] === 'past'
            ? Query::past($events, $now)
            : Query::upcoming($events, $now, [], Options::hideCancelled($kirby));
        $matching = Query::filter($view, $filters);
        $paged = Query::paginate($matching, $filters['page'], Options::perPage($kirby));

        $baseUrl = $calendar->url();
        $pagination = [
            'page' => $paged['page'],
            'pages' => $paged['pages'],
            'total' => $paged['total'],
            'perPage' => $paged['perPage'],
            'hasPrev' => $paged['hasPrev'],
            'hasNext' => $paged['hasNext'],
            'prevUrl' => $paged['hasPrev'] ? Filters::url($baseUrl, $filters, ['page' => $paged['page'] - 1]) : null,
            'nextUrl' => $paged['hasNext'] ? Filters::url($baseUrl, $filters, ['page' => $paged['page'] + 1]) : null,
        ];

        return [
            'view' => $filters['view'],
            'filters' => $filters,
            'events' => $paged['items'],
            'pagination' => $pagination,
            'facets' => Query::facets($view),
            'title' => $calendar->title()->value(),
            'baseUrl' => $baseUrl,
            'upcomingUrl' => $baseUrl,
            'pastUrl' => $baseUrl . '?view=past',
            'filtered' => Filters::isActive($filters),
        ];
    }

    /** Upcoming events at a venue (venue page id = Mapper::id()). @return list<Event> */
    public static function forVenue(App $kirby, Page $venue): array
    {
        $calendar = self::calendar($kirby);
        if ($calendar === null) {
            return [];
        }
        return Query::atVenue(self::upcoming($calendar), Mapper::id($venue));
    }

    /** Upcoming events of an organizer. @return list<Event> */
    public static function forOrganizer(App $kirby, Page $organizer): array
    {
        $calendar = self::calendar($kirby);
        if ($calendar === null) {
            return [];
        }
        return Query::byOrganizer(self::upcoming($calendar), Mapper::id($organizer));
    }

    /** Forget all cached lists (tests; content changes in the same process). */
    public static function reset(): void
    {
        self::$cache = new WeakMap();
    }

    /** Maps the listed event pages; invalid ones are logged and left out. @return list<Event> */
    private static function load(Page $calendar): array
    {
        $kirby = $calendar->kirby();
        $defaults = Options::eventDefaults($kirby);
        $events = [];
        foreach (self::eventPages($calendar) as $page) {
            try {
                $events[] = Mapper::event($page, $defaults);
            } catch (InvalidArgumentException $e) {
                error_log('Koyomi: skipped ' . $page->id() . ': ' . $e->getMessage());
            }
        }
        return $events;
    }

    /**
     * The page the option names if it has the template, else the first page
     * with that template: site children, then the index. $withDrafts = false
     * (public lookups) skips drafts and pages below drafts everywhere,
     * including the configured id, which `$kirby->page()` would otherwise
     * find anyway (it falls back to the drafts of a parent).
     */
    private static function findByTemplate(App $kirby, string $template, ?string $configuredId, bool $withDrafts): ?Page
    {
        if ($configuredId !== null) {
            $page = $kirby->page($configuredId);
            if ($page !== null && ($withDrafts || !Mapper::isDraft($page)) && $page->intendedTemplate()->name() === $template) {
                return $page;
            }
        }
        $site = $kirby->site();
        $pools = $withDrafts
            ? [$site->childrenAndDrafts(), $site->index(true)]
            : [$site->children(), $site->index()];
        foreach ($pools as $pages) {
            foreach ($pages as $page) {
                if ($page->intendedTemplate()->name() === $template) {
                    return $page;
                }
            }
        }
        return null;
    }

    /** Children of any status with the template; empty Pages without a container. */
    private static function childrenWithTemplate(?Page $container, string $template): Pages
    {
        if ($container === null) {
            return new Pages();
        }
        return $container->childrenAndDrafts()->filter(
            fn (Page $page): bool => $page->intendedTemplate()->name() === $template
        );
    }
}

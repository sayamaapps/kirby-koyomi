<?php

namespace Koyomi;

use Kirby\Cms\Page;

/**
 * Kirby pages → Koyomi value objects.        OWNER: task `kirby`
 *
 * Reads fields ONLY via $page->content()->get('<field>') — never via magic
 * methods: `status` and `image` are Page methods, and a site's own page
 * model might add more. Field contract: SPEC.md §5.
 *
 * PRIVACY: never reads `onlineUrl`; reads organizer `email` only when
 * `showEmail` is on.
 */
final class Mapper
{
    /**
     * The fromArray() input for an event page (SPEC.md §5.1 mapping table).
     * Exposed for tests and debugging.
     */
    public static function raw(Page $page): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /**
     * Event::fromArray(self::raw($page), $defaults).
     *
     * @param array{timezone?:string,currency?:string,defaultDuration?:int} $defaults Options::eventDefaults()
     * @throws \InvalidArgumentException when the page has no valid start date
     */
    public static function event(Page $page, array $defaults = []): Event
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** Venue::fromArray() from a koyomi-venue page (SPEC.md §5.2); mapLink from `koyomi.mapUrl` when set. */
    public static function venue(Page $page): Venue
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** Organizer::fromArray() from a koyomi-organizer page (SPEC.md §5.3); email only with showEmail. */
    public static function organizer(Page $page): Organizer
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** Page UUID id without "page://" when UUIDs are on, else the page id with "/" → "-". */
    public static function id(Page $page): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }
}

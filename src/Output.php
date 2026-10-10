<?php

namespace Koyomi;

use Kirby\Cms\Page;

/**
 * Bodies and headers of the content representations
 * (templates/koyomi-*.ics.php, .rss.php, .json.php call these).
 *                                               OWNER: task `kirby`
 * Contract: SPEC.md §8.10.
 *
 * Every method first checks Options::route() for its switch (which includes
 * `active`). Switched off → throws Kirby\Exception\NotFoundException, which
 * Kirby turns into its normal 404 error page. Switched on → sets the
 * response type/charset/headers and the cache expiry, and returns the body.
 */
final class Output
{
    /** /<event>.ics — switch `ics`. text/calendar; charset=utf-8, Content-Disposition: attachment; filename="<slug>.ics". */
    public static function eventIcs(Page $event): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** /<calendar>.ics — switch `feed`. Upcoming + events that ended within feedPastDays, cancelled included. Content-Disposition: inline; filename="<slug>.ics". */
    public static function calendarIcs(Page $calendar): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** /<calendar>.rss — switch `rss`. application/rss+xml; charset=utf-8. Upcoming events (hideCancelled respected). */
    public static function rss(Page $calendar): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** /<calendar>.json — switch `json` (default off). application/json; charset=utf-8. */
    public static function json(Page $calendar): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }
}

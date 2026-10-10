<?php

namespace Koyomi;

/**
 * "Add to Google Calendar" — a plain link, no script, nothing loaded from
 * Google until the visitor clicks. Kirby-free.     OWNER: task `present`
 *
 * Contract: SPEC.md §8.6.
 */
final class GoogleLink
{
    public const BASE = 'https://calendar.google.com/calendar/render';

    /** Maximum characters of the description put into `details`. */
    public const MAX_DETAILS = 500;

    /**
     * https://calendar.google.com/calendar/render?action=TEMPLATE&text=…&dates=…[&ctz=…]&details=…[&location=…]
     * built with http_build_query(…, '', '&', PHP_QUERY_RFC3986).
     */
    public static function url(Event $event, string $language = 'en'): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }
}

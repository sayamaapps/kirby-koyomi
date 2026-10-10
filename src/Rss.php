<?php

namespace Koyomi;

use DateTimeImmutable;

/**
 * RSS 2.0 feed of events, Kirby-free.        OWNER: task `seo`
 *
 * Contract: SPEC.md §8.4 (full example document there).
 */
final class Rss
{
    /** RSS event module namespace for ev:startdate / ev:enddate / ev:location. */
    public const EV_NS = 'http://purl.org/rss/1.0/modules/event/';

    /**
     * @param list<Event> $events   already filtered and sorted (upcoming, start ascending)
     * @param array{
     *   title: string,             // channel title (calendar page title)
     *   link: string,              // calendar page URL
     *   self: string,              // feed URL (atom:link rel="self")
     *   description?: string,      // default I18n "rss.description"
     *   language?: string,         // channel <language>, default I18n::language()
     *   now?: DateTimeImmutable,   // <lastBuildDate>, default real now
     *   describe?: callable,       // fn (Event $e): string — plain-text item description; default $e->description
     *   host?: string,             // for <guid>: Ics::uid()-style "<id>@<host>"; default host of `link`
     * } $channel
     */
    public static function feed(array $events, array $channel): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** XML text escaping: htmlspecialchars(ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), control characters removed. */
    public static function escape(string $text): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }
}

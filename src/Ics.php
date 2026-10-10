<?php

namespace Koyomi;

use DateTimeImmutable;

/**
 * iCalendar (RFC 5545) writer, Kirby-free.        OWNER: task `ics`
 *
 * Contract: SPEC.md §8.1. Summary:
 * - Content lines are joined with CRLF, the document ends with CRLF.
 * - Lines longer than 75 octets are folded (CRLF + one space), never inside
 *   a UTF-8 multibyte sequence.
 * - TEXT values escape \ ; , and newlines (escape()).
 * - Timed events: DTSTART/DTEND;TZID=<zone>:YYYYMMDDTHHMMSS plus one
 *   VTIMEZONE per used zone (VTimezone::lines()); zone "UTC" uses the
 *   ...Z form and no VTIMEZONE.
 * - All-day: DTSTART;VALUE=DATE / DTEND;VALUE=DATE with EXCLUSIVE end
 *   (Event::$end is already exclusive).
 * - UID "<event id>@<host>", SEQUENCE from Event::$modified, DTSTAMP = now (UTC).
 * - Never prints anything that is not in Event (so never an onlineUrl).
 */
final class Ics
{
    public const PRODID = '-//SayamaApps//Koyomi 1.0//EN';

    /** 2020-01-01T00:00:00Z — SEQUENCE counts minutes since then. */
    public const SEQUENCE_EPOCH = 1577836800;

    /**
     * Whole calendar with all given events (in the given order) and the
     * VTIMEZONEs they need. Used for the subscription feed.
     *
     * @param list<Event> $events
     * @param array{
     *   host?: string,            // for UID; default "localhost"
     *   now?: DateTimeImmutable,  // DTSTAMP; default real now
     *   language?: string,        // 'en'|'de' for "Online", status prefixes, "More information"; default I18n::language()
     *   name?: string,            // X-WR-CALNAME (omitted when empty)
     *   description?: string,     // X-WR-CALDESC (omitted when empty)
     *   timezone?: string,        // X-WR-TIMEZONE (omitted when empty)
     *   refresh?: string,         // REFRESH-INTERVAL;VALUE=DURATION + X-PUBLISHED-TTL, e.g. "PT6H"; omitted when empty
     * } $options
     */
    public static function calendar(array $events, array $options = []): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** One event as a complete VCALENDAR (download "Add to calendar"). Same options as calendar(). */
    public static function event(Event $event, array $options = []): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /**
     * The unfolded content lines of one VEVENT, from BEGIN:VEVENT to
     * END:VEVENT (SPEC.md §8.1 lists every property and its order).
     *
     * @return list<string>
     */
    public static function vevent(Event $event, array $options = []): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** "<id>@<host>"; characters outside [A-Za-z0-9._-] in the id become "-". */
    public static function uid(Event $event, string $host): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** max(0, intdiv(modified − SEQUENCE_EPOCH, 60)) — grows with every save. */
    public static function sequence(Event $event): int
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** TEXT escaping: "\" → "\\", ";" → "\;", "," → "\,", CRLF/CR/LF → "\n" (backslash + n). */
    public static function escape(string $text): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /**
     * Folds ONE unfolded line: first chunk ≤ 75 octets, every further chunk
     * is CRLF + " " + ≤ 74 octets. Never splits a UTF-8 character. No
     * trailing CRLF. A line of ≤ 75 octets is returned unchanged.
     */
    public static function fold(string $line): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** fold() each line, join with CRLF, append a final CRLF. */
    public static function render(array $lines): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /**
     * LOCATION text (unescaped):
     * - effective online: I18n "ics.online", plus " (<platform>)" when onlinePlatform is set → "Online (Zoom)"
     * - offline with venue: Venue::label() → "Seminarhaus Beispielhof, Seminarweg 1, 12345 Musterstadt, DE"
     * - mixed with venue: Venue::label() + " / " + the online text → "…, DE / Online (Zoom)"
     * - no venue and not online: ""
     */
    public static function location(Event $event, string $language): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }
}

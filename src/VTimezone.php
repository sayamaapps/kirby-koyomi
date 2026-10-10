<?php

namespace Koyomi;

/**
 * VTIMEZONE components from PHP's tz database, Kirby-free.   OWNER: task `ics`
 *
 * No RRULEs: every real transition in the window becomes its own STANDARD or
 * DAYLIGHT sub-component with an explicit DTSTART. That is valid RFC 5545,
 * exact for any zone PHP knows, and needs no guessing of rules.
 * Contract: SPEC.md §8.2.
 */
final class VTimezone
{
    /**
     * Lines (unfolded) BEGIN:VTIMEZONE … END:VTIMEZONE for $zone, covering
     * $from … $to (unix timestamps). The window is widened by one year on
     * each side. The state in force at the start of the window is always
     * emitted, so the first event's offset is defined.
     *
     * Sub-component for a transition at UTC instant T from offset A to B:
     *   BEGIN:STANDARD (or DAYLIGHT when the new state is DST)
     *   DTSTART:<T + A as local time, Ymd\THis>   ← local time BEFORE the change
     *   TZOFFSETFROM:<A as ±HHMM>
     *   TZOFFSETTO:<B as ±HHMM>
     *   TZNAME:<abbreviation after the change, e.g. CEST>
     *   END:STANDARD
     *
     * A zone without any transition in the window (e.g. Asia/Tokyo) gives one
     * STANDARD with DTSTART:19700101T000000 and TZOFFSETFROM = TZOFFSETTO =
     * the current offset.
     *
     * Returns [] for "UTC" (callers use the Z form instead).
     *
     * @return list<string>
     */
    public static function lines(string $zone, int $from, int $to): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** Seconds → "+0100", "-0500", "+0530", "+0000". */
    public static function offset(int $seconds): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }
}

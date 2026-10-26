<?php

namespace Sayamaapps\Koyomi;

use DateTimeImmutable;

/**
 * When does a rendered list go stale? Kirby-free.   SHARED (done)
 *
 * A cached calendar page must not keep showing an event that has ended, nor
 * miss the moment an event starts ("Happening now"). The answer is the
 * earliest start or end that is still in the future. index.php's
 * koyomi_expires() passes it to Kirby: $kirby->response()->expires($ts)
 * (Kirby\Cms\Responder::expires(), which only ever LOWERS an expiry, so
 * several lists on one page combine correctly). The subscription feed also
 * changes when an event leaves its window (leaving()), and a timeline's
 * "Today" line at local midnight (midnight()); koyomi_expires_at() passes those.
 */
final class Expiry
{
    /**
     * Unix timestamp of min({start, end of every event} ∩ (now, ∞)), or null
     * when nothing will change.
     *
     * @param list<Event> $events
     */
    public static function next(array $events, DateTimeImmutable $now): ?int
    {
        $limit = $now->getTimestamp();
        $next = null;
        foreach ($events as $event) {
            if (!$event instanceof Event) {
                continue;
            }
            foreach ([$event->start->getTimestamp(), $event->end->getTimestamp()] as $moment) {
                if ($moment > $limit && ($next === null || $moment < $next)) {
                    $next = $moment;
                }
            }
        }
        return $next;
    }

    /**
     * Unix timestamp of the first future moment an event leaves a window that
     * keeps events for $days days after their end (the subscription feed keeps
     * end > now − $days days), or null when none will.
     *
     * @param list<Event> $events
     */
    public static function leaving(array $events, DateTimeImmutable $now, int $days): ?int
    {
        $limit = $now->getTimestamp();
        $next = null;
        foreach ($events as $event) {
            if (!$event instanceof Event) {
                continue;
            }
            $moment = $event->end->setTimezone($now->getTimezone())->modify('+' . $days . ' days')->getTimestamp();
            if ($moment > $limit && ($next === null || $moment < $next)) {
                $next = $moment;
            }
        }
        return $next;
    }

    /** Unix timestamp of the next local midnight in $now's zone: a "Today" line goes stale then. */
    public static function midnight(DateTimeImmutable $now): int
    {
        return (new DateTimeImmutable($now->format('Y-m-d'), $now->getTimezone()))->modify('+1 day')->getTimestamp();
    }
}

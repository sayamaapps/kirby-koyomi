<?php

namespace Koyomi;

use DateTimeImmutable;

/**
 * When does a rendered list go stale? Kirby-free.   SHARED (done)
 *
 * A cached calendar page must not keep showing an event that has ended, nor
 * miss the moment an event starts ("Happening now"). The answer is the
 * earliest start or end that is still in the future. index.php's
 * koyomi_expires() passes it to Kirby: $kirby->response()->expires($ts)
 * (Kirby\Cms\Responder::expires(), which only ever LOWERS an expiry, so
 * several lists on one page combine correctly).
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
}

<?php

namespace Sayamaapps\Koyomi;

use DateTimeImmutable;
use DateTimeZone;

/**
 * When does a rendered list go stale? Kirby-free.
 *
 * A cached calendar page must not keep showing an event that has ended, nor
 * miss the moment an event starts ("Happening now"). The answer is the
 * earliest start or end that is still in the future. Api::expires()
 * passes it to Kirby: $kirby->response()->expires($ts)
 * (Kirby\Cms\Responder::expires(), which only ever LOWERS an expiry, so
 * several lists on one page combine correctly). The subscription feed also
 * changes when an event leaves its window (leaving()), and a timeline's
 * "Today" line at local midnight (midnight()); Api::expiresAt() passes those.
 *
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-koyomi
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class Expiry
{
	/**
	 * Unix timestamp of the moment $event leaves a window that keeps events for
	 * $days calendar days after their end, counted forward from the end on the
	 * wall clock of $zone. The subscription feed filter and the cache expiry
	 * both use it, so the feed changes exactly when the cache expires, also
	 * across a daylight-saving change.
	 */
	public static function leavesAt(Event $event, DateTimeZone $zone, int $days): int
	{
		return $event->end->setTimezone($zone)->modify('+' . $days . ' days')->getTimestamp();
	}

	/**
	 * Unix timestamp of the first future moment an event leaves a window that
	 * keeps events for $days days after their end (leavesAt(): the subscription
	 * feed keeps an event while that moment is still ahead), or null when none
	 * will.
	 *
	 * @param list<Event> $events
	 */
	public static function leaving(array $events, DateTimeImmutable $now, int $days): int|null
	{
		$limit = $now->getTimestamp();
		$next = null;
		foreach ($events as $event) {
			if (!$event instanceof Event) {
				continue;
			}
			$moment = self::leavesAt($event, $now->getTimezone(), $days);
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

	/**
	 * Unix timestamp of min({start, end of every event} ∩ (now, ∞)), or null
	 * when nothing will change.
	 *
	 * @param list<Event> $events
	 */
	public static function next(array $events, DateTimeImmutable $now): int|null
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

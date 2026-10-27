<?php

namespace Sayamaapps\Koyomi;

/**
 * Event status (content field `eventStatus` — NOT `status`, which is
 * Kirby's own draft/unlisted/listed and a Page method).
 *
 * Mapping used by the outputs:
 *   value        label key            schema.org eventStatus           ICS STATUS
 *   scheduled    status.scheduled     https://schema.org/EventScheduled   CONFIRMED
 *   cancelled    status.cancelled     https://schema.org/EventCancelled   CANCELLED
 *   postponed    status.postponed     https://schema.org/EventPostponed   TENTATIVE
 *   rescheduled  status.rescheduled   https://schema.org/EventRescheduled CONFIRMED
 *   movedOnline  status.movedOnline   https://schema.org/EventMovedOnline CONFIRMED
 *
 * @package   Kirby Koyomi
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-koyomi
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class Status
{
	public const ALL = [self::SCHEDULED, self::CANCELLED, self::POSTPONED, self::RESCHEDULED, self::MOVED_ONLINE];
	public const CANCELLED = 'cancelled';
	public const MOVED_ONLINE = 'movedOnline';
	public const POSTPONED = 'postponed';
	public const RESCHEDULED = 'rescheduled';
	public const SCHEDULED = 'scheduled';

	/** Known value (exact, or case-insensitive) or `scheduled`. */
	public static function normalize(mixed $value): string
	{
		return Normalize::choice($value, self::ALL, self::SCHEDULED);
	}
}

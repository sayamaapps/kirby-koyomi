<?php

namespace Sayamaapps\Koyomi;

/**
 * "Add to Google Calendar" — a plain link, no script, nothing loaded from
 * Google until the visitor clicks. Kirby-free.
 *
 */
final class GoogleLink
{
	public const BASE = 'https://calendar.google.com/calendar/render';

	/** Maximum characters of the description put into `details`. */
	public const MAX_DETAILS = 500;

	/** The venue's label for an in-person event, the word "Online" for an online-only one, else "". */
	private static function location(Event $event): string
	{
		$venue = $event->physicalVenue();
		if ($venue !== null) {
			return $venue->label();
		}
		return $event->isOnline() ? I18n::t('ics.online') : '';
	}

	/** @return array<string, string> in Google's parameter order */
	private static function params(Event $event): array
	{
		$params = ['action' => 'TEMPLATE', 'text' => $event->title];

		if ($event->allDay) {
			// Google's all-day end is exclusive, like Event::$end.
			$params['dates'] = Time::icsDate($event->start) . '/' . Time::icsDate($event->end);
		} elseif ($event->timezone === 'UTC') {
			$params['dates'] = Time::utc($event->start) . '/' . Time::utc($event->end);
		} else {
			// Wall times in the event's zone, named by ctz.
			$params['dates'] = Time::local($event->start) . '/' . Time::local($event->end);
			$params['ctz'] = $event->timezone;
		}

		$description = $event->description;
		if (mb_strlen($description) > self::MAX_DETAILS) {
			$description = mb_substr($description, 0, self::MAX_DETAILS) . "\u{2026}";
		}
		$details = implode("\n\n", array_values(array_filter([$description, $event->url], fn (string $part) => $part !== '')));
		if ($details !== '') {
			$params['details'] = $details;
		}

		$location = self::location($event);
		if ($location !== '') {
			$params['location'] = $location;
		}
		return $params;
	}

	/**
	 * https://calendar.google.com/calendar/render?action=TEMPLATE&text=…&dates=…[&ctz=…]&details=…[&location=…]
	 * built with http_build_query(…, '', '&', PHP_QUERY_RFC3986).
	 */
	public static function url(Event $event, string $language = 'en'): string
	{
		// Only the "Online" location word depends on the language; restore
		// the caller's language whatever happens.
		$previous = I18n::language();
		I18n::setLanguage($language);
		try {
			$query = http_build_query(self::params($event), '', '&', PHP_QUERY_RFC3986);
		} finally {
			I18n::setLanguage($previous);
		}
		return self::BASE . '?' . $query;
	}
}

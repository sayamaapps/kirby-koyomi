<?php

namespace Sayamaapps\Koyomi;

use DateTimeImmutable;
use DateTimeZone;

/**
 * iCalendar (RFC 5545) writer, Kirby-free.
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
	private const CONTINUATION_LIMIT = 74;

	/**
	 * RFC 5545 §3.1: a physical line is at most 75 octets. Continuation
	 * lines start with the single folding space, so they carry 74 content
	 * octets.
	 */
	private const LINE_LIMIT = 75;
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
		$settings = self::settings($options);

		return self::inLanguage($settings['language'], function () use ($events, $options, $settings): string {
			$lines = [
				'BEGIN:VCALENDAR',
				'VERSION:2.0',
				'PRODID:' . self::PRODID,
				'CALSCALE:GREGORIAN',
			];
			$name = (string)($options['name'] ?? '');
			if ($name !== '') {
				$lines[] = 'X-WR-CALNAME:' . self::escape($name);
			}
			$description = (string)($options['description'] ?? '');
			if ($description !== '') {
				$lines[] = 'X-WR-CALDESC:' . self::escape($description);
			}
			$timezone = (string)($options['timezone'] ?? '');
			if ($timezone !== '') {
				$lines[] = 'X-WR-TIMEZONE:' . $timezone;
			}
			$refresh = (string)($options['refresh'] ?? '');
			if ($refresh !== '') {
				$lines[] = 'REFRESH-INTERVAL;VALUE=DURATION:' . $refresh;
				$lines[] = 'X-PUBLISHED-TTL:' . $refresh;
			}

			foreach (self::timezoneLines($events) as $line) {
				$lines[] = $line;
			}
			foreach ($events as $event) {
				foreach (self::vevent($event, $settings) as $line) {
					$lines[] = $line;
				}
			}

			$lines[] = 'END:VCALENDAR';
			return self::render($lines);
		});
	}

	/**
	 * DESCRIPTION before escaping: the status line (only when not
	 * scheduled), the description and the link to the event page, separated
	 * by blank lines. Empty parts are left out.
	 */
	private static function descriptionParts(Event $event, string $label): string
	{
		$parts = [];
		if ($event->status !== Status::SCHEDULED) {
			$parts[] = $label . ($event->statusNote !== '' ? ': ' . $event->statusNote : '');
		}
		if ($event->description !== '') {
			$parts[] = $event->description;
		}
		if ($event->url !== '') {
			$parts[] = I18n::t('ics.more', ['url' => $event->url]);
		}
		return implode("\n\n", $parts);
	}

	/**
	 * TEXT escaping: "\" → "\\", ";" → "\;", "," → "\,", CRLF/CR/LF → "\n" (backslash + n).
	 * The backslash goes first, otherwise the escapes added for ";" and ","
	 * would be escaped again.
	 */
	public static function escape(string $text): string
	{
		$text = str_replace('\\', '\\\\', $text);
		$text = str_replace(';', '\\;', $text);
		$text = str_replace(',', '\\,', $text);
		return str_replace(["\r\n", "\r", "\n"], '\\n', $text);
	}

	/**
	 * One event as a complete VCALENDAR (download "Add to calendar"). Same
	 * options as calendar().
	 */
	public static function event(Event $event, array $options = []): string
	{
		return self::calendar([$event], $options);
	}

	/**
	 * Folds ONE unfolded line: first chunk ≤ 75 octets, every further chunk
	 * is CRLF + " " + ≤ 74 octets. Never splits a UTF-8 character. No
	 * trailing CRLF. A line of ≤ 75 octets is returned unchanged.
	 *
	 * Octets, not characters: clients count bytes, so a line of 40 "ä"
	 * (88 octets) is cut where the byte count allows, not after 75 characters.
	 */
	public static function fold(string $line): string
	{
		if (strlen($line) <= self::LINE_LIMIT) {
			return $line;
		}

		$parts = [];
		$length = strlen($line);
		$position = 0;
		$limit = self::LINE_LIMIT;
		while ($position < $length) {
			$end = min($length, $position + $limit);
			$cut = $end;
			// A byte of the form 10xxxxxx is a continuation byte: cutting in
			// front of it would split a character, so move the cut back.
			while ($end < $length && $end > $position && (ord($line[$end]) & 0xC0) === 0x80) {
				$end--;
			}
			// Input that is not valid UTF-8 can have no boundary in reach; the
			// cut stays where the octet count says, so the loop always advances.
			if ($end === $position) {
				$end = $cut;
			}
			$parts[] = substr($line, $position, $end - $position);
			$position = $end;
			$limit = self::CONTINUATION_LIMIT;
		}

		return implode("\r\n ", $parts);
	}

	/** SPEC §7: postponed is TENTATIVE, cancelled is CANCELLED, everything else CONFIRMED. */
	private static function icsStatus(string $status): string
	{
		return match ($status) {
			Status::CANCELLED => 'CANCELLED',
			Status::POSTPONED => 'TENTATIVE',
			default => 'CONFIRMED',
		};
	}

	/**
	 * Runs $fn with the given language and restores the previous one even
	 * when $fn throws, so a feed in German never leaves the rest of the
	 * request in German.
	 */
	private static function inLanguage(string $language, callable $fn): mixed
	{
		$previous = I18n::language();
		I18n::setLanguage($language);
		try {
			return $fn();
		} finally {
			I18n::setLanguage($previous);
		}
	}

	/**
	 * True when the local wall time of $time occurs twice in its zone: the
	 * offsets a day before and after $time each give a valid instant for the
	 * same local time, and the two differ.
	 */
	public static function isAmbiguous(DateTimeImmutable $time): bool
	{
		$zone = $time->getTimezone();
		$localAsUtc = (new DateTimeImmutable($time->format('Y-m-d H:i:s'), new DateTimeZone('UTC')))->getTimestamp();
		$instants = [];
		foreach ([$time->modify('-1 day')->getOffset(), $time->modify('+1 day')->getOffset()] as $offset) {
			$candidate = $localAsUtc - $offset;
			$at = (new DateTimeImmutable('@' . $candidate))->setTimezone($zone);
			if ($at->getOffset() === $offset) {
				$instants[$candidate] = true;
			}
		}
		return count($instants) > 1;
	}

	/**
	 * LOCATION text (unescaped):
	 * - effective online: I18n "ics.online", plus " (<platform>)" when onlinePlatform is set → "Online (Zoom)"
	 * - offline with venue: Venue::label() → "Seminarhaus Beispielhof, Musterstraße 1, 12345 Musterstadt, DE"
	 * - mixed with venue: Venue::label() + " / " + the online text → "…, DE / Online (Zoom)"
	 * - no venue and not online: ""
	 *
	 * The venue comes from physicalVenue(), so an event moved online never
	 * prints its old address, and the meeting link is never part of it.
	 */
	public static function location(Event $event, string $language): string
	{
		return self::inLanguage($language, function () use ($event): string {
			$parts = [];
			$venue = $event->physicalVenue();
			if ($venue !== null && $venue->label() !== '') {
				$parts[] = $venue->label();
			}
			if ($event->isOnline()) {
				$online = I18n::t('ics.online');
				if ($event->onlinePlatform !== '') {
					$online .= ' (' . $event->onlinePlatform . ')';
				}
				$parts[] = $online;
			}
			return implode(' / ', $parts);
		});
	}

	/**
	 * fold() each line, join with CRLF, append a final CRLF. The final CRLF
	 * is required by RFC 5545 §3.1; an empty list renders as an empty string.
	 */
	public static function render(array $lines): string
	{
		if ($lines === []) {
			return '';
		}
		return implode("\r\n", array_map(fn (string $line): string => self::fold($line), $lines)) . "\r\n";
	}

	/**
	 * max(0, intdiv(modified − SEQUENCE_EPOCH, 60)) — grows with every save.
	 * Calendar apps only replace an event they already have when SEQUENCE
	 * goes up, so minutes since 2020 give a monotonic number without storing
	 * a counter. An event without modified time stays at 0.
	 */
	public static function sequence(Event $event): int
	{
		return max(0, intdiv($event->modified - self::SEQUENCE_EPOCH, 60));
	}

	/**
	 * Options with the documented defaults. The language is validated by
	 * I18n::setLanguage() when it is applied.
	 *
	 * @return array{host: string, now: DateTimeImmutable, language: string}
	 */
	private static function settings(array $options): array
	{
		return [
			'host' => (string)($options['host'] ?? 'localhost'),
			'now' => $options['now'] ?? new DateTimeImmutable(),
			'language' => (string)($options['language'] ?? I18n::language()),
		];
	}

	/**
	 * One VTIMEZONE per distinct zone of the TIMED events, sorted by zone
	 * name. The window spans the zone's earliest start to latest end, so a
	 * subscription feed with a year-long range still gets every transition
	 * it needs. All-day events and the UTC zone need no VTIMEZONE.
	 *
	 * @param list<Event> $events
	 * @return list<string>
	 */
	private static function timezoneLines(array $events): array
	{
		$windows = [];
		foreach ($events as $event) {
			if ($event->allDay || $event->timezone === 'UTC') {
				continue;
			}
			$start = $event->start->getTimestamp();
			$end = $event->end->getTimestamp();
			$windows[$event->timezone] = isset($windows[$event->timezone])
				? [min($windows[$event->timezone][0], $start), max($windows[$event->timezone][1], $end)]
				: [$start, $end];
		}
		ksort($windows, SORT_STRING);

		$lines = [];
		foreach ($windows as $zone => [$from, $to]) {
			foreach (VTimezone::lines($zone, $from, $to) as $line) {
				$lines[] = $line;
			}
		}
		return $lines;
	}

	/**
	 * "<id>@<host>"; characters outside [A-Za-z0-9._-] in the id become "-".
	 * The id goes into a URL-like identifier that calendar apps keep forever,
	 * so it is restricted to a set that is safe in every client.
	 */
	public static function uid(Event $event, string $host): string
	{
		$clean = preg_replace('/[^A-Za-z0-9._-]/u', '-', $event->id)
			?? preg_replace('/[^A-Za-z0-9._-]/', '-', $event->id)
			?? '';
		return $clean . '@' . $host;
	}

	/**
	 * The unfolded content lines of one VEVENT, from BEGIN:VEVENT to
	 * END:VEVENT (SPEC.md §8.1 lists every property and its order).
	 *
	 * Lines are returned UNFOLDED on purpose: tests compare the properties,
	 * and render() folds them once for the whole document.
	 *
	 * @return list<string>
	 */
	public static function vevent(Event $event, array $options = []): array
	{
		$settings = self::settings($options);

		return self::inLanguage($settings['language'], function () use ($event, $settings): array {
			$zone = $event->timezone;
			$label = I18n::t('status.' . $event->status);
			$cancelled = $event->status === Status::CANCELLED;

			$lines = [
				'BEGIN:VEVENT',
				'UID:' . self::uid($event, $settings['host']),
				'DTSTAMP:' . Time::utc($settings['now']),
			];
			if ($event->modified > 0) {
				$lines[] = 'LAST-MODIFIED:' . gmdate('Ymd\THis\Z', $event->modified);
			}
			$lines[] = 'SEQUENCE:' . self::sequence($event);

			if ($event->allDay) {
				$lines[] = 'DTSTART;VALUE=DATE:' . Time::icsDate($event->start);
				$lines[] = 'DTEND;VALUE=DATE:' . Time::icsDate($event->end);
			} elseif ($zone === 'UTC') {
				$lines[] = 'DTSTART:' . Time::utc($event->start);
				$lines[] = 'DTEND:' . Time::utc($event->end);
			} else {
				$lines[] = self::zonedLine('DTSTART', $event->start, $zone);
				$lines[] = self::zonedLine('DTEND', $event->end, $zone);
			}

			// A cancelled or postponed event stays in the feed; the prefix is
			// what a subscriber sees in the list before opening the entry.
			$summary = $event->title;
			if (in_array($event->status, [Status::CANCELLED, Status::POSTPONED], true)) {
				$summary = $event->title === '' ? $label : $label . ': ' . $event->title;
			}
			if ($summary !== '') {
				$lines[] = 'SUMMARY:' . self::escape($summary);
			}

			$description = self::descriptionParts($event, $label);
			if ($description !== '') {
				$lines[] = 'DESCRIPTION:' . self::escape($description);
			}

			$location = self::location($event, $settings['language']);
			if ($location !== '') {
				$lines[] = 'LOCATION:' . self::escape($location);
			}
			$venue = $event->physicalVenue();
			if ($venue !== null && $venue->hasCoordinates()) {
				// GEO is separated by ";" and must use "." as decimal point.
				$lines[] = 'GEO:' . Venue::coordinate((float)$venue->lat) . ';' . Venue::coordinate((float)$venue->lng);
			}
			if ($event->url !== '') {
				// URI value: not TEXT, so no escaping.
				$lines[] = 'URL:' . $event->url;
			}

			$lines[] = 'STATUS:' . self::icsStatus($event->status);
			$lines[] = 'TRANSP:' . ($cancelled ? 'TRANSPARENT' : 'OPAQUE');

			if ($event->categories !== []) {
				$lines[] = 'CATEGORIES:' . implode(',', array_map([self::class, 'escape'], $event->categories));
			}

			// Only a public email reaches this point (Mapper drops hidden ones),
			// and the organizer is the first one who has one.
			foreach ($event->organizers as $organizer) {
				if ($organizer->email === '') {
					continue;
				}
				// A quoted parameter value cannot contain a double quote, and a
				// control character (CR/LF above all) would break the line and
				// inject properties, so both are dropped (RFC 5545 §3.2).
				$name = (string)preg_replace('/[\x00-\x1F\x7F"]/', '', $organizer->name);
				$lines[] = ($name !== '' ? 'ORGANIZER;CN="' . $name . '":' : 'ORGANIZER:') . 'mailto:' . $organizer->email;
				break;
			}

			$lines[] = 'END:VEVENT';
			return $lines;
		});
	}

	/**
	 * DTSTART/DTEND of a timed event in its zone. A local time in the autumn
	 * DST overlap (02:30 on the last Sunday of October in Berlin) names two
	 * instants, and RFC 5545 §3.3.5 makes a TZID time mean the FIRST one, one
	 * hour before the instant the site and JSON-LD show. Such a value is
	 * therefore written in UTC form, which pins the instant (the standard
	 * time, +01:00).
	 */
	private static function zonedLine(string $name, DateTimeImmutable $time, string $zone): string
	{
		if (self::isAmbiguous($time)) {
			return $name . ':' . Time::utc($time);
		}
		return $name . ';TZID=' . $zone . ':' . Time::local($time);
	}
}

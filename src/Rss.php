<?php

namespace Sayamaapps\Koyomi;

use DateTimeImmutable;

/**
 * RSS 2.0 feed of events, Kirby-free.
 *
 * @package   Sayamaapps\Koyomi
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-koyomi
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
final class Rss
{
	/** RSS event module namespace for ev:startdate / ev:enddate / ev:location. */
	public const EV_NS = 'http://purl.org/rss/1.0/modules/event/';

	/**
	 * @param list<Event> $events
	 */
	private static function document(array $events, array $channel, string $language): string
	{
		$link = (string)($channel['link'] ?? '');
		$host = (string)($channel['host'] ?? (parse_url($link, PHP_URL_HOST) ?: 'localhost'));
		$now = $channel['now'] ?? new DateTimeImmutable('now');
		$describe = $channel['describe'] ?? fn (Event $event): string => $event->description;

		$lines = [
			'<?xml version="1.0" encoding="UTF-8"?>',
			'<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:ev="' . self::EV_NS . '">',
			'  <channel>',
			'    <title>' . self::escape((string)($channel['title'] ?? '')) . '</title>',
			'    <link>' . self::escape($link) . '</link>',
			'    <description>' . self::escape((string)($channel['description'] ?? I18n::t('rss.description'))) . '</description>',
			'    <language>' . self::escape($language) . '</language>',
			'    <lastBuildDate>' . gmdate(DATE_RSS, $now->getTimestamp()) . '</lastBuildDate>',
			'    <atom:link href="' . self::escape((string)($channel['self'] ?? '')) . '" rel="self" type="application/rss+xml"/>',
		];
		foreach ($events as $event) {
			foreach (self::item($event, $host, $describe) as $line) {
				$lines[] = $line;
			}
		}
		$lines[] = '  </channel>';
		$lines[] = '</rss>';

		return implode("\n", $lines) . "\n";
	}

	/** XML text escaping: htmlspecialchars(ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), control characters removed. */
	public static function escape(string $text): string
	{
		// Control characters are not allowed in XML 1.0 (tab and newline are). Kept
		// out of the escaper so the output stays well-formed for every input.
		$clean = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/', '', $text) ?? $text;
		return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	/**
	 * @param list<Event> $events already filtered and sorted (upcoming, start ascending)
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
		// Status prefixes and the default description use I18n. The channel's
		// language decides them, so the feed is consistent with its <language>.
		// The previous language is restored afterwards, like Ics::calendar().
		$previous = I18n::language();
		$language = (string)($channel['language'] ?? $previous);
		I18n::setLanguage($language);
		try {
			return self::document($events, $channel, $language);
		} finally {
			I18n::setLanguage($previous);
		}
	}

	/**
	 * "<id>@<host>" with every character outside [A-Za-z0-9._-] in the id
	 * replaced by "-" — the same rule as Ics::uid(), computed here so the
	 * feed does not depend on the ICS task.
	 */
	private static function guid(string $id, string $host): string
	{
		$local = preg_replace('/[^A-Za-z0-9._-]/u', '-', $id) ?? preg_replace('/[^A-Za-z0-9._-]/', '-', $id) ?? $id;
		return $local . '@' . $host;
	}

	/**
	 * One <item>, indented for the channel. Cancelled and postponed events keep
	 * their item; the title gets the status label as prefix, as in the ICS SUMMARY.
	 *
	 * @return list<string>
	 */
	private static function item(Event $event, string $host, callable $describe): array
	{
		$title = $event->title;
		if (in_array($event->status, [Status::CANCELLED, Status::POSTPONED], true)) {
			$title = I18n::t('status.' . $event->status) . ': ' . $title;
		}

		$lines = ['    <item>', '      <title>' . self::escape($title) . '</title>'];
		if ($event->url !== '') {
			$lines[] = '      <link>' . self::escape($event->url) . '</link>';
		}
		$lines[] = '      <guid isPermaLink="false">' . self::escape(self::guid($event->id, $host)) . '</guid>';
		if ($event->modified > 0) {
			$lines[] = '      <pubDate>' . gmdate(DATE_RSS, $event->modified) . '</pubDate>';
		}
		$description = (string)$describe($event);
		if ($description !== '') {
			$lines[] = '      <description>' . self::escape($description) . '</description>';
		}
		foreach ($event->categories as $category) {
			$lines[] = '      <category>' . self::escape($category) . '</category>';
		}

		// All-day: dates only, and the end is the inclusive last day.
		if ($event->allDay) {
			$start = Time::date($event->start);
			$end = Time::date($event->lastDay());
		} else {
			$start = Time::iso($event->start);
			$end = Time::iso($event->end);
		}
		$lines[] = '      <ev:startdate>' . self::escape($start) . '</ev:startdate>';
		// No ev:enddate for a timed event without an end in the content (see Event::$hasEndDate).
		if ($event->allDay || $event->hasEndDate) {
			$lines[] = '      <ev:enddate>' . self::escape($end) . '</ev:enddate>';
		}

		$venue = $event->physicalVenue();
		if ($venue !== null && $venue->label() !== '') {
			$lines[] = '      <ev:location>' . self::escape($venue->label()) . '</ev:location>';
		}

		$lines[] = '    </item>';
		return $lines;
	}
}

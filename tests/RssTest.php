<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Koyomi\Event;
use Sayamaapps\Koyomi\I18n;
use Sayamaapps\Koyomi\Rss;

/** The retreat of SPEC §8 as raw content, with some fields replaced. */
function rss_event(array $changes = []): Event
{
	return Event::fromArray(koyomi_raw_event(array_replace([
		'id' => 'evt-retreat', 'title' => 'Silent Retreat', 'url' => 'https://example.test/events/retreat',
		'endDate' => '2027-03-14', 'endTime' => '13:00',
		'description' => 'Three days of silence.', 'attendance' => 'mixed', 'venue' => koyomi_raw_venue(),
		'categories' => 'Retreat, Meditation', 'languages' => 'de, cs',
		'price' => '380', 'availability' => 'limited', 'bookingUrl' => 'https://booking.example/retreat',
		'organizers' => [['id' => 'org-anna', 'name' => 'Anna Beispiel', 'kind' => 'person', 'website' => 'https://anna.example']],
		'image' => 'https://example.test/media/cover.jpg', 'modified' => 1791000000,
	], $changes)));
}

/** The channel options of SPEC §8.4. */
function rss_channel(array $extra = []): array
{
	return $extra + [
		'title' => 'Events', 'link' => 'https://example.test/events', 'self' => 'https://example.test/events.rss',
		'now' => new DateTimeImmutable('2027-03-10 12:00', new DateTimeZone('Europe/Berlin')),
	];
}

/** The item block of a document (from <item> to </item>). */
function rss_item(string $xml): string
{
	preg_match('~<item>.*?</item>~s', $xml, $m);
	return $m[0] ?? '';
}

/**
 * RSS 2.0 feed (SPEC §8.4): the reference document exactly, item rules
 * (status prefix, all-day dates, location, guid, pubDate, description),
 * escaping and the language of the channel.
 */
#[CoversClass(Rss::class)]
final class RssTest extends TestCase
{
	public function testSuite(): void
	{
		koyomi_require_core();
		// I18n keeps the language per process and PHPUnit runs all files in one process.
		I18n::setLanguage('en');

		try {

			// §8.4 — the whole document, byte for byte.
			$expected = implode("\n", [
				'<?xml version="1.0" encoding="UTF-8"?>',
				'<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:ev="http://purl.org/rss/1.0/modules/event/">',
				'  <channel>',
				'    <title>Events</title>',
				'    <link>https://example.test/events</link>',
				'    <description>Upcoming events</description>',
				'    <language>en</language>',
				'    <lastBuildDate>Wed, 10 Mar 2027 11:00:00 +0000</lastBuildDate>',
				'    <atom:link href="https://example.test/events.rss" rel="self" type="application/rss+xml"/>',
				'    <item>',
				'      <title>Silent Retreat</title>',
				'      <link>https://example.test/events/retreat</link>',
				'      <guid isPermaLink="false">evt-retreat@example.test</guid>',
				'      <pubDate>Sat, 03 Oct 2026 04:00:00 +0000</pubDate>',
				'      <description>Three days of silence.</description>',
				'      <category>Retreat</category>',
				'      <category>Meditation</category>',
				'      <ev:startdate>2027-03-12T18:00:00+01:00</ev:startdate>',
				'      <ev:enddate>2027-03-14T13:00:00+01:00</ev:enddate>',
				'      <ev:location>Seminarhaus Beispielhof, Musterstraße 1, 12345 Musterstadt, DE</ev:location>',
				'    </item>',
				'  </channel>',
				'</rss>',
			]) . "\n";
			expect($expected, Rss::feed([rss_event()], rss_channel()), 'SPEC 8.4 document, byte for byte');

			$xml = Rss::feed([rss_event()], rss_channel());
			holds(str_ends_with($xml, "</rss>\n") && !str_contains($xml, "\r"), 'final newline, LF only');
			holds(simplexml_load_string($xml) !== false, 'the document is well-formed XML');

			// Channel defaults and the language.
			$withDefaults = Rss::feed([], ['title' => 'Events', 'link' => 'https://example.test/events', 'self' => 'https://example.test/events.rss',
				'now' => new DateTimeImmutable('2027-03-10 12:00', new DateTimeZone('Europe/Berlin'))]);
			holds(str_contains($withDefaults, '<description>Upcoming events</description>'), 'default channel description (en)');
			holds(!str_contains($withDefaults, '<item>'), 'no events: no item');

			$german = Rss::feed([], rss_channel(['language' => 'de', 'description' => 'Termine']));
			holds(
				str_contains($german, '<language>de</language>') && str_contains($german, '<description>Termine</description>'),
				'channel language and explicit description'
			);
			$germanDefault = Rss::feed([rss_event(['status' => 'cancelled'])], rss_channel(['language' => 'de']));
			holds(str_contains($germanDefault, '<description>Kommende Termine</description>'), 'German default description');
			holds(str_contains($germanDefault, '<title>Abgesagt: Silent Retreat</title>'), 'status prefix follows the channel language');
			expect('en', I18n::language(), 'language restored after the feed');

			// Item text: status prefix, dates, location, guid, pubDate, description.
			holds(
				str_contains(rss_item(Rss::feed([rss_event(['status' => 'cancelled'])], rss_channel())), '<title>Cancelled: Silent Retreat</title>'),
				'cancelled: title prefixed "Cancelled: "'
			);
			holds(
				str_contains(rss_item(Rss::feed([rss_event(['status' => 'postponed'])], rss_channel())), '<title>Postponed: Silent Retreat</title>'),
				'postponed: title prefixed "Postponed: "'
			);
			holds(
				str_contains(rss_item(Rss::feed([rss_event(['status' => 'cancelled'])], rss_channel())), '<guid isPermaLink="false">evt-retreat@example.test</guid>'),
				'cancelled event keeps its item and its guid'
			);
			$rescheduled = rss_item(Rss::feed([rss_event(['status' => 'rescheduled', 'previousStart' => '2027-02-20 18:00'])], rss_channel()));
			holds(str_contains($rescheduled, '<title>Silent Retreat</title>'), 'rescheduled: no title prefix');
			$movedOnline = rss_item(Rss::feed([rss_event(['status' => 'movedOnline'])], rss_channel()));
			holds(str_contains($movedOnline, '<title>Silent Retreat</title>') && !str_contains($movedOnline, '<ev:location>'), 'movedOnline: no title prefix, no physical location');

			$allDay = rss_item(Rss::feed([rss_event(['id' => 'evt-workshop', 'title' => 'Breathing Workshop', 'startDate' => '2027-03-08',
				'startTime' => '', 'endDate' => '2027-03-11', 'endTime' => '', 'allDay' => true, 'attendance' => 'offline', 'venue' => null])], rss_channel()));
			holds(
				str_contains($allDay, '<ev:startdate>2027-03-08</ev:startdate>') && str_contains($allDay, '<ev:enddate>2027-03-11</ev:enddate>'),
				'all-day: dates only, enddate is the inclusive last day'
			);
			holds(!str_contains($allDay, '<ev:location>'), 'no physical venue: no ev:location');

			$single = rss_item(Rss::feed([rss_event(['id' => 'evt-one', 'startDate' => '2027-03-25', 'startTime' => '', 'endDate' => '', 'endTime' => '',
				'allDay' => true, 'attendance' => 'offline', 'venue' => null])], rss_channel()));
			holds(str_contains($single, '<ev:enddate>2027-03-25</ev:enddate>'), 'all-day single day: enddate equals the start date');

			$online = rss_item(Rss::feed([rss_event(['attendance' => 'online', 'venue' => null])], rss_channel()));
			holds(!str_contains($online, '<ev:location>'), 'online only: no ev:location');

			$noVenueMixed = rss_item(Rss::feed([rss_event(['venue' => null])], rss_channel()));
			holds(!str_contains($noVenueMixed, '<ev:location>'), 'mixed without venue: no ev:location');


			$noPubDate = rss_item(Rss::feed([rss_event(['modified' => 0])], rss_channel()));
			holds(!str_contains($noPubDate, '<pubDate>'), 'modified 0: pubDate omitted');

			$noDescription = rss_item(Rss::feed([rss_event(['description' => ''])], rss_channel()));
			holds(!str_contains($noDescription, '<description>'), 'empty description: omitted');

			$noUrl = rss_item(Rss::feed([rss_event(['url' => ''])], rss_channel()));
			holds(!str_contains($noUrl, '<link>'), 'empty url: no link element');

			// describe callback: replaces the plain description, and may return "".
			$described = rss_item(Rss::feed([rss_event()], rss_channel(['describe' => fn (Event $e) => 'Custom: ' . $e->title])));
			holds(str_contains($described, '<description>Custom: Silent Retreat</description>'), 'describe() output is the item description');
			$silent = rss_item(Rss::feed([rss_event()], rss_channel(['describe' => fn () => ''])));
			holds(!str_contains($silent, '<description>'), 'describe() returning "" omits the description');

			// Guid: id sanitized, host from link or from the host option.
			$guid = Rss::feed([rss_event(['id' => 'evt wäh/1'])], rss_channel());
			holds(str_contains($guid, '<guid isPermaLink="false">evt-w-h-1@example.test</guid>'), 'guid: characters outside [A-Za-z0-9._-] become "-"');
			$hostFromLink = Rss::feed([rss_event()], rss_channel(['link' => 'https://events.example.org/calendar']));
			holds(str_contains($hostFromLink, '<guid isPermaLink="false">evt-retreat@events.example.org</guid>'), 'guid host = host of link');
			$hostOption = Rss::feed([rss_event()], rss_channel(['host' => 'cal.example.net']));
			holds(str_contains($hostOption, '<guid isPermaLink="false">evt-retreat@cal.example.net</guid>'), 'guid host option wins');

			// Categories, order of the items.
			$two = Rss::feed([rss_event(['id' => 'a', 'title' => 'First', 'startDate' => '2027-03-12']),
				rss_event(['id' => 'b', 'title' => 'Second', 'startDate' => '2027-03-20'])], rss_channel());
			holds(strpos($two, '<title>First</title>') < strpos($two, '<title>Second</title>'), 'two events: the given order is kept');
			holds(substr_count($two, '<item>') === 2, 'two events: two items');

			// Escaping.
			expect('&amp;', Rss::escape('&'), 'ampersand');
			expect('&lt;b&gt;', Rss::escape('<b>'), 'angle brackets');
			expect('&quot;x&quot;', Rss::escape('"x"'), 'double quotes');
			expect('&apos;x&apos;', Rss::escape("'x'"), 'single quotes');
			expect("ab\tc\nde", Rss::escape('a' . chr(1) . "b\tc\nd" . chr(11) . 'e'), 'control characters removed, tab and newline kept');
			expect('ab', Rss::escape('a' . chr(127) . 'b'), 'DEL removed');
			expect('a' . chr(0xEF) . chr(0xBF) . chr(0xBD) . 'b', Rss::escape('a' . chr(0xC3) . 'b'), 'invalid UTF-8 replaced, not passed through');
			expect('Ä Ö ß', Rss::escape('Ä Ö ß'), 'umlauts untouched');

			$escaped = rss_item(Rss::feed([rss_event(['title' => 'Tea & "Cake" <Retreat>', 'description' => 'Fish & chips <b>', 'categories' => 'A&B, C<D'])], rss_channel()));
			holds(str_contains($escaped, '<title>Tea &amp; &quot;Cake&quot; &lt;Retreat&gt;</title>'), 'title escaped');
			holds(str_contains($escaped, '<description>Fish &amp; chips &lt;b&gt;</description>'), 'description escaped');
			holds(str_contains($escaped, '<category>A&amp;B</category>') && str_contains($escaped, '<category>C&lt;D</category>'), 'categories escaped');
			holds(simplexml_load_string(Rss::feed([rss_event(['title' => 'Tea & <x>'])], rss_channel())) !== false, 'escaped titles keep the XML well-formed');

			// No ev:enddate without an end in the content (review), the start stays.
			$noEndItem = rss_item(Rss::feed([rss_event(['endDate' => '', 'endTime' => '', 'startTime' => '23:00'])], rss_channel()));
			holds(!str_contains($noEndItem, 'ev:enddate') && str_contains($noEndItem, '<ev:startdate>2027-03-12T23:00:00+01:00</ev:startdate>'), 'RSS: no ev:enddate for a timed event without an end');
			holds(str_contains(rss_item(Rss::feed([rss_event()], rss_channel())), '<ev:enddate>2027-03-14T13:00:00+01:00</ev:enddate>'), 'RSS: ev:enddate kept when the content has an end');

			// Feed link and self escaped as attributes too.
			$attr = Rss::feed([], rss_channel(['self' => 'https://example.test/events.rss?a=1&b="2"']));
			holds(str_contains($attr, 'href="https://example.test/events.rss?a=1&amp;b=&quot;2&quot;"'), 'atom:link href escaped');

		} finally {

		}
	}
}

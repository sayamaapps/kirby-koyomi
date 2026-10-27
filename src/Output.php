<?php

namespace Sayamaapps\Koyomi;

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Exception\NotFoundException;

/**
 * Bodies and headers of the content representations
 * (templates/koyomi-*.ics.php, .rss.php, .json.php call these).
 *
 *
 * Every method first checks Options::route() for its switch (which includes
 * `active`). Switched off → throws Kirby\Exception\NotFoundException, which
 * Kirby turns into its normal 404 error page. Switched on → sets the
 * response type and headers and the cache expiry, and returns the body.
 *
 * The response charset is not set here: Kirby's Responder has no charset
 * setter, and Kirby's default is UTF-8, which is what every body is in.
 *
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-koyomi
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
final class Output
{
	/** /<calendar>.ics — switch `feed`. Upcoming + events that ended within feedPastDays, cancelled included. Content-Disposition: inline; filename="<slug>.ics". */
	public static function calendarIcs(Page $calendar): string
	{
		$kirby = $calendar->kirby();
		if (!Options::route($kirby, 'feed')) {
			throw new NotFoundException();
		}

		$language = Api::i18n($kirby);
		$now = Options::now($kirby);
		$cutoff = $now->modify('-' . Options::feedPastDays($kirby) . ' days');
		$all = Repository::events($calendar);
		Api::expires($kirby, $all);
		Api::expiresAt($kirby, Expiry::leaving($all, $now, Options::feedPastDays($kirby)));
		$events = Query::sort(array_values(array_filter($all, fn (Event $e): bool => $e->end > $cutoff)), 'asc');

		$response = $kirby->response();
		$response->type('text/calendar');
		$response->header('Content-Disposition', 'inline; filename="' . $calendar->slug() . '.ics"');

		return Ics::calendar($events, self::options($kirby, $language) + [
			'name' => $calendar->title()->value(),
			'timezone' => Options::timezone($kirby),
			'refresh' => 'PT6H',
		]);
	}

	/** /<event>.ics — switch `ics`. text/calendar; charset=utf-8, Content-Disposition: attachment; filename="<slug>.ics". */
	public static function eventIcs(Page $event): string
	{
		$kirby = $event->kirby();
		if (!Options::route($kirby, 'ics') || Mapper::isDraft($event) || $event->intendedTemplate()->name() !== 'koyomi-event') {
			throw new NotFoundException();
		}
		try {
			$item = Mapper::event($event, Options::eventDefaults($kirby));
		} catch (\InvalidArgumentException $e) {
			error_log('Koyomi: skipped ' . $event->id() . ': ' . $e->getMessage());
			throw new NotFoundException();
		}

		$language = Api::i18n($kirby);
		$response = $kirby->response();
		$response->type('text/calendar');
		$response->header('Content-Disposition', 'attachment; filename="' . $event->slug() . '.ics"');
		Api::expires($kirby, [$item]);

		return Ics::event($item, self::options($kirby, $language));
	}

	/** /<calendar>.json — switch `json` (default off). application/json; charset=utf-8. */
	public static function json(Page $calendar): string
	{
		$kirby = $calendar->kirby();
		if (!Options::route($kirby, 'json')) {
			throw new NotFoundException();
		}

		Api::i18n($kirby);
		$now = Options::now($kirby);
		$events = Repository::upcoming($calendar);
		$response = $kirby->response();
		$response->type('application/json');

		return json_encode([
			'calendar' => ['title' => $calendar->title()->value(), 'url' => $calendar->url()],
			'generated' => Time::iso($now),
			'events' => array_map(fn (Event $e): array => $e->toArray(), $events),
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
	}

	/** Host, clock and language shared by the calendar builders. */
	private static function options(App $kirby, string $language): array
	{
		return [
			'host' => parse_url((string)$kirby->url('index'), PHP_URL_HOST) ?: 'localhost',
			'now' => Options::now($kirby),
			'language' => $language,
		];
	}

	/** /<calendar>.rss — switch `rss`. application/rss+xml; charset=utf-8. Upcoming events (hideCancelled respected). */
	public static function rss(Page $calendar): string
	{
		$kirby = $calendar->kirby();
		if (!Options::route($kirby, 'rss')) {
			throw new NotFoundException();
		}

		$language = Api::i18n($kirby);
		$events = Repository::upcoming($calendar);
		$response = $kirby->response();
		$response->type('application/rss+xml');

		return Rss::feed($events, [
			'title' => $calendar->title()->value(),
			'link' => $calendar->url(),
			'self' => $calendar->url() . '.rss',
			'language' => $language,
			'now' => Options::now($kirby),
			'describe' => fn (Event $e): string => trim(Format::when($e) . "\n\n" . $e->description),
		]);
	}
}

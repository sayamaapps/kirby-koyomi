<?php

namespace Sayamaapps\Koyomi;

use DateTimeImmutable;
use Kirby\Cms\App;

/**
 * The calls snippets, templates and site code make. Every method takes the App
 * it works on; nothing here looks the App up itself.
 *
 * These replace the 1.x global functions (koyomi_active(), koyomi_t(), …),
 * which legacy.php still offers with a deprecation.
 *
 * @package   Sayamaapps\Koyomi
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-koyomi
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
final class Api
{
	/** True only when the plugin is switched on (`sayamaapps.koyomi.active`). */
	public static function active(App $kirby): bool
	{
		return Options::active($kirby);
	}

	/**
	 * An Event from whatever a snippet was given: an Event stays, a koyomi-event
	 * page gives its Event (null when its date is invalid), anything else null.
	 */
	public static function event(mixed $value): Event|null
	{
		if ($value instanceof Event) {
			return $value;
		}
		if ($value instanceof Models\EventPage) {
			return $value->koyomi();
		}
		return null;
	}

	/**
	 * Lowers the page cache expiry to the next moment one of these events starts
	 * or ends. Kirby's response only ever lowers the expiry.
	 *
	 * @param list<Event> $events
	 */
	public static function expires(App $kirby, array $events): void
	{
		self::expiresAt($kirby, Expiry::next($events, Options::now($kirby)));
	}

	/** Lowers the page cache expiry to $timestamp. Null changes nothing. */
	public static function expiresAt(App $kirby, int|null $timestamp): void
	{
		if ($timestamp !== null) {
			$kirby->response()->expires($timestamp);
		}
	}

	/**
	 * Sets the language and the date formats for this request and returns the
	 * language code. Every snippet and output calls it before it prints text:
	 * on a multi-language site the language is only known after routing.
	 */
	public static function i18n(App $kirby): string
	{
		$language = Options::language($kirby);
		I18n::setLanguage($language);
		Format::configure(Options::formats($kirby), Options::timezone($kirby));
		return $language;
	}

	/** The current time in the configured zone (`sayamaapps.koyomi.now` pins it). */
	public static function now(App $kirby): DateTimeImmutable
	{
		return Options::now($kirby);
	}

	/** Reads an option: `sayamaapps.koyomi.<key>`, else the 1.x `koyomi.<key>`, else $default. */
	public static function option(App $kirby, string $key, mixed $default = null): mixed
	{
		return Options::get($kirby, $key, $default);
	}

	/** A string of Koyomi in the current language. Call i18n() once before. */
	public static function t(string $key, array $values = []): string
	{
		return I18n::t($key, $values);
	}
}

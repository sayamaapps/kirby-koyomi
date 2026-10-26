<?php

/**
 * Names of the 1.x API, kept until the next major version (README, Deprecations).
 *
 * Loaded once by index.php, after the class map is registered. Class aliases and
 * function wrappers only; no logic here.
 */

class_alias(\Sayamaapps\Koyomi\Attendance::class, 'Koyomi\\Attendance');
class_alias(\Sayamaapps\Koyomi\Availability::class, 'Koyomi\\Availability');
class_alias(\Sayamaapps\Koyomi\Blueprints::class, 'Koyomi\\Blueprints');
class_alias(\Sayamaapps\Koyomi\Event::class, 'Koyomi\\Event');
class_alias(\Sayamaapps\Koyomi\Expiry::class, 'Koyomi\\Expiry');
class_alias(\Sayamaapps\Koyomi\Filters::class, 'Koyomi\\Filters');
class_alias(\Sayamaapps\Koyomi\Format::class, 'Koyomi\\Format');
class_alias(\Sayamaapps\Koyomi\GoogleLink::class, 'Koyomi\\GoogleLink');
class_alias(\Sayamaapps\Koyomi\Hooks::class, 'Koyomi\\Hooks');
class_alias(\Sayamaapps\Koyomi\I18n::class, 'Koyomi\\I18n');
class_alias(\Sayamaapps\Koyomi\Ics::class, 'Koyomi\\Ics');
class_alias(\Sayamaapps\Koyomi\JsonLd::class, 'Koyomi\\JsonLd');
class_alias(\Sayamaapps\Koyomi\Mapper::class, 'Koyomi\\Mapper');
class_alias(\Sayamaapps\Koyomi\Normalize::class, 'Koyomi\\Normalize');
class_alias(\Sayamaapps\Koyomi\Options::class, 'Koyomi\\Options');
class_alias(\Sayamaapps\Koyomi\Organizer::class, 'Koyomi\\Organizer');
class_alias(\Sayamaapps\Koyomi\Output::class, 'Koyomi\\Output');
class_alias(\Sayamaapps\Koyomi\Query::class, 'Koyomi\\Query');
class_alias(\Sayamaapps\Koyomi\Repository::class, 'Koyomi\\Repository');
class_alias(\Sayamaapps\Koyomi\Rss::class, 'Koyomi\\Rss');
class_alias(\Sayamaapps\Koyomi\Status::class, 'Koyomi\\Status');
class_alias(\Sayamaapps\Koyomi\Time::class, 'Koyomi\\Time');
class_alias(\Sayamaapps\Koyomi\VTimezone::class, 'Koyomi\\VTimezone');
class_alias(\Sayamaapps\Koyomi\Venue::class, 'Koyomi\\Venue');
class_alias(\Sayamaapps\Koyomi\Models\CalendarPage::class, 'Koyomi\\Models\\CalendarPage');
class_alias(\Sayamaapps\Koyomi\Models\EventPage::class, 'Koyomi\\Models\\EventPage');
class_alias(\Sayamaapps\Koyomi\Models\OrganizerPage::class, 'Koyomi\\Models\\OrganizerPage');
class_alias(\Sayamaapps\Koyomi\Models\VenuePage::class, 'Koyomi\\Models\\VenuePage');

/*
 * The 1.x global functions. Each one is a wrapper around the Api call that
 * replaces it, and each one logs a deprecation in debug mode. A site that
 * defines a function of the same name keeps its own (the guard).
 */

if (Kirby\Cms\Helpers::hasOverride('koyomi_option') === false) {
	/** Reads an option. Deprecated: use Sayamaapps\Koyomi\Api::option(). */
	function koyomi_option(string $key, mixed $default = null): mixed
	{
		Kirby\Cms\Helpers::deprecated(
			'koyomi_option() is deprecated, use Sayamaapps\Koyomi\Api::option()',
			'koyomi-function'
		);

		return Sayamaapps\Koyomi\Api::option(Kirby\Cms\App::instance(), $key, $default);
	}
}

if (Kirby\Cms\Helpers::hasOverride('koyomi_active') === false) {
	/** Deprecated: use Sayamaapps\Koyomi\Api::active(). */
	function koyomi_active(): bool
	{
		Kirby\Cms\Helpers::deprecated(
			'koyomi_active() is deprecated, use Sayamaapps\Koyomi\Api::active()',
			'koyomi-function'
		);

		return Sayamaapps\Koyomi\Api::active(Kirby\Cms\App::instance());
	}
}

if (Kirby\Cms\Helpers::hasOverride('koyomi_now') === false) {
	/** Deprecated: use Sayamaapps\Koyomi\Api::now(). */
	function koyomi_now(): \DateTimeImmutable
	{
		Kirby\Cms\Helpers::deprecated(
			'koyomi_now() is deprecated, use Sayamaapps\Koyomi\Api::now()',
			'koyomi-function'
		);

		return Sayamaapps\Koyomi\Api::now(Kirby\Cms\App::instance());
	}
}

if (Kirby\Cms\Helpers::hasOverride('koyomi_i18n') === false) {
	/** Deprecated: use Sayamaapps\Koyomi\Api::i18n(). */
	function koyomi_i18n(): string
	{
		Kirby\Cms\Helpers::deprecated(
			'koyomi_i18n() is deprecated, use Sayamaapps\Koyomi\Api::i18n()',
			'koyomi-function'
		);

		return Sayamaapps\Koyomi\Api::i18n(Kirby\Cms\App::instance());
	}
}

if (Kirby\Cms\Helpers::hasOverride('koyomi_t') === false) {
	/** Deprecated: use Sayamaapps\Koyomi\Api::t(). */
	function koyomi_t(string $key, array $values = []): string
	{
		Kirby\Cms\Helpers::deprecated(
			'koyomi_t() is deprecated, use Sayamaapps\Koyomi\Api::t()',
			'koyomi-function'
		);

		return Sayamaapps\Koyomi\Api::t($key, $values);
	}
}

if (Kirby\Cms\Helpers::hasOverride('koyomi_event') === false) {
	/** Deprecated: use Sayamaapps\Koyomi\Api::event(). */
	function koyomi_event(mixed $value): Sayamaapps\Koyomi\Event|null
	{
		Kirby\Cms\Helpers::deprecated(
			'koyomi_event() is deprecated, use Sayamaapps\Koyomi\Api::event()',
			'koyomi-function'
		);

		return Sayamaapps\Koyomi\Api::event($value);
	}
}

if (Kirby\Cms\Helpers::hasOverride('koyomi_expires') === false) {
	/** Deprecated: use Sayamaapps\Koyomi\Api::expires(). */
	function koyomi_expires(array $events): void
	{
		Kirby\Cms\Helpers::deprecated(
			'koyomi_expires() is deprecated, use Sayamaapps\Koyomi\Api::expires()',
			'koyomi-function'
		);

		Sayamaapps\Koyomi\Api::expires(Kirby\Cms\App::instance(), $events);
	}
}

if (Kirby\Cms\Helpers::hasOverride('koyomi_expires_at') === false) {
	/** Deprecated: use Sayamaapps\Koyomi\Api::expiresAt(). */
	function koyomi_expires_at(int|null $timestamp): void
	{
		Kirby\Cms\Helpers::deprecated(
			'koyomi_expires_at() is deprecated, use Sayamaapps\Koyomi\Api::expiresAt()',
			'koyomi-function'
		);

		Sayamaapps\Koyomi\Api::expiresAt(Kirby\Cms\App::instance(), $timestamp);
	}
}

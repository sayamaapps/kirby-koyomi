<?php

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Filesystem\F;

F::loadClasses(require __DIR__ . '/autoload.php', __DIR__);
require_once __DIR__ . '/legacy.php';

use Sayamaapps\Koyomi\Blueprints;
use Sayamaapps\Koyomi\Event;
use Sayamaapps\Koyomi\Expiry;
use Sayamaapps\Koyomi\Format;
use Sayamaapps\Koyomi\Hooks;
use Sayamaapps\Koyomi\I18n;
use Sayamaapps\Koyomi\Models\CalendarPage;
use Sayamaapps\Koyomi\Models\EventPage;
use Sayamaapps\Koyomi\Models\OrganizerPage;
use Sayamaapps\Koyomi\Models\VenuePage;
use Sayamaapps\Koyomi\Options;
use Sayamaapps\Koyomi\Repository;

/**
 * Koyomi (暦, "calendar, almanac") — events for Kirby 5 with reusable venues
 * and organizers, status, online/hybrid attendance, ICS, JSON-LD and RSS.
 *
 * Off by default: without `'koyomi.active' => true` every snippet prints
 * nothing and every feed URL (.ics/.rss/.json) is Kirby's 404. Blueprints,
 * page models and templates are registered regardless — they are inert until
 * a site's blueprint lets editors create a koyomi-calendar page, and Kirby
 * reads this file once per PHP process, so registration must not depend on
 * options.
 *
 * Defaults are passed at each option call (src/Options.php), NOT in an
 * 'options' block (Kirby would file them under sayamaapps.koyomi.*).
 *
 * Nothing here talks to a third party. The online meeting link (`onlineUrl`)
 * never leaves the Panel: Sayamaapps\Koyomi\Event has no such property.
 */

/** Reads `koyomi.<key>`. Also meant for site snippets with their own markup. */
function koyomi_option(string $key, mixed $default = null): mixed
{
    return Options::get(App::instance(), $key, $default);
}

/** `koyomi.active` is a real true. Snippets and outputs check this first. */
function koyomi_active(): bool
{
    return Options::active(App::instance());
}

/** The clock (`koyomi.now` pins it), in the configured zone. */
function koyomi_now(): \DateTimeImmutable
{
    return Options::now(App::instance());
}

/**
 * Sets Koyomi's language and date formats for this request and returns the
 * language code. Every snippet and output calls it before printing text —
 * on a multi-language site the language is only known after routing.
 */
function koyomi_i18n(): string
{
    $kirby = App::instance();
    $language = Options::language($kirby);
    I18n::setLanguage($language);
    Format::configure(Options::formats($kirby), Options::timezone($kirby));
    return $language;
}

/** Koyomi's own string in the current language (call koyomi_i18n() once first). */
function koyomi_t(string $key, array $values = []): string
{
    return I18n::t($key, $values);
}

/**
 * An Event from whatever a snippet was given: an Event stays, a
 * koyomi-event page gives its Event (or null when its date is invalid),
 * anything else null.
 */
function koyomi_event(mixed $value): ?Event
{
    if ($value instanceof Event) {
        return $value;
    }
    if ($value instanceof EventPage) {
        return $value->koyomi();
    }
    return null;
}

/**
 * Lowers the page cache expiry to the next moment one of these events
 * starts or ends (Kirby\Cms\Responder::expires() only ever lowers it).
 *
 * @param list<Event> $events
 */
function koyomi_expires(array $events): void
{
    koyomi_expires_at(Expiry::next($events, Options::now(App::instance())));
}

/** Lowers the page cache expiry to $timestamp; null changes nothing. */
function koyomi_expires_at(?int $timestamp): void
{
    if ($timestamp !== null) {
        App::instance()->response()->expires($timestamp);
    }
}

$koyomiTranslations = [];
foreach (I18n::LANGUAGES as $koyomiLanguage) {
    foreach (I18n::table($koyomiLanguage) as $koyomiKey => $koyomiText) {
        $koyomiTranslations[$koyomiLanguage]['koyomi.' . $koyomiKey] = $koyomiText;
    }
}
unset($koyomiLanguage, $koyomiKey, $koyomiText);

App::plugin('sayamaapps/koyomi', [
    // All strings, also usable as t('koyomi.<key>').
    'translations' => $koyomiTranslations,

    /**
     * Prefixed names, so a site's own "event" or "venue" template never
     * collides. Closures: the select options (zones, currencies, languages)
     * follow the config. A site overrides one with
     * site/blueprints/pages/koyomi-event.yml.
     */
    'blueprints' => [
        'pages/koyomi-calendar' => fn () => Blueprints::calendar(Options::blueprintSettings(App::instance())),
        'pages/koyomi-event' => fn () => Blueprints::event(Options::blueprintSettings(App::instance())),
        'pages/koyomi-venues' => fn () => Blueprints::venues(Options::blueprintSettings(App::instance())),
        'pages/koyomi-venue' => fn () => Blueprints::venue(Options::blueprintSettings(App::instance())),
        'pages/koyomi-organizers' => fn () => Blueprints::organizers(Options::blueprintSettings(App::instance())),
        'pages/koyomi-organizer' => fn () => Blueprints::organizer(Options::blueprintSettings(App::instance())),
    ],

    'pageModels' => [
        'koyomi-calendar' => CalendarPage::class,
        'koyomi-event' => EventPage::class,
        'koyomi-venue' => VenuePage::class,
        'koyomi-organizer' => OrganizerPage::class,
    ],

    /**
     * HTML templates are plain defaults (wrapped in snippet koyomi/layout,
     * which a site overrides to use its own layout). The .ics/.rss/.json
     * templates are Kirby content representations: /events.ics,
     * /events/retreat.ics, /events.rss, /events.json.
     */
    'templates' => [
        'koyomi-calendar' => __DIR__ . '/templates/koyomi-calendar.php',
        'koyomi-calendar.ics' => __DIR__ . '/templates/koyomi-calendar.ics.php',
        'koyomi-calendar.rss' => __DIR__ . '/templates/koyomi-calendar.rss.php',
        'koyomi-calendar.json' => __DIR__ . '/templates/koyomi-calendar.json.php',
        'koyomi-event' => __DIR__ . '/templates/koyomi-event.php',
        'koyomi-event.ics' => __DIR__ . '/templates/koyomi-event.ics.php',
        'koyomi-venue' => __DIR__ . '/templates/koyomi-venue.php',
        'koyomi-organizer' => __DIR__ . '/templates/koyomi-organizer.php',
    ],

    /**
     * Unstyled defaults with koyomi-* classes only. A site overrides any of
     * them with site/snippets/koyomi/<name>.php (Kirby looks there first).
     */
    'snippets' => [
        'koyomi/layout' => __DIR__ . '/snippets/koyomi/layout.php',
        'koyomi/list' => __DIR__ . '/snippets/koyomi/list.php',
        'koyomi/card' => __DIR__ . '/snippets/koyomi/card.php',
        'koyomi/event' => __DIR__ . '/snippets/koyomi/event.php',
        'koyomi/filters' => __DIR__ . '/snippets/koyomi/filters.php',
        'koyomi/switch' => __DIR__ . '/snippets/koyomi/switch.php',
        'koyomi/pagination' => __DIR__ . '/snippets/koyomi/pagination.php',
        'koyomi/past' => __DIR__ . '/snippets/koyomi/past.php',
        'koyomi/venue' => __DIR__ . '/snippets/koyomi/venue.php',
        'koyomi/subscribe' => __DIR__ . '/snippets/koyomi/subscribe.php',
        'koyomi/jsonld' => __DIR__ . '/snippets/koyomi/jsonld.php',
    ],

    // Queries for the Panel pages fields: kirby.collection("koyomi/venues") …
    'collections' => [
        'koyomi/events' => fn () => Repository::eventPages(Repository::calendar(App::instance())),
        'koyomi/venues' => fn () => Repository::venuePages(App::instance()),
        'koyomi/organizers' => fn () => Repository::organizerPages(App::instance()),
    ],

    'siteMethods' => [
        /** The calendar page (`koyomi.calendar`, else the first koyomi-calendar page), or null. */
        'koyomiCalendar' => function (): ?Page {
            return Repository::calendar($this->kirby());
        },
    ],

    'hooks' => [
        /**
         * A copy of an event keeps its dates (it is a draft anyway) but must
         * not inherit "cancelled", "sold out" or a status note.
         */
        'page.duplicate:after' => function (Page $duplicatePage, Page $originalPage) {
            return Hooks::duplicateAfter($duplicatePage, $originalPage);
        },
    ],
]);
unset($koyomiTranslations);

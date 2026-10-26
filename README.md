# Koyomi

Event calendar for [Kirby CMS](https://getkirby.com) 5, with ICS files, feeds and structured data.

Koyomi keeps venues and organizers as pages of their own, so an address changes in one place. Status and attendance are event fields, and the event page, calendar files, feeds and JSON-LD all read the same data. The plugin loads nothing from third parties itself; media an editor embeds from another server is the exception, see [Privacy](#privacy).

## Features

- Venues and organizers as reusable pages, edited once and linked from events.
- Status (cancelled, postponed, rescheduled, moved online) and in-person, online or hybrid attendance.
- Local wall time with an IANA zone; daylight saving time is handled on output.
- ICS download per event, `webcal://` subscription, RSS and an optional JSON feed.
- schema.org `Event` JSON-LD on every event page.
- English and German texts; no cookies, no scripts, no third-party requests from the plugin itself.

## Requirements

| | Supported |
|---|---|
| Kirby | 5 (`getkirby/cms` ^5.0) |
| PHP | 8.2 to 8.5, with `mbstring` |

## Installation

Pick one of three ways. Koyomi works the same in each.

**Composer** (recommended), from the site's root:

```sh
composer require sayamaapps/kirby-koyomi
```

Composer installs to `site/plugins/koyomi/`. Add that path to `.gitignore` and deploy it like `vendor/`.

**Git submodule**, in the site's `site/plugins/` folder:

```sh
git submodule add https://github.com/sayamaapps/kirby-koyomi.git site/plugins/koyomi
```

**ZIP**: download the release archive from the [releases page](https://github.com/sayamaapps/kirby-koyomi/releases) and extract it to `site/plugins/koyomi/`.

Use the release tags (for example `1.4.0`), not the `main` branch, on a live site.

## Setup

Koyomi is off by default. In `site/config/config.php`, switch it on and set the default zone (the 1.x names `koyomi.active` and `koyomi.timezone` still work, see [Deprecations](#deprecations)):

```php
return [
    'sayamaapps.koyomi.active'   => true,
    'sayamaapps.koyomi.timezone' => 'Europe/Berlin', // default zone of new events
];
```

Allow the calendar and the containers in the Panel, in the pages section of `site/blueprints/site.yml`. Events, venues and organizers are created inside their containers, so they need no entry of their own:

```yaml
columns:
  - width: 1/1
    sections:
      pages:
        type: pages
        templates:
          - koyomi-calendar
          - koyomi-venues
          - koyomi-organizers
          # your own templates stay in this list
```

Create the pages in this structure. The calendar is listed; the containers are unlisted.

```
content/
  events/                koyomi-calendar    (listed)
    20270312_retreat/    koyomi-event
  venues/                koyomi-venues      (unlisted)
    gasthaus-muster/     koyomi-venue
  organizers/            koyomi-organizers  (unlisted)
    max-muster/          koyomi-organizer
```

Event folders are named `YYYYMMDD_slug`; the URL drops the date (`/events/retreat`). The calendar's slug sets the feed URLs. A venue or organizer appears on events only when it is published and not below a draft, so publish it and its container first.

The plugin's templates render a complete calendar with filters, event pages, and venue and organizer pages. You need no template of your own to start.

## Usage

Snippets print unstyled markup with `koyomi-*` classes and no scripts:

```php
<?php snippet('koyomi/list', ['list' => $page->koyomiList()]) ?>
<?php snippet('koyomi/event', ['event' => $page]) ?>
```

Override a snippet with a file at the same path under `site/snippets/`, for example `site/snippets/koyomi/card.php`. A custom `koyomi/layout` must print `snippet('koyomi/jsonld', ['page' => $page])` in its `<head>`, or event pages lose their JSON-LD.

Fields, snippets, page methods and helpers are listed in [docs/reference.md](docs/reference.md).

### Feeds

| URL | Option | Content |
|---|---|---|
| `/events.ics` | `feed` | Subscription feed: upcoming events and recently ended ones. Cancelled events stay, marked `CANCELLED`. |
| `/events/<slug>.ics` | `ics` | One event as a download. |
| `/events.rss` | `rss` | RSS 2.0 with upcoming events. |
| `/events.json` | `json` | Upcoming events as JSON. Off by default. |

A switched-off output returns Kirby's 404 page, and `sayamaapps.koyomi.active` switches all of them off. The `koyomi/subscribe` snippet shows the `webcal://` address.

## Options

Every option is `sayamaapps.koyomi.<name>`, flat or nested. `null` means "not set". The 1.x spelling `koyomi.<name>` is read when the new name is not set, with a deprecation in debug mode (see [Deprecations](#deprecations)).

| Option | Default | Description |
|---|---|---|
| `active` | `false` | Master switch. Only a real `true` enables Koyomi. |
| `timezone` | `Europe/Berlin` | Default zone of new events and the site zone. |
| `timezones` | all PHP zones | Zones offered in the Panel, as a list of IANA names. |
| `currency` | first of `currencies` | Default currency of new events. |
| `currencies` | `['EUR', 'CZK', 'CHF']` | Currencies offered in the Panel. |
| `country` | `null` | Home country as ISO code, for example `DE`. Addresses omit it. |
| `languages` | `['de', 'en', 'cs']` | Event languages offered in the Panel and the filter. A list, or a map of code to label. |
| `language` | site language | Language of snippets, feeds and calendar files: `en` or `de`. |
| `perPage` | `12` | Events per list page, 1 to 100. |
| `defaultDuration` | `120` | Minutes an event without an end time lasts. |
| `feedPastDays` | `30` | Days after its end an event stays in the `.ics` feed. |
| `ics` | `true` | Per-event `.ics` download. |
| `feed` | `true` | Subscription feed `/<calendar>.ics`. |
| `rss` | `true` | RSS feed `/<calendar>.rss`. |
| `json` | `false` | JSON feed `/<calendar>.json`. |
| `google` | `true` | "Google Calendar" link on upcoming events. |
| `groupBy` | `'month'` | `'month'` groups lists under month headings; `false` prints one flat list. |
| `attendanceSwitch` | `true` | "All · In person · Online" switch above the calendar list. |
| `hideCancelled` | `false` | Leave cancelled events out of the upcoming list and the RSS feed. |
| `duplicateReset` | `true` | A duplicate loses its status, previous date, status note and availability. |
| `formats` | `[]` | Date and time patterns per language, for example `['de' => ['time' => 'G.i']]`. See [docs/reference.md](docs/reference.md#date-formats). |
| `mapUrl` | `null` | Closure `fn (Sayamaapps\Koyomi\Venue $venue): string` that replaces the OpenStreetMap link. |
| `calendar` | first `koyomi-calendar` | Page ID of the calendar used by snippets and feeds. |
| `venues` | first `koyomi-venues` | Page ID of the venues container. |
| `organizers` | first `koyomi-organizers` | Page ID of the organizers container. |
| `now` | real time | Pins the clock, for example `'2027-03-10 12:00'`, for tests and previews. |

## Deprecations

Koyomi 1.x names keep working until the next major version. Each one logs a deprecation when Kirby's debug mode is on. Move to the new name when you touch the site.

| 1.x | Use instead |
|---|---|
| Option `koyomi.<name>` | `sayamaapps.koyomi.<name>`, same values. |
| `koyomi_option($name, $default)` | `Sayamaapps\Koyomi\Api::option($kirby, $name, $default)` |
| `koyomi_active()` | `Sayamaapps\Koyomi\Api::active($kirby)` |
| `koyomi_now()` | `Sayamaapps\Koyomi\Api::now($kirby)` |
| `koyomi_i18n()` | `Sayamaapps\Koyomi\Api::i18n($kirby)` |
| `koyomi_t($key, $values)` | `Sayamaapps\Koyomi\Api::t($key, $values)` |
| `koyomi_event($value)` | `Sayamaapps\Koyomi\Api::event($value)` |
| `koyomi_expires($events)` | `Sayamaapps\Koyomi\Api::expires($kirby, $events)` |
| `koyomi_expires_at($timestamp)` | `Sayamaapps\Koyomi\Api::expiresAt($kirby, $timestamp)` |
| Classes `Koyomi\…`, for example `Koyomi\Event` | `Sayamaapps\Koyomi\…`, same names |

The functions are wrappers: a site that defines a function of the same name keeps its own. Blueprint, snippet, template, page method and route names do not change, and neither do the feed URLs or the stored content.

## Privacy

Koyomi processes no visitor data: no cookies, no browser storage, no third-party requests from the plugin. The "Google Calendar" link is an ordinary link; Google receives event data only when a visitor follows it. The online meeting link (`onlineUrl`) is never output, so no page or feed contains it.

The exception is media in an event's text. An image or video block with an address on another server loads from there when the page opens, and a video embed runs that provider's script. Upload images to the site instead, or leave such blocks out. Without them there is nothing to add to a site's privacy policy.

## Changes by chat (Fude)

With the separate package [Fude](https://github.com/sayamaapps/kirby-fude) 1.7 or later, a chat bot can create and change events. Koyomi's blueprints mark the fields and the calendar's events section, so each calendar is one Fude collection without a line in `site/config/fude.php`; an event is found by its start date. Date, times, venue, online link, price, booking link, status and short text are reachable; the full text (`blocks`), categories (`tags`), languages (`multiselect`), organizers and series (several pages) are not, because Fude cannot set those types, and neither are the cover and the other fields. Details: [docs/reference.md](docs/reference.md#fude). Without Fude, the `fude` and `fudeAsk` keys do nothing.

## Maintenance

Tests: `composer test`. Issues and pull requests are welcome, without a support promise. Report security problems privately through GitHub's vulnerability reporting. Changes: [CHANGELOG.md](CHANGELOG.md).

## License

MIT, see [LICENSE](LICENSE).

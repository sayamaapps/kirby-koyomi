# Koyomi

Event calendar for [Kirby CMS](https://getkirby.com) 5, with ICS files, feeds and structured data.

Koyomi keeps venues and organizers as pages of their own, so an address changes in one place. Status and attendance are event fields, and the event page, calendar files, feeds and JSON-LD all read the same data. Nothing is loaded from third parties.

## Features

- Venues and organizers as reusable pages, edited once and linked from events.
- Status (cancelled, postponed, rescheduled, moved online) and in-person, online or hybrid attendance.
- Local wall time with an IANA zone; daylight saving time is handled on output.
- ICS download per event, `webcal://` subscription, RSS and an optional JSON feed.
- schema.org `Event` JSON-LD on every event page.
- English and German texts; no cookies, no scripts, no third-party requests.

## Requirements

- PHP 8.2 to 8.4 with `mbstring`
- Kirby 5

## Installation

```sh
composer require sayamaapps/kirby-koyomi
```

Composer installs to `site/plugins/koyomi/`. Add that path to `.gitignore` and deploy it like `vendor/`. Copying the folder there by hand works too.

## Setup

Koyomi is off by default. In `site/config/config.php`:

```php
return [
    'koyomi.active'   => true,
    'koyomi.timezone' => 'Europe/Berlin', // default zone of new events
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

A switched-off output returns Kirby's 404 page, and `koyomi.active` switches all of them off. The `koyomi/subscribe` snippet shows the `webcal://` address.

## Options

Every option is `koyomi.<name>`, flat or nested. `null` means "not set".

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
| `mapUrl` | `null` | Closure `fn (Koyomi\Venue $venue): string` that replaces the OpenStreetMap link. |
| `calendar` | first `koyomi-calendar` | Page ID of the calendar used by snippets and feeds. |
| `venues` | first `koyomi-venues` | Page ID of the venues container. |
| `organizers` | first `koyomi-organizers` | Page ID of the organizers container. |
| `now` | real time | Pins the clock, for example `'2027-03-10 12:00'`, for tests and previews. |

## Privacy

Koyomi processes no visitor data: no cookies, no browser storage, no third-party requests. The "Google Calendar" link is an ordinary link; Google receives event data only when a visitor follows it. The online meeting link (`onlineUrl`) is never output, so no page or feed contains it.

## Changes by chat (Fude)

With the separate package [Fude](https://github.com/sayamaapps/kirby-fude) 1.7 or later, a chat bot can create and change events. Koyomi's blueprints mark the fields and the calendar's events section, so each calendar is one Fude collection without a line in `site/config/fude.php`; an event is found by its start date. Date, times, venue, online link, price, booking link, status and short text are reachable; the full text (`blocks`), categories (`tags`), languages (`multiselect`), organizers and series (several pages) are not, because Fude cannot set those types, and neither are the cover and the other fields. Details: [docs/reference.md](docs/reference.md#fude). Without Fude, the `fude` and `fudeAsk` keys do nothing.

## Maintenance

Tests: `composer test`. Issues and pull requests are welcome, without a support promise. Report security problems privately through GitHub's vulnerability reporting. Changes: [CHANGELOG.md](CHANGELOG.md).

## License

MIT, see [LICENSE](LICENSE).

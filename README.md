# Koyomi

An event calendar for [Kirby CMS](https://getkirby.com) 5: events with reusable
venues and organizers, status (cancelled, postponed, rescheduled, moved online),
in-person, online and hybrid attendance, ICS download and subscription,
"Add to Google Calendar", JSON-LD for search engines, an RSS feed and an optional
JSON feed.

*Koyomi* (暦) is Japanese for calendar, almanac. The plugin shows dates and
places. The visitor's browser never talks to a third party.

## Why

Event pages on a small site tend to repeat the same venue address and the same
teacher's name on every date, and they rarely say whether an event was
cancelled, moved or now takes place online. Koyomi keeps venues and organizers
as pages of their own, so an editor picks them from a list and changes an
address once. Status and attendance are first-class fields, and the same data
drives the page, the calendar files, the feeds and the structured data, so they
cannot disagree.

Koyomi was built for a site with workshops, multi-day retreats, online sessions
and concerts.

- **Reusable venues and organizers.** A venue or organizer is a page. Events
  link to it, so an address, a map image or a photo lives in one place.
- **Status and attendance.** Cancelled, postponed, rescheduled and moved-online
  events say so on the page, in the feeds and in the calendar files. An event
  can be in person, online or hybrid.
- **Time done right.** Times are stored as local wall time with an IANA zone
  (`Europe/Berlin`), so daylight saving time is handled at output. Ongoing events
  stay listed as upcoming.
- **Calendar files and feeds.** Each event downloads as `.ics`. The calendar can
  be subscribed to as `webcal://` address, read as RSS, and optionally read as JSON.
- **Structured data.** Each event page carries schema.org `Event` JSON-LD with
  the right attendance mode, status, location, offers and organizer.
- **No third-party requests.** No map embed, no font, no script, no tracking.
  The map is a self-hosted image and an OpenStreetMap link that opens on click.
  The Google Calendar link is a plain link that opens on click. Media an editor
  inserts from another server (a web image or a video block in the text, an
  external image in the intro) are the site's content and load from that server when the page opens.
- **The online meeting link stays private.** It is stored in the Panel and
  nowhere else. Koyomi's event object has no such field, so no output can show it.
- **English and German.** Snippets and feeds follow the site language. Panel
  labels are translated too.

## Requirements

- PHP 8.2 to 8.4 with `mbstring`
- Kirby 5

## Installation

```sh
composer require sayamaapps/kirby-koyomi
```

Composer installs the plugin into `site/plugins/koyomi/` through
`getkirby/composer-installer`. Make sure your deploy ships `site/plugins/` the way
it ships `vendor/`.

Until the package is on Packagist, add the repository first:

```json
"repositories": [{ "type": "vcs", "url": "https://github.com/sayamaapps/kirby-koyomi" }]
```

Manual installation (copy the folder into `site/plugins/koyomi/`) works too.

## Setup

### 1. Switch it on

Koyomi is off by default. In `site/config/config.php`:

```php
return [
    'koyomi.active'   => true,
    'koyomi.timezone' => 'Europe/Berlin',   // default zone of new events
];
```

Flat keys (as above) and nested arrays (`'koyomi' => ['active' => true]`) both
work. See [Options](#options).

### 2. Allow the templates in the Panel

Editors can only create the pages your site blueprint allows. Add the calendar,
venues and organizers templates to the pages section of `site/blueprints/site.yml`:

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

The event, venue and organizer templates are created inside their containers,
so they need no entry of their own.

### 3. Create the pages

Use the recommended structure. The calendar is listed; the containers are
unlisted, so they never appear in navigation or lists.

```
content/
  events/                       koyomi-calendar   (listed)
    20270312_silent-retreat/    koyomi-event      (listed)
    20270320_breathing/         koyomi-event      (listed)
  venues/                       koyomi-venues     (unlisted)
    beispielhof/                koyomi-venue      (unlisted)
  organizers/                   koyomi-organizers (unlisted)
    anna/                       koyomi-organizer  (unlisted)
```

- The calendar's slug is what the feed URLs use: `/events`, `/events.ics`,
  `/events.rss`. Use another slug if you like; the URLs follow it.
- Event folders are named `YYYYMMDD_slug`. The date prefix sorts them, and the
  slug (without the date) is the URL: `/events/silent-retreat`.
- Venue and organizer pages are unlisted: they have a URL for linking and
  feeds, but no navigation shows them. New ones are created unlisted, not as
  drafts. An event shows a venue or organizer only when the page is not a draft
  and does not sit below a draft (such as a "Venues" page put back into draft),
  so publish a draft venue or organizer, and its container, before you link it.
  The same holds for the other parts of a series: a draft part, or one below a
  draft, is left out of the series navigation until it is published.
- Events, venues and organizers are plain Kirby pages with the templates
  `koyomi-event`, `koyomi-venue` and `koyomi-organizer`. Kirby stores the content
  of a page in `<template>.txt`, or `<template>.<language>.txt` on a multilingual
  site, for example `koyomi-event.de.txt`.

The plugin's own templates render a complete calendar: the list with filters,
the event pages, venue and organizer pages. You need no template of your own to
start.

## Options

Every option is read as `koyomi.<name>`. `null` counts as "not set" and gives the
default.

| Option | Default | Meaning |
|---|---|---|
| `active` | `false` | Master switch. Only a real `true` switches it on. Off: snippets print nothing, and the `.ics`, `.rss` and `.json` URLs are 404. |
| `timezone` | `Europe/Berlin` | Default zone of new events, the site zone for the zone hint in dates, and the zone of `now`. An invalid name falls back to `Europe/Berlin`. |
| `timezones` | all PHP zone identifiers | Zones offered in the Panel select, as a list of IANA names. The default zone is always included. |
| `currency` | first of `currencies` (`EUR`) | Default currency of new events. |
| `currencies` | `['EUR', 'CZK', 'CHF']` | Currencies offered in the Panel select. |
| `languages` | `['de', 'en', 'cs']` | Event languages offered in the Panel and in the language filter. A list (`['de', 'cs']`) or a map of code to label (`['cs' => 'Čeština']`). Without a label the translated language name is used. |
| `language` | site language | Language of snippets, feeds and calendar file texts. Koyomi ships English and German only; any other value is skipped. Order: this option, the current site language, the default language, the `locale` option, English. |
| `perPage` | `12` | Events per list page, between 1 and 100. |
| `defaultDuration` | `120` | Minutes an event without an end time lasts, as elapsed time (across a DST change too). |
| `feedPastDays` | `30` | Days after its end an event stays in the `.ics` subscription feed. |
| `ics` | `true` | Per-event download, `/<calendar>/<event>.ics`. |
| `feed` | `true` | Subscription feed, `/<calendar>.ics`, offered as `webcal://` link. |
| `rss` | `true` | RSS feed, `/<calendar>.rss`. |
| `json` | `false` | JSON feed, `/<calendar>.json`. Off by default. |
| `google` | `true` | "Google Calendar" link on the event page. |
| `groupBy` | `'month'` | Layout of `koyomi/list`. `'month'` is the timeline: a "today" line, then one heading per month with its events, each card with a calendar-sheet date. `false` prints one flat list. |
| `attendanceSwitch` | `true` | The switch "All · In person · Online" above the calendar list. A hybrid event counts as both. With the switch on, the filter form leaves out its format select. |
| `hideCancelled` | `false` | Leave cancelled events out of the upcoming list and the RSS feed. The `.ics` feed keeps them, marked as cancelled. |
| `duplicateReset` | `true` | When an event is duplicated, clear its status, previous date, status note and availability. Dates are kept, and the copy is a draft. |
| `formats` | `[]` | Date and time patterns per language, merged over Koyomi's defaults, for example `['de' => ['time' => 'G.i']]`. Range patterns are two-element lists. See [Date formats](#date-formats). |
| `country` | `null` | Home country as ISO code, for example `'DE'`. Venue addresses on pages leave this country out and name every other one: "Musterstadt, Czechia". Unset: every address names its country. |
| `mapUrl` | `null` | A closure `fn (Koyomi\Venue $venue): string` that replaces the OpenStreetMap link, for example `fn ($venue) => 'https://maps.example.test/?q=' . urlencode($venue->label())`. |
| `calendar` | first `koyomi-calendar` page | Page id of the calendar that snippets and feeds use. |
| `venues` | first `koyomi-venues` page | Page id of the venues container. |
| `organizers` | first `koyomi-organizers` page | Page id of the organizers container. |
| `now` | real time | Pins the clock, for tests and for previewing a calendar as it will look on a given day. A string `'2027-03-10 12:00'`, a `DateTimeInterface`, or a closure returning either. |

### Date formats

`formats` changes the patterns Koyomi prints. Each language has its own keys; a
key you leave out keeps Koyomi's default. Range keys take two patterns, the start
and the end of the range.

| Key | en default | de default | Used for |
|---|---|---|---|
| `date` | `l, j F Y` | `l, j. F Y` | one day: "Friday, 12 March 2027" |
| `time` | `H:i` | `H:i` | a time: "18:00" (the German "Uhr" comes from the translations) |
| `rangeSameMonth` | `j`, `j F Y` | `j.`, `j. F Y` | days of one month: "8–11 March 2027" |
| `rangeSameMonthSep` | `–` | `–` | between the two days of `rangeSameMonth` |
| `rangeSameYear` | `j F`, `j F Y` | `j. F`, `j. F Y` | months of one year: "28 March – 2 April 2027" |
| `rangeOther` | `j F Y`, `j F Y` | `j. F Y`, `j. F Y` | across years: "30 December 2027 – 2 January 2028" |
| `rangeSep` | ` – ` | ` – ` | between the two parts of the other ranges |

`dateShort` and `dateNoWeekday` are also in Koyomi's defaults, but no output uses
them yet, so setting them has no effect.

Tokens, in any pattern:

| Token | Prints |
|---|---|
| `d` `j` | day of the month, with or without leading zero |
| `m` `n` | month, with or without leading zero |
| `Y` `y` | year, four or two digits |
| `H` `G` | hour (24 h), with or without leading zero |
| `i` `s` | minutes, seconds |
| `F` `M` | month name, short month name (from the translations) |
| `l` `D` | weekday name, short weekday name (from the translations) |
| `\x` | the character `x` as it is, for example `\T` |

Every other character is copied as it is. For example `['de' => ['time' => 'G.i']]`
prints `18.00`, and `['en' => ['date' => 'D, j M Y']]` prints "Fri, 12 Mar 2027".

## Fields

The Panel stores these fields. Read them in your own code with
`$page->content()->get('<field>')`; Koyomi itself uses the same names.

### Event (`koyomi-event`)

| Field | Panel type | Meaning |
|---|---|---|
| `title` | title | Name of the event. |
| `excerpt` | textarea | Short description, plain text. Used in lists, feeds, calendar files and search results. When empty, the start of the text is used (the first 300 characters of the text blocks). |
| `text` | blocks | The full description. Shown on the event page only. |
| `cover` | file | Cover image. Its alt text is used too. |
| `startDate` | date | First day. Required. |
| `startTime` | time | Start time. Required unless `allDay`. |
| `endDate` | date | Last day. Empty for a single day. |
| `endTime` | time | End time. Empty for an open end, see [Time zones and daylight saving time](#time-zones-and-daylight-saving-time). Asked by Fude. |
| `allDay` | toggle | All-day event. Start and end are dates, and the times are hidden. |
| `timezone` | select | IANA zone of the wall times above, for example `Europe/Berlin`. |
| `inPerson` | toggle (default on) | The event takes place in person. Requires a `venue`. |
| `online` | toggle (default off) | The event takes place online, or online as well as in person. |
| `venue` | page | The venue, from the venues container. Required when `inPerson` is on. |
| `onlinePlatform` | text | Name of the platform, for example `Zoom`. Shown publicly ("Online via Zoom"). |
| `onlineUrl` | url | The meeting link. Required when `online` is on. Panel only: never shown, never in a feed, never in a calendar file. Send it to participants yourself. |
| `eventStatus` | select | `scheduled`, `cancelled`, `postponed`, `rescheduled` or `movedOnline`. |
| `previousStart` | date with time | Original start, shown when the event is `rescheduled`. |
| `statusNote` | textarea | Note on the status, for example the reason for a cancellation. |
| `isFree` | toggle | Free of charge. Hides the price fields. |
| `price` | number | Price in `currency`. Asked by Fude. |
| `currency` | select | Currency of `price`. |
| `priceNote` | text | Note on the price, for example "early bird until 1 March". |
| `availability` | select | `available`, `limited` (only a few places left) or `soldout`. |
| `bookingUrl` | url | Link where people register or buy. It leads to an external provider. Asked by Fude. |
| `bookingLabel` | text | Text of the booking button. Defaults to "Register" / "Zur Anmeldung". |
| `organizers` | pages | One or more organizers, from the organizers container. |
| `categories` | tags | Free categories, for example `Retreat, Meditation`. Used for the filter. |
| `eventLanguages` | multiselect | Languages the event is held in. Used for the filter. |
| `featured` | toggle | Marks the event as featured. Used for the filter. |
| `series` | pages | Other parts of the same series. Sibling events of the same calendar. |
| `seriesPart` | number | This event's part number in the series, for example `2`. |

The names are chosen with care. Kirby's page object already has a `status()`
method (draft, listed, unlisted) and an `image()` method. A content field of the
same name would be hidden behind them in templates, which is why the event status
is `eventStatus`, and the organizer picture is `photo`. Languages are
`eventLanguages`, so they never clash with a site's own `languages`.

**Attendance.** Kirby's `when` condition can only test for equality, so the venue
rule ("required for in-person events") needs two toggles, `inPerson` and `online`.
Koyomi derives the attendance mode from them: in person only, online only, or
mixed (hybrid). A site blueprint may store an `attendance` field with one of the
values `offline`, `online` or `mixed` instead; a valid value there wins. The
status `movedOnline` makes the effective attendance online.

### Venue (`koyomi-venue`)

| Field | Panel type | Meaning |
|---|---|---|
| `title` | title | Name of the venue. |
| `street`, `postalCode`, `city` | text | Address. Required: an in-person event needs one. |
| `region` | text | Region or state, optional. |
| `country` | text, two letters | ISO country code, for example `DE`. Pages show the country by name ("Germany", "Deutschland"), and leave it out for the `country` option. JSON-LD and calendar files keep the code. |
| `lat`, `lng` | number | Coordinates. Used for the OpenStreetMap link and for the location in JSON-LD and calendar files. |
| `mapImage` | file | A static map image you upload. The plugin never loads maps from third parties. |
| `showMap` | toggle (default off) | Shows `mapImage` on the venue page. Event pages show the venue name and address only, without the map image. |
| `website` | url | The venue's website. |
| `directions` | textarea | How to get there. |

### Changes by chat (Fude)

With the separate package [Fude](https://github.com/sayamaapps/kirby-fude) (1.6 or
later), a site can let a chat bot create and change events. Koyomi says what the
bot needs; the site declares which fields it may write. Fude reads `required`,
`when` and `fudeAsk` from these blueprints. Without Fude the key `fudeAsk` does
nothing.

| | Event | Venue |
|---|---|---|
| **Required** | `title`, `startDate`, `startTime` (unless `allDay`), `venue` (in person), `onlineUrl` (online), `eventStatus` (has a default) | `title`, `street`, `postalCode`, `city` |
| **Asked** (once, "don't know" is fine) | `endTime`, `price` (unless `isFree`), `bookingUrl` | — |
| **Free** | everything else | everything else |

A site may be stricter (an end time required for all its events) in its Fude
declaration, never looser.

### Organizer (`koyomi-organizer`)

| Field | Panel type | Meaning |
|---|---|---|
| `title` | title | Name of the person or organization. |
| `kind` | select | `person` or `organization` (default). Sets the schema.org type. |
| `email` | email | Public contact address. Used only when `showEmail` is on. |
| `showEmail` | toggle (default off) | Publishes `email` on the organizer's page, in the event page and in calendar files. |
| `website` | url | Website. Used as the organizer's link. |
| `photo` | file | Photo or logo. |

### Calendar (`koyomi-calendar`)

| Field | Panel type | Meaning |
|---|---|---|
| `title` | title | Heading of the calendar. Also the name in the calendar subscription and the RSS channel. |
| `intro` | textarea | Short introduction shown above the list. |

## Outputs and URLs

With the calendar at `/events`, the plugin serves these addresses. Each one is a
Kirby content representation of the page: no route of your own is needed.

| URL | Switch | Content type | Content |
|---|---|---|---|
| `/events.ics` | `feed` | `text/calendar; charset=UTF-8` | Subscription feed: upcoming events and those that ended within `feedPastDays`. Cancelled events stay in, marked `CANCELLED`. Served inline, so calendar apps can subscribe to it. |
| `/events/<slug>.ics` | `ics` | `text/calendar; charset=UTF-8` | One event, as a download (`Content-Disposition: attachment`). |
| `/events.rss` | `rss` | `application/rss+xml; charset=UTF-8` | RSS 2.0 with the event module, upcoming events. Cancelled events are prefixed in the title, unless `hideCancelled` removes them. |
| `/events.json` | `json` | `application/json; charset=UTF-8` | Upcoming events as JSON. Off by default. |

A switched-off output answers with Kirby's normal 404 page. Turning off
`active` switches all of them off at once.

The subscription address for calendar apps is the calendar URL with `webcal://`
instead of `https://`, for example `webcal://example.test/events.ics`. The
`koyomi/subscribe` snippet shows it, with a short note for visitors.

The event `.ics` file contains: title, date and time in the event's zone (or as
`DATE` values for all-day events), location, description with a link to the
event page, status, and the organizer's public address when `showEmail` allows it.
An event whose status is cancelled or postponed carries that in the summary.

### Add to Google Calendar

The event page links to Google Calendar with a pre-filled event: title, time,
zone, location, a shortened description and the event URL. The link is a plain
URL. Google receives the data only when a visitor follows it. The link appears
on upcoming events that are not cancelled, and only when `google` is on. Turn it
off with `koyomi.google` => `false`.

## Snippets

The plugin's snippets print unstyled markup with `koyomi-*` classes only, no
inline styles and no scripts. Call them from a template:

```php
<?php snippet('koyomi/list', ['list' => $page->koyomiList()]) ?>
<?php snippet('koyomi/event', ['event' => $page]) ?>
```

| Snippet | Data | Prints |
|---|---|---|
| `koyomi/layout` | `$page`, `$slot` | A minimal HTML document with the `<title>`, language and `<main class="koyomi-main">`. In the `<head>` it adds the JSON-LD on event pages. It prints its slot even when Koyomi is off, so the page is never blank. |
| `koyomi/list` | `$list` or `$events`, `$title` (text or `false`), `$level` (default 2), `$group` (`'month'` or `false`, default from `groupBy`) | The events as a timeline: in the upcoming view a `.koyomi-today` line, then a `.koyomi-month` section per month (heading at `$level + 1`, cards at `$level + 2`). With `$group` false one flat list (cards at `$level + 1`). Also the empty message, and a switch between upcoming and past events when `$list` is given. |
| `koyomi/card` | `$event`, `$level` (default 3) | One event as a card: calendar-sheet date (`.koyomi-card-date` with weekday, day, month and, across months, "until …"), title link, full date and time (`.koyomi-card-when`), time of day alone (`.koyomi-card-time`), status, place, price, availability and excerpt. The sheet date and the time of day are `aria-hidden`; `.koyomi-card-when` carries the full text for screen readers, so a timeline may hide it visually. |
| `koyomi/switch` | `$list` or `$calendar` | The attendance switch "All · In person · Online" as links with counts, the active one with `aria-current="page"`. A hybrid event counts under both, so nobody has to enter a hybrid event twice. Prints nothing when the view has no in-person or no online event. |
| `koyomi/event` | `$event` or `$page`, `$calendarUrl` | The facts of one event: status, previous date, when, where, price, languages, organizers, categories, booking, calendar links and series navigation. |
| `koyomi/past` | `$event`, `$calendarUrl` | A note on past events with a link to the upcoming ones. Prints nothing for upcoming events. |
| `koyomi/venue` | `$venue` (a venue or venue page), `$compact` | Name and address. With `$compact` off also the directions and the map image (when `showMap` is on). The map and website links are printed in both modes. |
| `koyomi/filters` | `$list` or `$calendar`, `$attendance` (default `true`) | The filter form: category, format, language and featured, as a plain GET form. With `$attendance` false the format select is left out (the switch shows it) and a chosen format is kept as a hidden field. |
| `koyomi/pagination` | `$list` or `$calendar` | Previous and next links, only when there is more than one page. |
| `koyomi/subscribe` | `$calendar` (defaults to the site's calendar) | The `webcal://` subscription link and the RSS link, as far as their switches are on. |
| `koyomi/jsonld` | `$event` or `$page` | The `<script type="application/ld+json">` block for an event. |

Snippets that take an event accept an `Event` object or an event page.

The booking link carries a `koyomi-sr-only` span ("opens in a new tab"). The
plugin ships no CSS, so the site must hide that span visually in its own
stylesheet. Without the rule the text shows.

### Overriding a snippet

Kirby looks in the site first. A file in `site/snippets/koyomi/<name>.php`
replaces the plugin's snippet of that name, for example
`site/snippets/koyomi/card.php`. To use your own layout, override
`site/snippets/koyomi/layout.php`; it is the one snippet that is meant to be
replaced wholesale. Your layout must print the structured data in `<head>`, or
the event pages lose their JSON-LD (the other pages print nothing for it):

```php
<head>
  <?php snippet('koyomi/jsonld', ['page' => $page]) ?>
</head>
```

To change a page, override the template: `site/templates/koyomi-event.php`,
`koyomi-calendar.php`, `koyomi-venue.php` or `koyomi-organizer.php`. Your own
`koyomi-event.php` keeps the plugin's `.ics` file working. To change what the
Panel offers, override the blueprint, for example
`site/blueprints/pages/koyomi-event.yml`.

## Your own markup

Use the snippets when you can. When you need your own markup, the page models
give you plain arrays.

### An event page: `$page->koyomiData()`

```php
<?php $event = $page->koyomiData(); ?>
<?php if ($event !== []): ?>
  <h1><?= esc($event['title']) ?></h1>
  <p><?= esc($event['labels']['when']) ?></p>
  <p><?= esc($event['labels']['status']) ?></p>
  <?php if ($event['venue'] !== null): ?>
    <p><?= esc($event['venue']['address']) ?></p>
  <?php endif ?>
  <?php if ($event['isOngoing']): ?>
    <p>Happening now</p>
  <?php endif ?>
<?php endif ?>
```

`koyomiData()` returns `[]` when the start date is invalid. Otherwise it is
`Event::toArray()` plus these keys:

```php
[
    'labels' => [                                  // human-readable texts, in the site language
        'when' => 'Friday, 12 March 2027, 18:00 – Sunday, 14 March 2027, 13:00',
        'dateRange' => '…', 'status' => '', 'previousStart' => '',
        'attendance' => 'Hybrid: in person and online via Zoom', 'availability' => 'Only a few places left',
        'price' => '€380', 'series' => 'Part 2 of 3', 'languages' => 'German, Czech',
    ],
    'isPast' => false,
    'isOngoing' => false,
    'googleCalendarUrl' => 'https://calendar.google.com/…' | '',
]
```

### The event array: `Event::toArray()`

This array is the basis of the JSON feed and of `koyomiData()`. It contains no
online meeting link, because the event object has no such field.

```php
[
    'id' => 'evt-retreat', 'title' => 'Silent Retreat', 'url' => 'https://example.test/events/silent-retreat',
    'start' => '2027-03-12T18:00:00+01:00', 'end' => '2027-03-14T13:00:00+01:00',   // all-day: dates
    'firstDay' => '2027-03-12', 'lastDay' => '2027-03-14',                           // inclusive
    'allDay' => false, 'hasEndTime' => true, 'multiDay' => true, 'timezone' => 'Europe/Berlin',
    'description' => 'Three days of silence.', 'image' => 'https://example.test/media/cover.jpg', 'imageAlt' => '',
    'attendance' => 'mixed', 'effectiveAttendance' => 'mixed', 'onlinePlatform' => 'Zoom',
    'venue' => ['id' => 'venue-beispielhof', 'name' => 'Seminarhaus Beispielhof', 'address' => 'Musterstraße 1, 12345 Musterstadt, DE', /* … */],
    'status' => 'scheduled', 'previousStart' => null, 'statusNote' => '',
    'isFree' => false, 'price' => 380.0, 'currency' => 'EUR', 'priceNote' => '',
    'availability' => 'limited', 'bookingUrl' => 'https://booking.example/retreat', 'bookingLabel' => '',
    'organizers' => [['id' => 'org-anna', 'name' => 'Anna Beispiel', 'kind' => 'person', /* … */]],
    'categories' => ['Retreat', 'Meditation'], 'languages' => ['de', 'cs'], 'featured' => true,
    'series' => [], 'seriesPart' => null, 'seriesTotal' => 0,
    'icsUrl' => 'https://example.test/events/silent-retreat.ics', 'modified' => 1791000000,
]
```

`venue` is `null` unless the event takes place in person.

### A calendar: `$page->koyomiList()`

```php
$list = $page->koyomiList();                       // the request's query string applies
// or: $page->koyomiList(['view' => 'past', 'page' => '2'])

$list['events'];        // list of Koyomi\Event objects, the current page only
$list['pagination'];    // ['page' => 1, 'pages' => 1, 'total' => 7, 'perPage' => 12,
                        //  'hasPrev' => false, 'hasNext' => false, 'prevUrl' => null, 'nextUrl' => null]
$list['facets'];        // categories, attendance, languages and featured counts for the view
$list['view'];          // 'upcoming' or 'past'
$list['filtered'];      // true when a filter is active
$list['upcomingUrl'];   // $list['pastUrl'] is the same calendar with ?view=past
```

For the sorted list without paging, use `$page->koyomiUpcoming()` and
`$page->koyomiPast()`; both return a list of `Event` objects.

## Helpers

These are global functions, usable in templates and in your own snippets.

| Helper | Returns |
|---|---|
| `koyomi_option('name', $default)` | The value of `koyomi.name`. |
| `koyomi_active()` | Whether Koyomi is switched on. |
| `koyomi_now()` | The clock, in the configured zone (`koyomi.now` pins it). |
| `koyomi_i18n()` | Sets the language and date formats for this request, and returns the language. Call it before printing Koyomi texts yourself. |
| `koyomi_t('key', [...])` | A Koyomi text in the current language. |
| `koyomi_event($value)` | An `Event` for an event page, or the event itself; `null` otherwise. |
| `koyomi_expires($events)` | Lowers the page cache expiry to the next start or end. |
| `koyomi_expires_at($timestamp)` | Lowers the page cache expiry to a Unix timestamp; `null` changes nothing. |

The site method `$site->koyomiCalendar()` returns the calendar page, or `null`.

### Sitemaps and SEO

An event page can say whether it has ended or is happening now. Sitemap and SEO
plugins can use that:

```php
if ($page->isPast()) {
    // leave it out of the sitemap, or mark it as ended
}
$page->isOngoing();   // start <= now < end
```

Both return `false` for a page without a valid event.

### Panel collections

The Panel's page fields use these queries, which you can reuse in a blueprint:
`kirby.collection("koyomi/events")`, `kirby.collection("koyomi/venues")` and
`kirby.collection("koyomi/organizers")`.

## Time zones and daylight saving time

- An event stores its wall time and its zone: `2027-03-12`, `18:00`,
  `Europe/Berlin`. The UTC offset is calculated at output, so an event in summer
  gets the summer offset even when it was entered in winter.
- A time inside the spring-forward gap moves forward: 02:30 on 28 March 2027
  becomes 03:30 in Berlin. An ambiguous time in the autumn overlap takes the
  standard time.
- `end` is exclusive. An event that ends at 13:00 on 14 March ends there, and an
  all-day event ends at 00:00 on the day after its last day. In the calendar file
  the end is written the same way, as the ICS standard asks.
- An event without `endTime` lasts `defaultDuration` minutes (default 120) of elapsed
  time: an event at 01:30 on the night the clocks go forward ends at 04:30. If it
  has an `endDate` later than its start date, it ends at 00:00 after that date.
- An evening event with an earlier end time and no `endDate` ends the next day: a
  concert from 20:00 to 01:00 ends at 01:00 the following night.
- An event is past when its end has passed. An ongoing event (started, not yet
  ended) stays in the upcoming list and is marked "Happening now".
- The `timezone` option sets the default for new events and the site zone for
  the zone hint. A zone hint, such as "(Europe/Prague)", appears only when an
  event's zone differs from the site zone.

## Cache expiry

Koyomi lowers the page cache expiry, so a cached calendar or event page refreshes
when an event starts or ends. The subscription feed also refreshes when a past
event leaves its `feedPastDays` window, and a timeline with its "Today" line at
local midnight. The expiry only ever moves earlier, and it has an
effect only when Kirby's page cache is active. Lists, the event snippet and the
calendar's feeds set it, and so do `$page->koyomiData()`, `$page->isPast()` and
`$page->isOngoing()`. Your own templates therefore need no extra call.

## Past events

A past event keeps its page, its `.ics` file and its JSON-LD. Its page shows a
note that it has taken place, with a link to the upcoming events. The upcoming
list leaves past events out. The archive is the calendar with `?view=past`:
`/events?view=past`.

## Filters

The calendar's filter form uses the URL query. There is no JavaScript, and a
filtered page can be bookmarked and shared.

| Parameter | Values |
|---|---|
| `category` | A category name, as shown in the filter. |
| `attendance` | `offline`, `online` or `mixed`, exactly as written. |
| `language` | A language code from the `languages` option. |
| `featured` | `1` for featured events only. |
| `view` | `past` for the archive. Anything else means upcoming. |
| `page` | The page number. |

Example: `/events?category=Retreat&attendance=mixed&language=de&page=2`.
Unknown parameters are ignored. A filter value that does not fit (a language
that is not configured, an attendance mode that is misspelled) is dropped.

## Duplicating events

A duplicated event keeps its dates, because the copy is a draft and you move it
to the new date yourself. With `duplicateReset` on, the copy does not inherit
"cancelled", "postponed", a status note, a previous date or "sold out". Switch
that off with `koyomi.duplicateReset` => `false` if you want a plain copy.

## Translations

Koyomi's own texts come in English and German. They are also registered as Kirby
translations under the `koyomi.` prefix, so your own templates can use them:

```php
<?= t('koyomi.status.cancelled') ?>
<?= t('koyomi.calendar.google') ?>
```

The language is set for each request from the `language` option, as described
under [Options](#options). Missing German texts fall back to English, and a missing
English text shows its key.

## Multiple languages

On a multilingual site the event's dates, times, status, price, availability,
booking link, online platform and organizers are the same in every language. The
Panel stores them only in the default language; the other translations read them
from there. Title, excerpt, text, status note, price note, booking button text,
categories and the directions of a venue are translated per language.

Every translation then shows the same event under the same ICS UID, so a
calendar subscriber sees one event whichever language the page is in.

If a translation file already contains one of the shared values (saved before
this rule, or edited by hand), Koyomi ignores it and reads the default language.
Delete those lines from the translation file to keep the files clean.

Duplicating an event resets the status in every language.

## Privacy

Koyomi processes no visitor data: it sets no cookies, uses no browser storage and makes no request to a third party. Its ICS and RSS feeds and any organizer contact data it shows are the site's own published content. The "Google Calendar" link is a plain link: Google receives the event data only when a visitor follows it, like any external link. There is nothing to add to a site's privacy policy.

## Not in v1

These are deliberately left out of the first release:

- recurring events (a rule such as "every Thursday")
- RSVP, registration and capacity limits (booking goes to your own provider through `bookingUrl`)
- import from WordPress
- a month grid
- a calendar view in the Panel
- scheduled publishing

## Development

```sh
composer install --ignore-platform-req=ext-gd   # Kirby is a dev dependency, used as the test fixture
composer test                                   # or: sh tests/run.sh
sh tests/run.sh IcsTest                         # one file, without directory and .php
```

The tests are standalone PHP scripts, not PHPUnit. Each file exits with 0 when it
passes, so they also run on a hosting PHP without a test framework. No test needs
a network connection.

## Credits

Koyomi was written by [Simon Räder](https://sayamaapps.de) for SayamaApps.

## Maintenance

This module is open source under the MIT license and is maintained for the author's own projects. Issues and pull requests are welcome, but there is no support promise and no release schedule. Report security problems privately through GitHub's private vulnerability reporting (the "Report a vulnerability" button on the Security tab).

## License

MIT, see [LICENSE](LICENSE).

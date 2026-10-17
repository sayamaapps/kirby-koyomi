# Koyomi — build specification (v1.0.0)

Koyomi (暦, "calendar, almanac") is an event calendar plugin for Kirby 5:
events with reusable venues and organizers, status (cancelled, postponed,
rescheduled, moved online), offline/online/hybrid attendance, ICS download and
subscription, "Add to Google Calendar", JSON-LD, RSS and an optional JSON feed.
First user: a spiritual teacher's site (workshops, multi-day retreats, Zoom
sessions, concerts; DE/CZ/CH audience).

This file is the contract for parallel builders. **Shared files are written
already and must not be edited by build tasks** (§2). Each task owns a disjoint
set of files (§2, last column) and implements them against the signatures that
already exist as stubs in `src/` (bodies throw `LogicException('Koyomi: not
implemented yet: …')`). Keep every public signature exactly as in the stub.

---

## 0. Decisions (fixed, do not re-open)

| Topic | Decision |
|---|---|
| Names | Package `sayamaapps/kirby-koyomi`, plugin id `sayamaapps/koyomi`, namespace `Koyomi`, options `koyomi.*`, CSS classes `koyomi-*`, helpers `koyomi_*()`. |
| Templates/blueprints | **Prefixed, not configurable**: `koyomi-calendar`, `koyomi-event`, `koyomi-venues`, `koyomi-venue`, `koyomi-organizers`, `koyomi-organizer`. A site's own `event` template never collides. A site overrides a blueprint with `site/blueprints/pages/koyomi-event.yml`, a template with `site/templates/koyomi-event.php`, a snippet with `site/snippets/koyomi/<name>.php`. |
| Registration | Everything is registered unconditionally (Kirby loads the plugin file once per process). `koyomi.active` (default false) gates **output**: snippets print nothing, `.ics/.rss/.json` are 404, the duplicate hook does nothing. Page models and value objects keep working (they only read content). |
| Field names | `status` and `image` are `Kirby\Cms\Page` methods → the fields are **`eventStatus`** and (organizer) **`photo`**. Languages field: **`eventLanguages`**. Code reads fields ONLY via `$page->content()->get('<name>')`. |
| Attendance in the Panel | Two toggles `inPerson` (default on) and `online` (default off), because Kirby's `when` can only test equality ("venue required for offline OR mixed" is `when: inPerson: true`). Mapper derives `offline|online|mixed`. A site blueprint may store a field `attendance` instead; a valid value there wins. |
| Time | Content stores **local wall time + IANA zone**. Offsets only at output (DST-safe). `end` is **exclusive**; all-day events end at 00:00 of the day after the last day. Past = `end <= now`; an ongoing retreat is still "upcoming". |
| onlineUrl | Exists only in content and the Panel. `Koyomi\Event` has **no** such property, so no output can leak it. `tests/PrivacyTest.php` renders everything and asserts it. |
| Organizer email | Enters `Organizer` only when `showEmail` is on (Mapper). |
| Third parties | None. No map embeds, no fonts, no scripts. Map = self-hosted image + plain OpenStreetMap link. Google Calendar = plain link. |
| Outputs | Kirby **content representations**, not custom routes: `/<calendar>.ics` (feed), `/<event>.ics`, `/<calendar>.rss`, `/<calendar>.json` (default off). |
| Filters | Query string (`?category=…&attendance=…&language=…&featured=1&view=past&page=2`), plain GET form, no JS. Kirby does not page-cache requests with query data. |
| Cache | Lists and event pages lower the page cache expiry to the next start/end in the future (`$kirby->response()->expires($ts)`, verified in `vendor/getkirby/cms/src/Cms/Responder.php`: it only ever lowers). |
| Duplicate | `page.duplicate:after` resets `eventStatus`, `previousStart`, `statusNote`, `availability` (option `koyomi.duplicateReset`, default true). Dates are kept (the copy is a draft). |
| Not in v1 | Recurring events (RRULE), RSVP/registration/capacity, WordPress import, month grid, Panel calendar view, scheduled publishing. |

---

## 1. Architecture

```
content/*.txt ──► Mapper (Kirby) ──► Event / Venue / Organizer (Kirby-free value objects)
                                         │
             ┌───────────────┬───────────┼───────────┬──────────────┬──────────────┐
             ▼               ▼           ▼           ▼              ▼              ▼
          Query/Expiry     Ics       JsonLd        Rss          Format         GoogleLink
          (lists, cache)  VTimezone  (schema.org)  (RSS 2.0)    (labels, dates) (plain link)
             │               │           │           │              │              │
             └──► Repository (Kirby: finds pages, caches per App) ──┴──► snippets/templates
                                      │
                        Output (Kirby: headers, 404 gating) ◄── templates/*.ics|rss|json.php
```

- **Kirby-free core** (testable without vendor/): `I18n`, `Normalize`, `Time`, `Status`, `Attendance`, `Availability`, `Venue`, `Organizer`, `Event`, `Query`, `Expiry`, `Filters`, `Format`, `GoogleLink`, `VTimezone`, `Ics`, `JsonLd`, `Rss`, `Blueprints`.
- **Kirby glue**: `Options`, `Mapper`, `Repository`, `Output`, `Hooks`, `Models\*Page`, `index.php`, snippets, templates.
- `index.php` does `require_once` of every `src/*.php` (no autoloader) — the list is final.

### Global helpers (index.php, done)

| Function | Returns |
|---|---|
| `koyomi_option(string $key, mixed $default = null): mixed` | `koyomi.<key>` |
| `koyomi_active(): bool` | `Options::active()` |
| `koyomi_now(): DateTimeImmutable` | `Options::now()` |
| `koyomi_i18n(): string` | sets `I18n` language + `Format::configure(Options::formats(), Options::timezone())`, returns the language. **Every snippet and Output method calls it first** (after the active check). |
| `koyomi_t(string $key, array $values = []): string` | `I18n::t()` |
| `koyomi_event(mixed $value): ?Event` | Event → itself; `EventPage` → `->koyomi()`; else null. Snippets accept Event or page through this. |
| `koyomi_expires(array $events): void` | `$kirby->response()->expires(Expiry::next($events, now))` when not null |

Site method: `$site->koyomiCalendar(): ?Page` (= `Repository::calendar()`).
Collections: `kirby.collection("koyomi/events")`, `("koyomi/venues")`, `("koyomi/organizers")`.

---

## 2. File tree and ownership

`SHARED` = written by the architect, **read-only for every task**. If a task
needs a change there, it writes the request into `NOTES-<task>.md` in the
plugin root and works around it (never edits the file).

| File | Owner |
|---|---|
| `composer.json`, `.gitignore`, `.gitattributes`, `LICENSE`, `.github/workflows/ci.yml` | SHARED |
| `index.php` | SHARED |
| `SPEC.md` | SHARED |
| `src/I18n.php`, `src/Normalize.php`, `src/Time.php`, `src/Status.php`, `src/Attendance.php`, `src/Availability.php` | SHARED |
| `src/Venue.php`, `src/Organizer.php`, `src/Event.php`, `src/Options.php` | SHARED |
| `translations/en.php`, `translations/de.php` | SHARED (all keys exist; §6) |
| `tests/run.sh`, `tests/lib/expect.php`, `tests/lib/Fixture.php` | SHARED |
| `src/Expiry.php`, `tests/ExpiryTest.php` | SHARED (done) |
| `tests/I18nTest.php`, `tests/EventTest.php`, `tests/PrivacyTest.php`, `tests/AcceptanceTest.php` | SHARED (Privacy/Acceptance go green only when all tasks are done) |
| `src/Ics.php`, `src/VTimezone.php`, `tests/IcsTest.php`, `tests/VTimezoneTest.php` | task **ics** |
| `src/JsonLd.php`, `src/Rss.php`, `snippets/koyomi/jsonld.php`, `tests/JsonLdTest.php`, `tests/RssTest.php` | task **seo** |
| `src/Format.php` (except `configure()`, `reset()`, `pattern()`, `siteZone()` — done, keep them), `src/GoogleLink.php`, `snippets/koyomi/{layout,list,card,event,filters,pagination,past,venue,subscribe}.php`, `templates/koyomi-calendar.php`, `templates/koyomi-event.php`, `templates/koyomi-venue.php`, `templates/koyomi-organizer.php`, `tests/FormatTest.php`, `tests/GoogleLinkTest.php`, `tests/SnippetsTest.php` | task **present** |
| `src/Query.php`, `src/Filters.php`, `src/Mapper.php`, `src/Repository.php`, `src/Output.php`, `src/Models/{CalendarPage,EventPage,VenuePage,OrganizerPage}.php`, `templates/koyomi-calendar.ics.php`, `templates/koyomi-calendar.rss.php`, `templates/koyomi-calendar.json.php`, `templates/koyomi-event.ics.php`, `tests/QueryTest.php`, `tests/FiltersTest.php`, `tests/KirbyTest.php` | task **kirby** |
| `src/Blueprints.php`, `src/Hooks.php`, `tests/BlueprintsTest.php`, `tests/HooksTest.php` | task **panel** |
| `README.md`, `CHANGELOG.md`, `PRIVACY.md`, `DATENSCHUTZ.md` | task **docs** |

---

## 3. Options

All read via `Koyomi\Options` (done). Flat (`'koyomi.active' => true`) and
nested (`'koyomi' => ['active' => true]`) both work; `null` = not set.

| Option | Default | Getter | Meaning |
|---|---|---|---|
| `active` | `false` | `Options::active()` | Master switch; only a real `true` switches on. |
| `timezone` | `'Europe/Berlin'` | `timezone()` | Default zone of new events; site zone for the zone hint in `Format::when()`; zone of `now()`. Invalid → Europe/Berlin. |
| `timezones` | all PHP identifiers | `timezones()` | Zones offered in the Panel select (list). The default is always included. |
| `currency` | first of `currencies` | `currency()` | Default currency of new events. |
| `currencies` | `['EUR','CZK','CHF']` | `currencies()` | Panel select. |
| `languages` | `['de','en','cs']` | `languages()` → `code => label` | Event languages (Panel multiselect, filter). List or map `['cs' => 'Čeština']`; `''` label = translation `language.<code>`. |
| `language` | site language | `language()` | Language of snippets/feeds: option → current site language → default language → `locale` → `en`. |
| `perPage` | `12` | `perPage()` | Events per list page (1–100). |
| `defaultDuration` | `120` | `defaultDuration()` | Minutes for events without end time. |
| `feedPastDays` | `30` | `feedPastDays()` | Past days kept in the ICS subscription feed. |
| `ics` | `true` | `route('ics')` | `/<event>.ics`. |
| `feed` | `true` | `route('feed')` | `/<calendar>.ics` subscription. |
| `rss` | `true` | `route('rss')` | `/<calendar>.rss`. |
| `json` | `false` | `route('json')` | `/<calendar>.json`. |
| `google` | `true` | `google()` | "Google Calendar" link in the event snippet. |
| `hideCancelled` | `false` | `hideCancelled()` | Remove cancelled events from upcoming lists and RSS (the ICS feed keeps them as CANCELLED). |
| `duplicateReset` | `true` | `duplicateReset()` | Reset status fields of duplicated events. |
| `formats` | `[]` | `formats()` | Per-language date patterns merged over `Format::DEFAULTS` (§8.5). |
| `country` | `null` | `country()` → `'DE'` or `''` | Home country (ISO code), left out of displayed addresses (`Format::addressLines()`). Invalid → `''`. |
| `mapUrl` | `null` | `mapUrl()` | `fn (Koyomi\Venue $v): string` replacing the OpenStreetMap link. |
| `calendar` | first `koyomi-calendar` page | `calendarId()` | Page id of the calendar. |
| `venues` | first `koyomi-venues` page | `venuesId()` | Page id of the venues container. |
| `organizers` | first `koyomi-organizers` page | `organizersId()` | Page id of the organizers container. |
| `now` | real time | `now()` | Pin the clock (`'2027-03-10 12:00'`, DateTimeInterface or closure) — tests and previews. |

Also: `Options::eventDefaults()` → `['timezone','currency','defaultDuration']`
(second argument of `Event::fromArray()`), `Options::blueprintSettings()` →
`['timezone','timezones','currency','currencies','languages']`.

---

## 4. Time model (implemented in `Time` and `Event`)

- `Time::wall($date, $time, $zone)` builds an instant from local wall time; `null` for impossible dates/times. Spring-forward gap moves forward (`2027-03-28 02:30` Berlin → `03:30+02:00`); autumn overlap takes standard time (`2027-10-31 02:30` → `+01:00`).
- Output formats: `Time::iso()` `2027-03-12T18:00:00+01:00` · `Time::date()` `2027-03-12` · `Time::utc()` `20270312T170000Z` · `Time::local()` `20270312T180000` · `Time::icsDate()` `20270312`.
- `Event` rules: see the docblock of `Event::fromArray()` (all-day exclusive end; overnight end time; default duration; invalid start → `InvalidArgumentException`). `firstDay()`, `lastDay()` (inclusive), `isMultiDay()`, `isPast/isOngoing/isUpcoming($now)`, `effectiveAttendance()`, `isInPerson()`, `isOnline()`, `physicalVenue()`, `seriesTotal()`, `icsUrl()`, `toArray()`.
- `tests/EventTest.php` (done) is the executable reference for all of this.

---

## 5. Content field contract

### 5.1 Event page — template `koyomi-event`, child of the calendar, folder `YYYYMMDD_slug` (`num: '{{ page.startDate.toDate("Ymd") }}'`)

| Field | Panel type | Stored as | → `Event::fromArray()` key (Mapper) |
|---|---|---|---|
| `title` | title | text | `title` |
| — | — | — | `id` = `Mapper::id($page)` (UUID id, e.g. `evt-retreat`) · `url` = `$page->url()` · `modified` = `$page->modified()` |
| `excerpt` | textarea | plain text | `description` = excerpt; if empty: text blocks → HTML → `strip_tags` → whitespace collapsed → first 300 characters (`Kirby\Toolkit\Str::excerpt($plain, 300)`) |
| `text` | blocks | JSON | (only via `description` fallback; templates render it) |
| `cover` | files (single) | `- file://…` | `image` = file URL, `imageAlt` = file field `alt` |
| `startDate` | date | `2027-03-12` | `startDate` |
| `startTime` | time | `18:00:00` | `startTime` |
| `endDate` | date | `2027-03-14` or empty | `endDate` |
| `endTime` | time | `13:00:00` or empty | `endTime` |
| `allDay` | toggle | `true`/`false` | `allDay` |
| `timezone` | select | `Europe/Berlin` | `timezone` |
| `inPerson` | toggle (default true) | `true`/`false` | `attendance` = `Attendance::fromToggles(inPerson, online)` — unless the content has a valid `attendance` value |
| `online` | toggle (default false) | `true`/`false` | (see inPerson) |
| `venue` | pages (single, `kirby.collection("koyomi/venues")`, `when: inPerson: true`, required) | `- page://venue-beispielhof` | `venue` = `Mapper::venue($venuePage)` or null |
| `onlinePlatform` | text (`when: online: true`) | `Zoom` | `onlinePlatform` |
| `onlineUrl` | url (`when: online: true`) | URL | **never read** |
| `eventStatus` | select | `scheduled` … | `status` |
| `previousStart` | date with time (`when: eventStatus: rescheduled`) | `2027-02-20 20:00:00` | `previousStart` |
| `statusNote` | textarea | text | `statusNote` |
| `isFree` | toggle | `true`/`false` | `isFree` |
| `price` | number (`when: isFree: false`) | `380` | `price` |
| `currency` | select (`when: isFree: false`) | `EUR` | `currency` |
| `priceNote` | text | text | `priceNote` |
| `availability` | select | `available`/`limited`/`soldout` | `availability` |
| `bookingUrl` | url | URL | `bookingUrl` |
| `bookingLabel` | text | text | `bookingLabel` |
| `organizers` | pages (multiple, `kirby.collection("koyomi/organizers")`) | YAML list | `organizers` = `Mapper::organizer()` each |
| `categories` | tags | `Retreat, Meditation` | `categories` |
| `eventLanguages` | multiselect | `de, cs` | `languages` |
| `featured` | toggle | `true`/`false` | `featured` |
| `series` | pages (multiple, `page.siblings(false)`) | YAML list | `series` = for each sibling page: `['id' => Mapper::id(p), 'title', 'url', 'startDate', 'startTime', 'allDay', 'timezone', 'part' => seriesPart]` |
| `seriesPart` | number | `2` | `seriesPart` |

### 5.2 Venue page — template `koyomi-venue`, child of `koyomi-venues`, unlisted

| Field | Panel type | → `Venue::fromArray()` key |
|---|---|---|
| `title` | title | `name` |
| — | — | `id` = `Mapper::id()`, `url` = `$page->url()` |
| `street`, `postalCode`, `city`, `region` | text | same |
| `country` | text (2 letters) | `country` |
| `lat`, `lng` | number | `lat`, `lng` |
| `mapImage` | files (single) | `mapImage` = file URL, `mapImageAlt` = file `alt` |
| `showMap` | toggle (default false) | `showMap` |
| `website` | url | `website` |
| `directions` | textarea | `directions` |
| — | — | `mapLink` = `Options::mapUrl()` closure result for a Venue built without it, else omitted (Venue computes OSM) |

### 5.3 Organizer page — template `koyomi-organizer`, child of `koyomi-organizers`, unlisted

| Field | Panel type | → `Organizer::fromArray()` key |
|---|---|---|
| `title` | title | `name` |
| — | — | `id`, `url` |
| `kind` | select person/organization (default organization) | `kind` |
| `email` | email | `email` **only if `showEmail` is true**, else `''` |
| `showEmail` | toggle (default false) | — |
| `website` | url | `website` |
| `photo` | files (single) | `image` = file URL, `imageAlt` = file `alt` |

### 5.4 Calendar page — template `koyomi-calendar`

Fields: `title`, `intro` (textarea). Children: events. Venues and organizers
live in their own containers (`koyomi-venues`, `koyomi-organizers`), typically
`/venues` and `/organizers`, unlisted.

---

## 6. Translations

All keys exist in `translations/en.php` and `translations/de.php` (the full
table is the appendix at the end of this file). Use `I18n::t('<key>', [...])`
in Kirby-free code and `koyomi_t()` in snippets. **Do not add keys** — if one
is missing, use the closest existing key and note it in `NOTES-<task>.md`.
Panel labels: `I18n::all('<key>')` gives `['en' => …, 'de' => …]`.

---

## 7. Enumerations (done)

| Class | Values | schema.org | ICS STATUS |
|---|---|---|---|
| `Status` | `scheduled` · `cancelled` · `postponed` · `rescheduled` · `movedOnline` | `EventScheduled` · `EventCancelled` · `EventPostponed` · `EventRescheduled` · `EventMovedOnline` | CONFIRMED · CANCELLED · TENTATIVE · CONFIRMED · CONFIRMED |
| `Attendance` | `offline` · `online` · `mixed` | `OfflineEventAttendanceMode` · `OnlineEventAttendanceMode` · `MixedEventAttendanceMode` | — |
| `Availability` | `available` · `limited` · `soldout` | `InStock` · `LimitedAvailability` · `SoldOut` | — |

All schema.org values are full URLs: `https://schema.org/EventCancelled`.

---

## 8. Component contracts

Reference objects used in the examples below (Kirby-free tests build them with
`koyomi_raw_event()` / `koyomi_raw_venue()` from `tests/lib/expect.php`):

```php
$venue   = koyomi_raw_venue();   // Seminarhaus Beispielhof, Musterstraße 1, 12345 Musterstadt, Musterland, DE, 48.123/11.456
$retreat = Event::fromArray(koyomi_raw_event([
    'id' => 'evt-retreat', 'title' => 'Silent Retreat', 'url' => 'https://example.test/events/retreat',
    'endDate' => '2027-03-14', 'endTime' => '13:00',         // start 2027-03-12 18:00 Europe/Berlin
    'description' => 'Three days of silence.', 'attendance' => 'mixed', 'venue' => $venue,
    'onlinePlatform' => 'Zoom', 'categories' => 'Retreat, Meditation', 'languages' => 'de, cs',
    'price' => '380', 'availability' => 'limited', 'bookingUrl' => 'https://booking.example/retreat',
    'organizers' => [['id' => 'org-anna', 'name' => 'Anna Beispiel', 'kind' => 'person', 'website' => 'https://anna.example']],
    'image' => 'https://example.test/media/cover.jpg', 'modified' => 1791000000,
]));
$now = new DateTimeImmutable('2027-03-10 12:00', new DateTimeZone('Europe/Berlin'));   // = 2027-03-10T11:00:00Z
```

Weekdays used below: 2027-03-08 Mon · 03-11 Thu · 03-12 Fri · 03-14 Sun · 03-20 Sat · 03-25 Thu · 2027-02-20 Sat · 2027-12-30 Thu · 2028-01-02 Sun · 2027-03-28 Sun · 2027-04-02 Fri.

### 8.1 `Ics` (task ics)

**Calendar** (`Ics::calendar($events, $options)`), lines in this order:

```
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//SayamaApps//Koyomi 1.0//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH
X-WR-CALNAME:<escape(name)>                 only if name
X-WR-CALDESC:<escape(description)>          only if description
X-WR-TIMEZONE:<timezone>                    only if timezone
REFRESH-INTERVAL;VALUE=DURATION:<refresh>   only if refresh
X-PUBLISHED-TTL:<refresh>                   only if refresh
<VTIMEZONE per distinct zone of the TIMED events, zones sorted by name, "UTC" skipped;
 window = min(start) … max(end) of that zone's timed events>
<VEVENT per event, in the given order>
END:VCALENDAR
```

`Ics::event($event, $options)` = `calendar([$event], $options)`.
`render()` folds every line and joins with CRLF plus a final CRLF. Options
default: `host` `localhost`, `now` real now, `language` `I18n::language()`.
The language option is applied by `I18n::setLanguage()` for the duration of the
call and the previous language restored afterwards (try/finally).

**VEVENT** (`Ics::vevent()`), unfolded lines in this order (omit a line whose value would be empty unless stated):

```
BEGIN:VEVENT
UID:evt-retreat@example.test                          uid()
DTSTAMP:20270310T110000Z                              Time::utc(now)
LAST-MODIFIED:20261003T040000Z                        gmdate of modified; omitted when modified = 0
SEQUENCE:3552720                                      sequence() = intdiv(1791000000 − 1577836800, 60)
DTSTART;TZID=Europe/Berlin:20270312T180000            timed, zone ≠ UTC
DTEND;TZID=Europe/Berlin:20270314T130000
                                                      timed UTC zone: DTSTART:20270312T180000Z / DTEND:…Z
                                                      all-day: DTSTART;VALUE=DATE:20270312 / DTEND;VALUE=DATE:<icsDate(end)>  (exclusive)
SUMMARY:Silent Retreat                                cancelled/postponed: "<status label>: <title>", e.g. "Cancelled: Breathing Workshop"
DESCRIPTION:Three days of silence.\n\nMore information: https://example.test/events/retreat
                                                      = escape(join("\n\n", non-empty of [statusLine, description, t('ics.more', url)]))
                                                      statusLine (status ≠ scheduled) = label + (note ? ": " + note : "")
LOCATION:Seminarhaus Beispielhof\, Musterstraße 1\, 12345 Musterstadt\, DE / Online (Zoom)    escape(location())
GEO:48.123;11.456                                     physicalVenue() with coordinates, Venue::coordinate()
URL:https://example.test/events/retreat               not escaped (URI)
STATUS:CONFIRMED                                      §7 table
TRANSP:OPAQUE                                         TRANSPARENT when cancelled
CATEGORIES:Retreat,Meditation                         each value escape()d, joined by ","
ORGANIZER;CN="Sayama":mailto:info@sayama.example      first organizer with a public email; CN without '"'
END:VEVENT
```

`uid()` replaces characters outside `[A-Za-z0-9._-]` in the id with `-`.
`escape()`: `\` → `\\`, `;` → `\;`, `,` → `\,`, CRLF/CR/LF → `\n`.
`fold()`: first chunk ≤ 75 octets; continuation = CRLF + one space + ≤ 74
octets; cut only at UTF-8 character boundaries.
Examples: `'DESCRIPTION:' . str_repeat('a', 100)` (112 octets) → 75 octets +
`"\r\n "` + 37 octets. `'SUMMARY:' . str_repeat('ä', 40)` (88 octets) →
`'SUMMARY:' . 33×'ä'` (74 octets) + `"\r\n "` + 7×'ä'.

### 8.2 `VTimezone` (task ics)

`VTimezone::lines('Europe/Berlin', $retreat->start->getTimestamp(), $retreat->end->getTimestamp())`
(window widened to 2026-03-12T17:00Z … 2028-03-14T12:00Z; `getTransitions()`
returns the begin state first, then 2026-03-29, 2026-10-25, 2027-03-28,
2027-10-31):

```
BEGIN:VTIMEZONE
TZID:Europe/Berlin
BEGIN:STANDARD                ← the state at the window begin, always first, fixed DTSTART
DTSTART:19700101T000000
TZOFFSETFROM:+0100
TZOFFSETTO:+0100
TZNAME:CET
END:STANDARD
BEGIN:DAYLIGHT
DTSTART:20260329T020000       ← UTC 01:00 + previous offset (+1h) = local time before the change
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
END:DAYLIGHT
BEGIN:STANDARD
DTSTART:20261025T030000
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
END:STANDARD
BEGIN:DAYLIGHT
DTSTART:20270328T020000
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
END:DAYLIGHT
BEGIN:STANDARD
DTSTART:20271031T030000
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
END:STANDARD
END:VTIMEZONE
```

Base observance: DAYLIGHT if the begin state is DST, else STANDARD.
A zone without transitions in the window (`Asia/Tokyo`) gives only the base
observance (`TZOFFSETFROM:+0900`, `TZOFFSETTO:+0900`, `TZNAME:JST`).
`'UTC'` → `[]`. `offset()`: `3600` → `+0100`, `-18000` → `-0500`, `19800` → `+0530`, `0` → `+0000`.

### 8.3 `JsonLd` (task seo)

`JsonLd::event($retreat)` returns exactly (key order as shown, empty values omitted):

```php
[
    '@context' => 'https://schema.org',
    '@type' => 'Event',
    'name' => 'Silent Retreat',
    'description' => 'Three days of silence.',
    'url' => 'https://example.test/events/retreat',
    'image' => ['https://example.test/media/cover.jpg'],
    'startDate' => '2027-03-12T18:00:00+01:00',
    'endDate' => '2027-03-14T13:00:00+01:00',
    'eventAttendanceMode' => 'https://schema.org/MixedEventAttendanceMode',
    'eventStatus' => 'https://schema.org/EventScheduled',
    'location' => [
        ['@type' => 'Place', 'name' => 'Seminarhaus Beispielhof',
         'address' => ['@type' => 'PostalAddress', 'streetAddress' => 'Musterstraße 1', 'postalCode' => '12345',
                       'addressLocality' => 'Musterstadt', 'addressRegion' => 'Musterland', 'addressCountry' => 'DE'],
         'geo' => ['@type' => 'GeoCoordinates', 'latitude' => 48.123, 'longitude' => 11.456],
         'url' => 'https://beispielhof.example'],
        ['@type' => 'VirtualLocation', 'url' => 'https://example.test/events/retreat'],
    ],
    'offers' => ['@type' => 'Offer', 'url' => 'https://booking.example/retreat', 'price' => 380.0,
                 'priceCurrency' => 'EUR', 'availability' => 'https://schema.org/LimitedAvailability'],
    'organizer' => ['@type' => 'Person', 'name' => 'Anna Beispiel', 'url' => 'https://anna.example'],
    'inLanguage' => ['de', 'cs'],
]
```

Rules:
- `startDate`/`endDate`: `Time::iso()`; all-day → `Time::date(start)` and `Time::date(lastDay())` (inclusive).
- `eventAttendanceMode` from `effectiveAttendance()`.
- `previousStartDate` (after `eventStatus`): only for rescheduled with previousStart (ISO, or date for all-day).
- `location`: in person with venue → Place (address/geo/url keys only when non-empty; `url` = venue website); online → `VirtualLocation` with `url` = **event page URL**; mixed → list `[Place, VirtualLocation]`; exactly one → the object itself, not a list; none → key omitted. movedOnline → VirtualLocation only.
- `offers` only if `isFree` or price !== null or bookingUrl. `url` = bookingUrl ?: event url. Free → `'price' => 0.0, 'priceCurrency' => currency`. No price → no `price`/`priceCurrency`. `availability` always inside offers.
- `organizer`: `@type` Person/Organization from `kind`; `name`; `url` = `Organizer::link()`; `email` only if non-empty. One → object, several → list, none → omitted.
- `inLanguage`: one → string, several → list, none → omitted.
- `encode()` flags as in the stub. `script()` wraps in `<script type="application/ld+json">…</script>`.

**Snippet `snippets/koyomi/jsonld.php`** (task seo): data `$event` (Event|EventPage) or `$page`.
Prints nothing unless `koyomi_active()` and `koyomi_event($event ?? $page)` is
an Event. Calls `koyomi_i18n()`. Output exactly `JsonLd::script(JsonLd::event($e))`.
Used in `<head>` by `snippets/koyomi/layout.php` only when `$page` is a koyomi-event page.

### 8.4 `Rss` (task seo)

`Rss::feed([$retreat], ['title' => 'Events', 'link' => 'https://example.test/events', 'self' => 'https://example.test/events.rss', 'now' => $now])` (language en):

```xml
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:ev="http://purl.org/rss/1.0/modules/event/">
  <channel>
    <title>Events</title>
    <link>https://example.test/events</link>
    <description>Upcoming events</description>
    <language>en</language>
    <lastBuildDate>Wed, 10 Mar 2027 11:00:00 +0000</lastBuildDate>
    <atom:link href="https://example.test/events.rss" rel="self" type="application/rss+xml"/>
    <item>
      <title>Silent Retreat</title>
      <link>https://example.test/events/retreat</link>
      <guid isPermaLink="false">evt-retreat@example.test</guid>
      <pubDate>Sat, 03 Oct 2026 04:00:00 +0000</pubDate>
      <description>Three days of silence.</description>
      <category>Retreat</category>
      <category>Meditation</category>
      <ev:startdate>2027-03-12T18:00:00+01:00</ev:startdate>
      <ev:enddate>2027-03-14T13:00:00+01:00</ev:enddate>
      <ev:location>Seminarhaus Beispielhof, Musterstraße 1, 12345 Musterstadt, DE</ev:location>
    </item>
  </channel>
</rss>
```

Newlines `\n`, two spaces per level, final newline. Dates `DATE_RSS` in UTC.
`pubDate` from `modified` (omitted when 0). `description` = `describe($e)` or
`$e->description` (omitted when empty). `ev:location` = `physicalVenue()->label()`
(omitted otherwise); all-day `ev:startdate`/`ev:enddate` = dates (enddate = lastDay).
guid host default = host of `link`. Every text through `escape()`.
Cancelled events keep their item; title gets the prefix `"<status label>: "` like ICS.

### 8.5 `Format` (task present)

`Format::configure($formats, $siteZone)`, `reset()`, `pattern($name)` and
`siteZone()` are **already implemented** (every Kirby test calls them via
`koyomi_i18n()`); task present implements the rest on top of `pattern()` and
`siteZone()`. All methods use `I18n::language()`; unknown language → `en` patterns.

**`when()` table** (site zone Europe/Berlin):

| Case | en | de |
|---|---|---|
| timed, one day, with end (2027-03-12 18:00–20:00) | `Friday, 12 March 2027, 18:00–20:00` | `Freitag, 12. März 2027, 18:00–20:00 Uhr` |
| timed, no end time | `Friday, 12 March 2027, 18:00` | `Freitag, 12. März 2027, 18:00 Uhr` |
| timed, multi-day with end time (12 18:00 – 14 13:00) | `Friday, 12 March 2027, 18:00 – Sunday, 14 March 2027, 13:00` | `Freitag, 12. März 2027, 18:00 Uhr – Sonntag, 14. März 2027, 13:00 Uhr` |
| timed, multi-day without end time | `12–14 March 2027, 18:00` | `12.–14. März 2027, 18:00 Uhr` |
| all-day, one day (2027-03-25) | `Thursday, 25 March 2027` | `Donnerstag, 25. März 2027` |
| all-day, same month (03-08 … 03-11) | `8–11 March 2027` | `8.–11. März 2027` |
| all-day, same year (03-28 … 04-02) | `28 March – 2 April 2027` | `28. März – 2. April 2027` |
| all-day, across years (2027-12-30 … 2028-01-02) | `30 December 2027 – 2 January 2028` | `30. Dezember 2027 – 2. Januar 2028` |
| timed, zone Europe/Prague (2027-03-20 20:00, no end) | `Saturday, 20 March 2027, 20:00 (Europe/Prague)` | `Samstag, 20. März 2027, 20:00 Uhr (Europe/Prague)` |

Phrases: `when.dateTime` `{date}, {time}`; `when.timeSingle`; `when.timeRange`;
`when.multiDayTimed`; `when.timezone` `{when} ({zone})` with `_` → space in the
zone name. Zone hint only for timed events whose zone ≠ site zone.

**`dateRange()`**: as the all-day rows above (also for timed events).
**`date()`**/**`time()`**: patterns `date` / `time`.

**`money()`** — amount: 0 decimals when whole (|x − round(x)| < 0.005), else 2.

| | 380 EUR | 1200.5 EUR | 450 CZK | 120 CHF | 10 USD |
|---|---|---|---|---|---|
| en | `€380` | `€1,200.50` | `CZK 450` | `CHF 120` | `USD 10` |
| de | `380 €` | `1.200,50 €` | `450 Kč` | `120 CHF` | `10 USD` |

en: EUR → `€` + amount; any other code → code + space + amount (`,` thousands, `.` decimals).
de: amount + space + (`SYMBOLS[code]` ?? code) (`.` thousands, `,` decimals). Plain spaces.

Other methods (en / de):
- `price()`: free → `Free` / `Kostenlos`; no price → `''`; else `money()`.
- `status()`: `''` for scheduled; `Cancelled` / `Abgesagt` etc.
- `previousStart()`: `Originally planned for Saturday, 20 February 2027` / `Ursprünglich geplant am Samstag, 20. Februar 2027` (date() of previousStart).
- `attendance()`: offline `In person`/`Vor Ort`; online `Online` or `Online via Zoom`/`Online über Zoom`; mixed `Hybrid` or `Hybrid: in person and online via Zoom`/`Hybrid: vor Ort und online über Zoom`. Uses `effectiveAttendance()`.
- `availability()`: `''` for available; `Only a few places left`/`Nur noch wenige Plätze`; `Sold out`/`Ausgebucht`.
- `series()`: `Part 2 of 3`/`Teil 2 von 3`, `''` without series.
- `languageName('cs')`: `Czech`/`Tschechisch`; custom label wins; unknown `pt-br` → `PT-BR`.
- `countryName('CZ')`: `Czechia`/`Tschechien` (translation `country.<CODE>`); unknown `MT` → `MT`.
- `addressLines($venue, $homeCountry = '')`: `['Musterstraße 1', '12345 Musterstadt', 'Czechia']`, empty lines left out, country by name and left out when it equals `$homeCountry`. Display only — `Venue::addressLine()`/`label()` (ICS, RSS, Google link, OSM search) keep the ISO code.
- `languages($retreat)`: `German, Czech` / `Deutsch, Tschechisch`.
- `labels()`: keys `when, dateRange, status, previousStart, attendance, availability, price, series, languages`.

`apply()` tokens: `d j m n Y y H G i s` as PHP `date()`, `F M` month names,
`l D` weekday names (ISO `N`), `\x` literal; everything else copied.

### 8.6 `GoogleLink` (task present)

`GoogleLink::url($retreat, 'en')` →

```
https://calendar.google.com/calendar/render?action=TEMPLATE&text=Silent%20Retreat&dates=20270312T180000%2F20270314T130000&ctz=Europe%2FBerlin&details=Three%20days%20of%20silence.%0A%0Ahttps%3A%2F%2Fexample.test%2Fevents%2Fretreat&location=Seminarhaus%20Beispielhof%2C%20Musterstra%C3%9Fe%201%2C%2012345%20Musterstadt%2C%20DE
```

- Parameter order: `action`, `text`, `dates`, `ctz` (timed, zone ≠ UTC only), `details` (omitted when empty), `location` (omitted when empty).
- Timed: `dates` = `Time::local(start)/Time::local(end)` (wall times in the event zone) + `ctz`. UTC zone: `Time::utc(start)/Time::utc(end)`, no ctz.
- All-day: `icsDate(start)/icsDate(end)` (end exclusive, e.g. `20270325/20270326`), no ctz.
- `details` = description cut to `MAX_DETAILS` characters (cut → `mb_substr(…, 0, 500) . '…'`) + (url ? "\n\n" + url : '').
- `location` = `physicalVenue()?->label()`; no physical venue but online → `I18n::t('ics.online')` in $language.

### 8.7 `Filters` (task kirby)

`fromQuery()` rules (unknown keys ignored; array values = invalid):
- `category`: `Normalize::string`, max 80 characters (`mb_substr`), `''` → null.
- `attendance`: exactly one of `Attendance::ALL`, else null.
- `language`: `Normalize::languages($v)[0] ?? null`; if `$languages` is non-empty and the code is not in it → null.
- `featured`: `Normalize::bool`.
- `view`: `'past'` only for the exact value `past`, else `'upcoming'`.
- `page`: `Normalize::positiveInt`, else 1; capped at 10000.

Examples:
- `fromQuery(['category' => ' Retreat ', 'attendance' => 'ONLINE', 'language' => 'CS', 'featured' => 'true', 'page' => '3', 'x' => 'y'], ['de','cs'])` → `['category' => 'Retreat', 'attendance' => null, 'language' => 'cs', 'featured' => true, 'view' => 'upcoming', 'page' => 3]` (attendance is case-sensitive).
- `toQuery(['category' => 'Retreat', 'featured' => true, 'view' => 'past', 'page' => 1] + EMPTY)` → `['category' => 'Retreat', 'featured' => '1', 'view' => 'past']`.
- `url('https://ex.test/events', $f /* category Retreat, page 3 */, ['language' => 'cs'])` → `https://ex.test/events?category=Retreat&language=cs` (page reset).
- `url('https://ex.test/events', $f, ['page' => 2])` keeps filters: `…?category=Retreat&page=2`.
- `url('https://ex.test/events', $f, ['category' => null])` → `https://ex.test/events`.
- Values with spaces/umlauts: `category=Atem%20%26%20St%C3%BCtze` (RFC 3986).

### 8.8 `Query` (task kirby) and `Expiry` (done)

See the stubs. Sorting key: `[start ts, end ts, mb_strtolower(title), id]`;
desc reverses. `paginate([...7 items], 2, 3)` → items 4–6, `page 2, pages 3,
total 7, perPage 3, hasPrev true, hasNext true`; `page 9` → clamped to 3;
`[]` → `pages 1, page 1, total 0`.
Attendance filter: `online` = `isOnline()` (online, mixed, movedOnline), `offline` = `isInPerson()`, `mixed` = effective mixed.
Facets: categories keyed by first spelling, counts case-insensitive, sorted
with `strnatcasecmp`; languages sorted by code; attendance counts per filter
semantics (a mixed event counts for offline, online and mixed); featured count.
`Expiry::next()` (done, `tests/ExpiryTest.php`): min over all events of start
and end that are `> now` (strictly); null if none. Fixture clock example:
zoom session start 2027-03-11 19:00 Berlin = `1804788000`.

### 8.9 `Repository` (task kirby)

See the stub. `list()` shape:

```php
[
    'view' => 'upcoming',                      // or 'past'
    'filters' => [...],                        // Filters::fromQuery($query, array_keys(Options::languages()))
    'events' => [Event, …],                    // current page only
    'pagination' => ['page' => 1, 'pages' => 1, 'total' => 7, 'perPage' => 12,
                     'hasPrev' => false, 'hasNext' => false, 'prevUrl' => null, 'nextUrl' => null],
    'facets' => [...],                         // Query::facets(all events of this view, unfiltered)
    'title' => 'Events',                       // calendar title
    'baseUrl' => 'https://example.test/events',
    'upcomingUrl' => 'https://example.test/events',
    'pastUrl' => 'https://example.test/events?view=past',
    'filtered' => false,                       // Filters::isActive()
]
```

`prevUrl`/`nextUrl` = `Filters::url(baseUrl, filters, ['page' => n])` or null.
Every list method calls `koyomi_expires(self::events($calendar))` (all
events: the earliest future start/end of any event decides).
Cache: `WeakMap<App, array<string calendar id, list<Event>>>`; `reset()`
replaces the WeakMap. Invalid pages: `error_log('Koyomi: skipped <id>: <message>')` and skip.

### 8.10 `Output` and representation templates (task kirby)

Templates are one-liners: `<?php echo \Koyomi\Output::eventIcs($page);` etc.
Kirby resolves a representation only when the page's HTML template exists
(`Page::representation()` goes through `Page::template()`), so the HTML
templates `templates/koyomi-*.php` exist as placeholders from the start
(task present replaces them). A site's own `site/templates/koyomi-event.php`
keeps the plugin's `.ics` representation working.
Each Output method: (1) `Options::route()` false → `throw new \Kirby\Exception\NotFoundException()`
(Kirby's `App::resolve()` turns it into the 404 page); (2) `koyomi_i18n()`;
(3) headers on `$kirby->response()`: `type()`, `header()` (the charset is Kirby's default UTF-8; `Cms\Responder` has no `charset()` setter);
(4) `koyomi_expires(...)`; (5) return body.

| Method | Events | Body | Type | Content-Disposition |
|---|---|---|---|---|
| `eventIcs($page)` | the page's Event (404 if null, if page is a draft or below one, or not a koyomi-event) | `Ics::event($e, opts)` | `text/calendar` | `attachment; filename="<slug>.ics"` |
| `calendarIcs($cal)` | `Repository::events()` with `end > now − feedPastDays days`, `Query::sort()` asc, cancelled included | `Ics::calendar($events, opts + ['name' => title, 'timezone' => Options::timezone(), 'refresh' => 'PT6H'])` | `text/calendar` | `inline; filename="<slug>.ics"` |
| `rss($cal)` | `Repository::upcoming($cal)` | `Rss::feed($events, ['title','link' => url,'self' => rssUrl,'language' => koyomi_i18n(),'now','describe' => fn ($e) => trim(Format::when($e) . "\n\n" . $e->description)])` | `application/rss+xml` | — |
| `json($cal)` | `Repository::upcoming($cal)` | `json_encode(['calendar' => ['title','url'], 'generated' => Time::iso(now), 'events' => [toArray(), …]], UNESCAPED_SLASHES\|UNESCAPED_UNICODE\|PRESERVE_ZERO_FRACTION\|THROW_ON_ERROR)` | `application/json` | — |

`opts` = `['host' => parse_url($kirby->url('index'), PHP_URL_HOST) ?: 'localhost', 'now' => Options::now($kirby), 'language' => koyomi_i18n()]`.

### 8.11 Page models (task kirby)

See `src/Models/*.php`. `koyomiData()` shape:

```php
$event->toArray() + [
    'labels' => Format::labels($event, Options::languages($kirby)),
    'isPast' => false, 'isOngoing' => false,
    'googleCalendarUrl' => 'https://calendar.google.com/…' | '',
]
```

### 8.12 `Blueprints` (task panel)

Pure arrays (Kirby blueprint syntax as PHP). Every `label`/`title`/`help`/
`placeholder`/option `text` = `I18n::all('<key>')`. Required content:

- **calendar**: `title` blueprint.calendar, `icon` calendar, `options.changeTemplate` false; columns: main 2/3 with sections `drafts` (pages, `status: draft`, `templates: [koyomi-event]`, `sortBy: startDate desc`, `info: '{{ page.startDate.toDate("d.m.Y") }}'`) and `events` (pages, `status: published`, same templates/sortBy/info, `limit: 50`); sidebar 1/3 with a fields section: `intro` (textarea, label field.intro).
- **event**: `title` blueprint.event, `icon` calendar, `num: '{{ page.startDate.toDate("Ymd") }}'`, `create: ['fields' => ['startDate']]`, `options.changeTemplate` false, tabs:
  - `event` (tab.event): columns main 2/3: `excerpt` (textarea, buttons false, maxlength 500, help field.excerpt.help), `text` (blocks); sidebar 1/3: `cover` (files, multiple false, `query: page.images`).
  - `when` (tab.when): `startDate` (date, required, 1/2), `startTime` (time, 1/2, `when: [allDay => false]`, required), `endDate` (date, 1/2, help field.endDate.help), `endTime` (time, 1/2, `when: [allDay => false]`), `allDay` (toggle, default false, 1/2), `timezone` (select, options = settings.timezones as `[['value' => z, 'text' => z], …]`, default settings.timezone, required, 1/2), `inPerson` (toggle, default true, 1/2), `online` (toggle, default false, 1/2), `venue` (pages, multiple false, `query: kirby.collection("koyomi/venues")`, `when: [inPerson => true]`, required), `onlinePlatform` (text, 1/2, `when: [online => true]`, placeholder field.onlinePlatform.placeholder), `onlineUrl` (url, 1/2, `when: [online => true]`, help field.onlineUrl.help).
  - `status` (tab.status): `eventStatus` (select, options `options(Status::ALL, 'status.')`, default scheduled, required), `previousStart` (date, `time: true`, `when: [eventStatus => rescheduled]`), `statusNote` (textarea, buttons false).
  - `tickets` (tab.tickets): `isFree` (toggle, default false), `price` (number, min 0, step 0.01, 1/3, `when: [isFree => false]`), `currency` (select, options settings.currencies, default settings.currency, 1/3, `when: [isFree => false]`), `availability` (select, `options(Availability::ALL, 'availability.')`, default available, 1/3), `priceNote` (text, placeholder field.priceNote.placeholder), `bookingUrl` (url, 2/3), `bookingLabel` (text, 1/3, placeholder booking.default).
  - `details` (tab.details): `organizers` (pages, `query: kirby.collection("koyomi/organizers")`), `categories` (tags), `eventLanguages` (multiselect, options from settings.languages: text = custom label or `I18n::all('language.<code>')` or the upper-cased code), `featured` (toggle), `series` (pages, `query: page.siblings(false)`, help none), `seriesPart` (number, min 1, step 1, 1/3).
- **venues**: title blueprint.venues, icon pin, `status: [draft => true, unlisted => true, listed => false]`; one pages section `venues` (templates [koyomi-venue], sortBy title asc).
- **venue**: title blueprint.venue, icon pin, same status; columns main 2/3: `street` (2/3), `postalCode` (1/3), `city` (1/2), `region` (1/2), `country` (text, maxlength 2, 1/3, placeholder `DE`), `lat` (number, step 0.000001, min −90, max 90, 1/3), `lng` (number, step 0.000001, min −180, max 180, 1/3), `website` (url), `directions` (textarea, buttons false); sidebar 1/3: `mapImage` (files, multiple false, help field.mapImage.help), `showMap` (toggle, default false), a files section `files` (label section.files).
- **organizers**: like venues with blueprint.organizers, icon users, templates [koyomi-organizer].
- **organizer**: title blueprint.organizer, icon user, same status; fields `kind` (select person/organization via `options([...], 'kind.')`, default organization), `email` (email, 1/2), `showEmail` (toggle, default false, 1/2), `website` (url), `photo` (files, multiple false).
- **No field named `status` or `image` anywhere.**

`options(['scheduled', …], 'status.')` → `[['value' => 'scheduled', 'text' => I18n::all('status.scheduled')], …]`.

### 8.13 `Hooks` (task panel)

See the stub. Implementation: `try { return $duplicatePage->update(self::DUPLICATE_RESET); } catch (\Throwable $e) { error_log('Koyomi: duplicate reset failed: ' . $e->getMessage()); return $duplicatePage; }`,
guarded by `Options::active()`, `Options::duplicateReset()` and
`$duplicatePage->intendedTemplate()->name() === 'koyomi-event'`.
Kirby 5 replaces the duplicated page with the hook's return value
(`Kirby\Cms\Events::apply()`), so always return a Page.

### 8.14 Snippets and HTML templates (task present; jsonld: task seo)

General rules for every snippet:
1. First line of logic: `if (!koyomi_active()) { return; }`, then `koyomi_i18n();`.
2. Only `koyomi-*` classes, no inline styles, no scripts, no external resources.
3. Escape everything: `esc($text)`, and the same for attribute values (`esc($url)`, html context). Kirby's `attr` context encodes every punctuation character, which breaks the literal `href`/`datetime` strings. URLs from value objects are already http(s)-only.
4. Status, availability, "happening now", "has taken place" are always **text**.
5. Accept `Event` or `EventPage` via `koyomi_event()`; never read content fields directly (no onlineUrl possible).
6. Never call Repository lists from `koyomi/event`, `koyomi/card`, `koyomi/past` (the event page's cache expiry must be its own start/end).

| Snippet | Data (all optional unless noted) | Markup contract |
|---|---|---|
| `koyomi/layout` | `$slot` (Kirby slot), `$page` | Minimal HTML5 document: `<html lang="<koyomi_i18n()>">`, charset, viewport, `<title>` page title – site title; in `<head>` `snippet('koyomi/jsonld', ['page' => $page])` when `$page` is an EventPage; `<body><main class="koyomi-main">` + slot. Sites override this one snippet to use their own layout. Prints the slot even when inactive (it is a layout). |
| `koyomi/list` | `$list` (Repository::list() array) or `$events` (list of Event/EventPage) or nothing (→ `site()->koyomiCalendar()?->koyomiList()`); `$title` (string, or false for no heading); `$level` (heading level, default 2) | `<section class="koyomi-list koyomi-list--<view>">`, heading `class="koyomi-list-title"` (default list.upcoming / list.past), `<ol class="koyomi-events">` with `<li class="koyomi-events-item">` + `snippet('koyomi/card', ['event' => $e, 'level' => $level + 1])`; empty → `<p class="koyomi-empty">` list.emptyFiltered (filtered) / list.empty / list.pastEmpty; with `$list`: `<p class="koyomi-list-switch"><a href="pastUrl|upcomingUrl">list.toPast|list.toUpcoming</a></p>`. |
| `koyomi/card` | `$event` (required), `$level` (default 3) | `<article class="koyomi-card">` + modifiers `koyomi-card--cancelled`, `--featured`, `--past`, `--ongoing`, `--<status>`; optional `<img class="koyomi-card-image" src alt loading="lazy">`; `<hN class="koyomi-card-title"><a href="url">title</a></hN>`; `<p class="koyomi-card-when"><time datetime="<iso start or date>">Format::when()</time></p>`; `<p class="koyomi-card-status"><strong>status</strong></p>` if not scheduled; `<p class="koyomi-card-badge">event.ongoing / event.past</p>`; `<p class="koyomi-card-place">` venue name + city, attendance label; `<p class="koyomi-card-price">` price · availability (when any); `<p class="koyomi-card-excerpt">` description. |
| `koyomi/event` | `$event` or `$page` (required one), `$calendarUrl` (default: calendar of the page, else `site()->koyomiCalendar()?->url()`) | Calls `koyomi_expires([$e])`. `<div class="koyomi-event">` + modifiers; `snippet('koyomi/past', …)`; status `<p class="koyomi-status koyomi-status--<status>" role="status"><strong>label</strong> note</p>` + `<p class="koyomi-previous">Format::previousStart()</p>`; `<dl class="koyomi-facts">` with `<dt>event.when</dt><dd class="koyomi-when"><time datetime="<iso start, date for all-day>">when</time></dd>`, `<dt>event.where</dt><dd class="koyomi-where">` (koyomi/venue snippet with `compact => true` for in-person, plus attendance label), `<dt>price.label</dt><dd class="koyomi-price">` price, `<span class="koyomi-price-note">`, `<span class="koyomi-availability">`, languages (`event.languages`), organizers (`event.organizer(s)`: name linked to `link()`, public email as `mailto:`), categories. Booking: if bookingUrl and not past/cancelled: soldout → `<p class="koyomi-booking koyomi-booking--soldout">availability.soldout</p>`, else `<p class="koyomi-booking"><a class="koyomi-booking-link" href target="_blank" rel="noopener">bookingLabel ?: booking.default<span class="koyomi-sr-only"> booking.opensNewTab</span></a></p>`. Calendar links (not past, not cancelled): `<p class="koyomi-calendar-links">` with `<a href="icsUrl" download>calendar.ics</a>` when `Options::route('ics')`, and `<a href="GoogleLink::url()" target="_blank" rel="noopener nofollow">calendar.google</a>` when `Options::google()`; heading text calendar.add. Series: `<nav class="koyomi-series" aria-label="series.title">` `<p>Format::series()</p><ol>` items: link + date, current item `<span aria-current="page">title</span> series.current`. **No title, no text blocks** (the template prints those). |
| `koyomi/past` | `$event` (required), `$calendarUrl` | Only when the event is past: `<p class="koyomi-past" role="note">event.pastNote <a href="calendarUrl">event.toUpcoming</a></p>` (link only with a URL). |
| `koyomi/venue` | `$venue` (Venue or VenuePage, required), `$compact` (bool) | `<div class="koyomi-venue">`: name (`<a href=url>` when url and not compact… keep link in compact too), `<p class="koyomi-venue-address">` `Format::addressLines($venue, Options::country())` joined with `<br>`; not compact: `<p class="koyomi-venue-directions">`, `<img class="koyomi-venue-map" src alt>` when showMap and mapImage (alt = mapImageAlt ?: venue.mapAlt); links `<p class="koyomi-venue-links">` map link (venue.map, target _blank rel noopener) and website. |
| `koyomi/filters` | `$list` or `$calendar` (→ koyomiList()) | `<form class="koyomi-filters" method="get" action="baseUrl" aria-label="filters.label">`; hidden `view=past` in past view; selects only for non-empty facets: `category` (option text "Name (count)"), `attendance` (counts > 0), `language` (Format::languageName with custom labels); featured checkbox when facets.featured > 0; each select starts with `<option value="">filters.all</option>`; current values `selected`/`checked`; `<button type="submit">filters.apply</button>`; when filtered `<a class="koyomi-filters-reset" href>filters.reset</a>`. |
| `koyomi/pagination` | `$list` or `$calendar` | Only when pages > 1: `<nav class="koyomi-pagination" aria-label="pagination.label">` `<a rel="prev" href>pagination.previous</a>` `<span>pagination.page</span>` `<a rel="next" href>pagination.next</a>` (links only when URL). |
| `koyomi/subscribe` | `$calendar` (any Page; default `site()->koyomiCalendar()`) | URLs built from `$calendar->url()` only (no model methods, so the snippet also works in task present's tests): ics = url + `.ics`, webcal = ics with `^https?://` → `webcal://`, rss = url + `.rss`. `<div class="koyomi-subscribe">`: when `Options::route('feed')`: `<a href="<webcal>">calendar.subscribe</a>` + `<p class="koyomi-subscribe-hint">calendar.subscribeHint <code><ics></code></p>`; when `route('rss')`: `<a href="<rss>" type="application/rss+xml">calendar.rss</a>`. |
| `koyomi/jsonld` | §8.3 | task seo |

HTML templates (task present), all wrapped as
`<?php snippet('koyomi/layout', ['page' => $page], slots: true) ?> … <?php endsnippet() ?>`:
- `koyomi-calendar.php`: `$list = $page->koyomiList()`; `<h1>` title; intro (`$page->intro()->kti()`); `koyomi/filters`, `koyomi/list`, `koyomi/pagination` with `['list' => $list]`; `koyomi/subscribe`.
- `koyomi-event.php`: `<h1>` title; cover image (if any); excerpt `<p class="koyomi-excerpt">`; `snippet('koyomi/event', ['event' => $page])`; `$page->text()->toBlocks()`.
- `koyomi-venue.php`: `<h1>`; `koyomi/venue`; heading venue.upcoming; cards of `$page->upcomingEvents()`.
- `koyomi-organizer.php`: `<h1>`; photo; website; public email; heading organizer.upcoming; cards of `$page->upcomingEvents()`.

---

## 9. Test plan

Run one file: `ASDF_PHP_VERSION=8.5.3 sh tests/run.sh IcsTest` · everything: `ASDF_PHP_VERSION=8.5.3 sh tests/run.sh`.
Every test file starts with `require __DIR__ . '/lib/expect.php';`, uses
`koyomi_test('Koyomi <Name>', function () { … });` with `expect()`, `holds()`,
`throws()`. Kirby-free tests load classes with `koyomi_require_core()` or
explicit `require_once`; Kirby tests use `tests/lib/Fixture.php`
(`KoyomiFixture::create()`, `::app($root, $options)`, `::remove($root)` in the
finally callback). Every concrete example in §8 must appear as a check.

| File | Owner | Covers (minimum) |
|---|---|---|
| `I18nTest.php` | SHARED | keys/placeholders equal, fallback, spec wording. **Done.** |
| `EventTest.php` | SHARED | Normalize, Time, Venue, Organizer, Event rules, toArray, privacy of the value object. **Done.** |
| `IcsTest.php` | ics | escape (each char, CRLF/CR/LF); fold (ASCII 112 → 75+37; 88-octet 'ä' line → 74 + 15 with space; exactly 75 unchanged; 4-byte emoji never split; every physical line ≤ 75 octets); render ends with CRLF and has no bare LF; uid sanitizing; sequence (example 3552720, 0 for modified 0, grows with modified); full VEVENT of `$retreat` line by line (§8.1); all-day (`VALUE=DATE`, DTEND = last day + 1, incl. across DST and month end 2027-03-31 → 20270401); UTC zone (Z form, no VTIMEZONE); cancelled (STATUS:CANCELLED, TRANSP:TRANSPARENT, SUMMARY prefix, statusLine in DESCRIPTION); postponed → TENTATIVE; online only → LOCATION "Online (Zoom)" and no GEO; movedOnline → "Online …"; ORGANIZER only for a public email; German language option → "Abgesagt: …", "Weitere Informationen: …", previous language restored; calendar(): header lines, X-WR-* only when given, one VTIMEZONE per zone (Berlin + Prague), none for all-day-only; the string "onlineUrl"/any URL not in Event never appears (build an Event from raw data with onlineUrl and assert absence). |
| `VTimezoneTest.php` | ics | Berlin example of §8.2 exactly; Tokyo single observance; UTC → []; America/New_York 2027 (DST 2027-03-14 02:00 → DTSTART 20270314T020000, ends 2027-11-07 → 20271107T020000); southern hemisphere Australia/Sydney (DAYLIGHT in October); offset() examples incl. +0530 and −0930 (`-34200` → `-0930`). |
| `JsonLdTest.php` | seo | §8.3 example exactly (`expect` on the whole array); all-day dates; online only → VirtualLocation object with the event URL; movedOnline; offline without venue → no location; rescheduled → previousStartDate; free → price 0.0; booking only → offers without price; two organizers → list, one → object, email only when public; one language → string; every Status/Attendance/Availability mapping; encode(): `</script>` in a title becomes `</script>`, slashes unescaped, `380.0` kept; script() wrapper; onlineUrl absent. Kirby part: render `koyomi/jsonld` with the fixture (`KoyomiFixture::app()`), passing an Event (built with `Event::fromArray`) as `event`: one script element; inactive app → empty. |
| `RssTest.php` | seo | §8.4 document exactly; describe callback; all-day dates; cancelled prefix; empty description omitted; escaping of `&`, `<`, quotes and control chars; guid host from link; two events order kept. |
| `FormatTest.php` | present | every row of the §8.5 `when()` table in en and de; dateRange; money table; price/status/previousStart/attendance/availability/series/languageName/languages; `configure()` overrides (`'de' => ['time' => 'G.i']` → `18.00 Uhr`, range pair override); `apply()` tokens incl. escape `\T`; reset(); unknown language → en. |
| `GoogleLinkTest.php` | present | §8.6 URL exactly; all-day `dates=20270325%2F20270326` and no ctz; UTC; online-only location "Online"; no venue no online → no location param; long description cut at 500 + "…"; no url → details without blank lines. |
| `FiltersTest.php` | kirby | every §8.7 example; arrays rejected; page cap; view; isActive; url round trip `fromQuery(parse_url query of url())` equals the filters. |
| `SnippetsTest.php` | present | With `KoyomiFixture::app()` and Event/Venue objects from `fromArray()` (NOT pages: page models, Repository and Filters belong to task kirby and may still be stubs — always pass `event`/`venue`/`events`/`list`/`calendarUrl` explicitly; for `koyomi/subscribe` pass `calendar` => `$kirby->page('events')`, it only calls `->url()`): card (title link, `<time datetime>`, status text for cancelled, "Happening now" for ongoing, price + availability, classes), event (status + note, previous date, booking link/soldout text/no booking when past, ics + Google links on/off by option, no links when cancelled, series nav with aria-current, public email as mailto, `koyomi_expires` sets `$kirby->response()->expires()` to the start), past (note + link only when past), venue (address, map image only with showMap, OSM link, compact), list with `events` (empty texts), list with a hand-made `$list` array (switch link), filters with a hand-made `$list` (selected values, hidden view, reset link), pagination (none for 1 page; rel prev/next), German output (`koyomi.language` => de); inactive → every snippet prints ''; a site override `site/snippets/koyomi/card.php` wins. |
| `QueryTest.php` | kirby | upcoming/past (ongoing kept, boundaries end == now is past), hideCancelled, every filter incl. attendance semantics, sort ties and desc, paginate examples, facets, atVenue (movedOnline venue excluded), byOrganizer. |
| `ExpiryTest.php` | SHARED | **Done.** |
| `KirbyTest.php` | kirby | Mapper::raw() of every fixture event (attendance from toggles, venue/organizers/series resolved, onlineUrl absent, hidden email absent, public email present, cover URL + alt, description fallback from text blocks when excerpt empty), Mapper::id(); Repository: calendar() via option and fallback, eventPages() listed only, events() skip invalid with error_log (write a temporary invalid event into the fixture), upcoming/past titles (see AcceptanceTest §5 for the expected order), list() shape and URLs, forVenue/forOrganizer, WeakMap cache + reset(); models: koyomi() memoized, isPast/isOngoing, icsUrl/webcalUrl/rssUrl/jsonUrl, koyomiCalendar(), koyomiData() returns [] for an invalid event (the full koyomiData()/googleCalendarUrl() need Format/GoogleLink of task present — AcceptanceTest covers them); collections `kirby.collection("koyomi/venues")`; Output gating: inactive and each switch off → `$kirby->render('<path>')` is 404 (no builder needed); draft event `.ics` 404. Do NOT assert ICS/RSS/JSON body content here (tasks ics/seo own it; AcceptanceTest checks the assembly). |
| `BlueprintsTest.php` | panel | Kirby-free: every blueprint is an array; event tabs and every field of §5.1 with the right type; `when` conditions; options from settings (zones, currencies, languages with custom label); labels are `['en','de']` arrays; no `status`/`image` field; `num` and `create`; `options()` helper. |
| `HooksTest.php` | panel | Fixture + `$kirby->impersonate('kirby')`: duplicating `events/workshop-cancelled` resets the four fields and keeps dates; `koyomi.duplicateReset` false and `koyomi.active` false → unchanged; a non-event page (duplicate a venue) untouched; returns a Page; delete the copies afterwards. Note: `$page->duplicate()` needs the event blueprint (yours). |
| `PrivacyTest.php` | SHARED | every output, en + de, no secret, no hidden email (green at the end). |
| `AcceptanceTest.php` | SHARED | the assembled plugin (green at the end). |

---

## 10. Rules for builders

- PHP **8.2 compatible**: no typed class constants, `#[\Override]`, `json_validate`, `mb_str_pad`, `str_increment` (8.3); no property hooks, asymmetric visibility, `array_find`, `mb_trim`, `new Foo()->x` without parentheses (8.4); no pipe operator, `clone with`, `array_first/last` (8.5). Local PHP is 8.5 — be careful.
- `declare(strict_types=1)` is not used in this family; keep the style of the existing files (final classes, static methods, docblocks explaining *why*).
- Code, identifiers, comments in English. German only in translations and DATENSCHUTZ.md.
- Do not edit files you do not own. Missing something in a shared file → `NOTES-<task>.md` in the plugin root, then work around it.
- Do not change public signatures of the stubs. Private helpers are fine.
- Run your tests until green. Run `I18nTest` and `EventTest` too (they must stay green). `PrivacyTest`/`AcceptanceTest` may stay red until all tasks are merged.
- No network, no git push, no new dependencies, nothing outside the plugin directory.
- Composer: `ASDF_PHP_VERSION=8.5.3 php ~/Applications/PHP_Composer/composer.phar install` (already done; `vendor/` exists).

---

## Appendix: translation keys (generated from translations/*.php)

| Key | en | de |
|---|---|---|
| `month.1` | January | Januar |
| `month.2` | February | Februar |
| `month.3` | March | März |
| `month.4` | April | April |
| `month.5` | May | Mai |
| `month.6` | June | Juni |
| `month.7` | July | Juli |
| `month.8` | August | August |
| `month.9` | September | September |
| `month.10` | October | Oktober |
| `month.11` | November | November |
| `month.12` | December | Dezember |
| `monthShort.1` | Jan | Jan. |
| `monthShort.2` | Feb | Feb. |
| `monthShort.3` | Mar | März |
| `monthShort.4` | Apr | Apr. |
| `monthShort.5` | May | Mai |
| `monthShort.6` | Jun | Juni |
| `monthShort.7` | Jul | Juli |
| `monthShort.8` | Aug | Aug. |
| `monthShort.9` | Sep | Sept. |
| `monthShort.10` | Oct | Okt. |
| `monthShort.11` | Nov | Nov. |
| `monthShort.12` | Dec | Dez. |
| `weekday.1` | Monday | Montag |
| `weekday.2` | Tuesday | Dienstag |
| `weekday.3` | Wednesday | Mittwoch |
| `weekday.4` | Thursday | Donnerstag |
| `weekday.5` | Friday | Freitag |
| `weekday.6` | Saturday | Samstag |
| `weekday.7` | Sunday | Sonntag |
| `weekdayShort.1` | Mon | Mo |
| `weekdayShort.2` | Tue | Di |
| `weekdayShort.3` | Wed | Mi |
| `weekdayShort.4` | Thu | Do |
| `weekdayShort.5` | Fri | Fr |
| `weekdayShort.6` | Sat | Sa |
| `weekdayShort.7` | Sun | So |
| `language.de` | German | Deutsch |
| `language.en` | English | Englisch |
| `language.cs` | Czech | Tschechisch |
| `language.sk` | Slovak | Slowakisch |
| `language.pl` | Polish | Polnisch |
| `language.fr` | French | Französisch |
| `language.it` | Italian | Italienisch |
| `language.es` | Spanish | Spanisch |
| `language.nl` | Dutch | Niederländisch |
| `language.hu` | Hungarian | Ungarisch |
| `language.ru` | Russian | Russisch |
| `language.uk` | Ukrainian | Ukrainisch |
| `country.AT` | Austria | Österreich |
| `country.BE` | Belgium | Belgien |
| `country.CH` | Switzerland | Schweiz |
| `country.CZ` | Czechia | Tschechien |
| `country.DE` | Germany | Deutschland |
| `country.DK` | Denmark | Dänemark |
| `country.ES` | Spain | Spanien |
| `country.FI` | Finland | Finnland |
| `country.FR` | France | Frankreich |
| `country.GB` | United Kingdom | Vereinigtes Königreich |
| `country.GR` | Greece | Griechenland |
| `country.HR` | Croatia | Kroatien |
| `country.HU` | Hungary | Ungarn |
| `country.IE` | Ireland | Irland |
| `country.IT` | Italy | Italien |
| `country.LI` | Liechtenstein | Liechtenstein |
| `country.LU` | Luxembourg | Luxemburg |
| `country.NL` | Netherlands | Niederlande |
| `country.NO` | Norway | Norwegen |
| `country.PL` | Poland | Polen |
| `country.PT` | Portugal | Portugal |
| `country.SE` | Sweden | Schweden |
| `country.SI` | Slovenia | Slowenien |
| `country.SK` | Slovakia | Slowakei |
| `country.US` | United States | Vereinigte Staaten |
| `status.scheduled` | Scheduled | Findet statt |
| `status.cancelled` | Cancelled | Abgesagt |
| `status.postponed` | Postponed | Verschoben |
| `status.rescheduled` | Rescheduled | Neuer Termin |
| `status.movedOnline` | Moved online | Jetzt online |
| `status.previousStart` | Originally planned for {date} | Ursprünglich geplant am {date} |
| `attendance.offline` | In person | Vor Ort |
| `attendance.online` | Online | Online |
| `attendance.mixed` | Hybrid | Hybrid |
| `attendance.onlineVia` | Online via {platform} | Online über {platform} |
| `attendance.mixedVia` | Hybrid: in person and online via {platform} | Hybrid: vor Ort und online über {platform} |
| `availability.available` | Places available | Plätze frei |
| `availability.limited` | Only a few places left | Nur noch wenige Plätze |
| `availability.soldout` | Sold out | Ausgebucht |
| `price.label` | Price | Preis |
| `price.free` | Free | Kostenlos |
| `booking.default` | Register | Zur Anmeldung |
| `booking.opensNewTab` | (opens in a new tab) | (öffnet neuen Tab) |
| `calendar.add` | Add to calendar | In den Kalender |
| `calendar.ics` | Download (.ics) | Herunterladen (.ics) |
| `calendar.google` | Google Calendar | Google Kalender |
| `calendar.subscribe` | Subscribe to calendar | Kalender abonnieren |
| `calendar.subscribeHint` | Paste this address into your calendar app to see new dates automatically. | Diese Adresse in die Kalender-App einfügen, dann erscheinen neue Termine von selbst. |
| `calendar.rss` | RSS feed | RSS-Feed |
| `event.when` | When | Wann |
| `event.where` | Where | Wo |
| `event.organizer` | Organizer | Veranstalter |
| `event.organizers` | Organizers | Veranstalter |
| `event.languages` | Language | Sprache |
| `event.categories` | Categories | Kategorien |
| `event.featured` | Featured | Empfohlen |
| `event.ongoing` | Happening now | Läuft gerade |
| `event.past` | Has taken place | Hat stattgefunden |
| `event.pastNote` | This event has already taken place. | Diese Veranstaltung hat bereits stattgefunden. |
| `event.toUpcoming` | See upcoming events | Zu den kommenden Terminen |
| `event.more` | More details | Mehr erfahren |
| `series.part` | Part {part} of {total} | Teil {part} von {total} |
| `series.title` | All dates in this series | Alle Termine dieser Reihe |
| `series.previous` | Previous part | Vorheriger Teil |
| `series.next` | Next part | Nächster Teil |
| `series.current` | (this event) | (diese Veranstaltung) |
| `venue.directions` | Directions | Anfahrt |
| `venue.map` | Show on map | Auf Karte zeigen |
| `venue.mapAlt` | Map: {name} | Karte: {name} |
| `venue.website` | Website | Website |
| `venue.upcoming` | Upcoming events at this venue | Kommende Termine an diesem Ort |
| `organizer.email` | Email | E-Mail |
| `organizer.website` | Website | Website |
| `organizer.upcoming` | Upcoming events | Kommende Termine |
| `list.upcoming` | Upcoming events | Kommende Termine |
| `list.past` | Past events | Vergangene Veranstaltungen |
| `list.empty` | There are no upcoming events at the moment. | Zurzeit sind keine Termine geplant. |
| `list.emptyFiltered` | No events match these filters. | Keine Termine passen zu diesen Filtern. |
| `list.pastEmpty` | No past events. | Keine vergangenen Veranstaltungen. |
| `list.toPast` | Past events | Vergangene Veranstaltungen |
| `list.toUpcoming` | Upcoming events | Kommende Termine |
| `pagination.label` | Pagination | Seitennavigation |
| `pagination.previous` | Previous page | Vorherige Seite |
| `pagination.next` | Next page | Nächste Seite |
| `pagination.page` | Page {page} of {pages} | Seite {page} von {pages} |
| `filters.label` | Filter events | Termine filtern |
| `filters.category` | Category | Kategorie |
| `filters.attendance` | Format | Format |
| `filters.language` | Language | Sprache |
| `filters.featured` | Featured only | Nur Empfehlungen |
| `filters.all` | All | Alle |
| `filters.apply` | Apply | Anwenden |
| `filters.reset` | Reset filters | Filter zurücksetzen |
| `filters.results` | {count} events | {count} Termine |
| `when.dateTime` | {date}, {time} | {date}, {time} |
| `when.timeSingle` | {time} | {time} Uhr |
| `when.timeRange` | {start}–{end} | {start}–{end} Uhr |
| `when.multiDayTimed` | {startDate}, {startTime} – {endDate}, {endTime} | {startDate}, {startTime} Uhr – {endDate}, {endTime} Uhr |
| `when.timezone` | {when} ({zone}) | {when} ({zone}) |
| `ics.online` | Online | Online |
| `ics.more` | More information: {url} | Weitere Informationen: {url} |
| `rss.description` | Upcoming events | Kommende Termine |
| `blueprint.calendar` | Calendar | Kalender |
| `blueprint.event` | Event | Veranstaltung |
| `blueprint.venues` | Venues | Veranstaltungsorte |
| `blueprint.venue` | Venue | Veranstaltungsort |
| `blueprint.organizers` | Organizers | Veranstalter |
| `blueprint.organizer` | Organizer | Veranstalter |
| `tab.event` | Event | Veranstaltung |
| `tab.when` | Date & place | Termin & Ort |
| `tab.status` | Status | Status |
| `tab.tickets` | Tickets | Tickets |
| `tab.details` | Details | Details |
| `section.events` | Events | Veranstaltungen |
| `section.drafts` | Drafts | Entwürfe |
| `section.venues` | Venues | Veranstaltungsorte |
| `section.organizers` | Organizers | Veranstalter |
| `section.files` | Files | Dateien |
| `field.intro` | Introduction | Einleitung |
| `field.excerpt` | Short description | Kurzbeschreibung |
| `field.excerpt.help` | Plain text. Used in lists, feeds, calendar files and search results. | Reiner Text. Erscheint in Listen, Feeds, Kalenderdateien und Suchergebnissen. |
| `field.text` | Text | Text |
| `field.cover` | Cover image | Titelbild |
| `field.startDate` | Start date | Beginn (Datum) |
| `field.startTime` | Start time | Beginn (Uhrzeit) |
| `field.endDate` | End date | Ende (Datum) |
| `field.endDate.help` | Leave empty for a single day. | Leer lassen, wenn die Veranstaltung an einem Tag stattfindet. |
| `field.endTime` | End time | Ende (Uhrzeit) |
| `field.allDay` | All day | Ganztägig |
| `field.timezone` | Time zone | Zeitzone |
| `field.inPerson` | In person | Vor Ort |
| `field.online` | Online | Online |
| `field.venue` | Venue | Veranstaltungsort |
| `field.onlinePlatform` | Online platform | Online-Plattform |
| `field.onlinePlatform.placeholder` | e.g. Zoom | z. B. Zoom |
| `field.onlineUrl` | Meeting link (private) | Meeting-Link (privat) |
| `field.onlineUrl.help` | Never shown on the website, in feeds or in calendar files. Send it to participants yourself. | Erscheint nie auf der Website, in Feeds oder Kalenderdateien. Bitte selbst an die Teilnehmenden schicken. |
| `field.eventStatus` | Status | Status |
| `field.previousStart` | Originally planned start | Ursprünglicher Beginn |
| `field.statusNote` | Note on the status | Hinweis zum Status |
| `field.isFree` | Free of charge | Kostenlos |
| `field.price` | Price | Preis |
| `field.currency` | Currency | Währung |
| `field.priceNote` | Price note | Hinweis zum Preis |
| `field.priceNote.placeholder` | e.g. early bird until 1 March | z. B. Frühbucher bis 1. März |
| `field.availability` | Availability | Verfügbarkeit |
| `field.bookingUrl` | Booking link | Anmelde-Link |
| `field.bookingLabel` | Button text | Button-Text |
| `field.organizers` | Organizers | Veranstalter |
| `field.categories` | Categories | Kategorien |
| `field.languages` | Languages | Sprachen |
| `field.featured` | Featured | Hervorheben |
| `field.series` | Other parts of the series | Weitere Teile der Reihe |
| `field.seriesPart` | Part number | Teil Nr. |
| `field.street` | Street | Straße |
| `field.postalCode` | Postal code | PLZ |
| `field.city` | City | Ort |
| `field.region` | Region | Region |
| `field.country` | Country (ISO code) | Land (ISO-Code) |
| `field.lat` | Latitude | Breitengrad |
| `field.lng` | Longitude | Längengrad |
| `field.mapImage` | Map image (self-hosted) | Kartenbild (selbst gehostet) |
| `field.mapImage.help` | Upload a static map image. The plugin never loads maps from third parties. | Ein statisches Kartenbild hochladen. Das Plugin lädt nie Karten von Drittanbietern. |
| `field.showMap` | Show map image | Kartenbild zeigen |
| `field.website` | Website | Website |
| `field.directions` | Directions | Anfahrt |
| `field.email` | Email | E-Mail |
| `field.showEmail` | Show email publicly | E-Mail öffentlich zeigen |
| `field.photo` | Photo / logo | Foto / Logo |
| `field.kind` | Type | Art |
| `kind.person` | Person | Person |
| `kind.organization` | Organization | Organisation |

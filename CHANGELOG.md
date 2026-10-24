# Changelog

All notable changes to this project are documented here. Versions follow
[Semantic Versioning](https://semver.org) with these project rules:

- **A change of default output wording, or of a default option that changes
  what visitors see**, is a **major** version: a site would show something else
  after a routine update.
- A change to the names of options, fields, snippets or URLs is breaking and is
  a major version too.

## [1.3.0] — 2026-10-24

### Added

- **Events by chat (Fude 1.7 or later).** The calendar's events section and
  these event fields carry a `fude` marker: `title`, `excerpt`, `startDate`,
  `startTime`, `endDate`, `endTime`, `inPerson`, `online`, `venue`,
  `onlineUrl`, `eventStatus`, `statusNote`, `price`, `priceNote`,
  `availability` and `bookingUrl`. Fude derives one collection per calendar,
  found by the start date, without a line in `site/config/fude.php`. Kirby
  and sites without Fude ignore the key; pages and feeds are unchanged.

### Changed

- **`composer.json` conflicts with Fude before 1.7**, which would read the
  event markers as one collection per event and fail beyond 30 events.

## [1.2.0] — 2026-10-23

### Changed

- **A venue needs an address:** `street`, `postalCode` and `city` are required.
  An in-person event points to its venue, and a venue without an address
  leaves visitors without a way to get there. Existing venues without one ask
  for it at the next save in the Panel; pages and feeds are unchanged.
- **An online event needs its meeting link:** `onlineUrl` is required while
  `online` is on. It stays private as before.

### Added

- **`fudeAsk` on `endTime`, `price` and `bookingUrl`:** with the package Fude
  (1.6 or later), a chat bot asks for them before it drafts a new event and
  takes "don't know". Kirby and sites without Fude ignore the key. README:
  "Changes by chat (Fude)".

### Removed

- **`DATENSCHUTZ.md` and `PRIVACY.md`.** Koyomi processes no visitor data, so it has no place in a site's privacy policy. The README says so under "Privacy".

## [1.1.0] — 2026-10-19

### Fixed

- An event without an end time lasted `defaultDuration` minutes on the wall clock,
  not in elapsed time: starting at 01:30 on the night the clocks change, it lasted
  60 minutes in spring and 180 in autumn — in the `.ics`, the Google link and the
  past/ongoing decision. It now lasts the minutes set. An end time the content
  gives keeps its wall time; all-day events keep their calendar days.
- Calendar files said `METHOD:PUBLISH` without an `ORGANIZER` whenever no organizer
  had a public email. RFC 5546 asks an organizer for `PUBLISH`, and a feed or
  download is no scheduling message at all: the line is gone (RFC 5545 does not
  ask for it). No email is published to fill the gap.
- With Kirby's page cache on, the subscription feed and the timeline could go
  stale: the expiry knew only event starts and ends. A feed with nothing ahead was
  cached without end and kept an event long after it left the `feedPastDays`
  window; a calendar whose next event was weeks away kept yesterday's "Today" line.
  The feed now also expires when the next event leaves its window, and a list that
  prints the Today line at local midnight. New helper `koyomi_expires_at()`.

### Changed

- **[privacy text]** `DATENSCHUTZ.md`, `PRIVACY.md` and the README said, without a
  limit, that event pages load nothing from another server. That holds for what
  Koyomi renders. The event's text is a Kirby blocks field, and a web image or a
  video block an editor inserts there (or an external image in the calendar intro)
  loads from its server without a click. The texts now say so and what to do. No
  output changed; review your privacy policy if your descriptions embed foreign
  media.

## [1.0.2] — 2026-10-17

### Fixed

- A venue, organizer or series part below a draft container (for example after the
  whole "Venues" page was put back into draft) was still shown in a published
  event's page, JSON-LD, `.ics` files and the JSON feed, although its own URL
  answered 404. Kirby's `isDraft()` looks at the page alone; Koyomi now treats a
  page below a draft as a draft everywhere it decides what is public
  (`Mapper::isDraft()`): references, the calendar lookup for a configured
  `koyomi.calendar` id, and `/<event>.ics`. Panel pickers are unchanged. The
  privacy texts name pages below drafts explicitly.

## [1.0.1] — 2026-10-11

### Fixed

- `koyomi/venue` printed the country as its raw ISO code ("Musterstadt<br>CZ"). It
  now prints the name in the site language ("Czechia", "Tschechien"; 24
  European countries and the US, other codes as they are), via
  `Format::countryName()` and `Format::addressLines()`.
- New option `country`: the home country, left out of displayed addresses, so a
  German site shows "12345 Musterstadt" and "12345 Musterstadt, Tschechien".
- JSON-LD (`addressCountry`), calendar files, RSS and the Google link keep the
  ISO code, as before.

## [1.0.0] — 2026-10-11

### Timeline (Simon 11.10.2026: "a calendar is always a time line")

- `koyomi/list` shows the events as a timeline by default: a "today" line,
  month headings, and per card a calendar-sheet date (weekday, day, month) and
  the time of day. Option `groupBy` (`'month'` | `false`), snippet parameter
  `$group`. Helpers `Query::byMonth()`, `Format::monthTitle()`,
  `Format::dayBlock()`, `Format::clock()`.
- New snippet `koyomi/switch`: "All · In person · Online" as links with
  counts. A hybrid event counts under both, so a hybrid event no longer has to
  be entered twice. Option `attendanceSwitch` (default on); the calendar
  template shows the switch and leaves the format select out of the filter
  form (`koyomi/filters` parameter `$attendance`).

### Fixed (review round, before the first release)

- Drafts never reach the public output: a draft series part, venue or organizer
  is left out of the event data, HTML, JSON feed, JSON-LD and ICS. A draft
  calendar is not the site's calendar. New venues and organizers are created
  unlisted. [privacy text] (the "Privacy" sections of the texts were corrected:
  drafts, JSON-LD contents, the RSS organizer address, unlisted events.)
- Multilingual sites: dates, status, price, venue, organizers and the other
  shared fields are read from the default language (`translate: false`); the
  duplicate reset covers every language.
- A timed event without an end (for example a 23:00 concert with the default
  duration) is one day: no two-day range in HTML, RSS or JSON-LD, and no
  invented `endDate` in JSON-LD or `ev:enddate` in RSS.
- Autumn DST overlap: a local time that occurs twice is written in UTC form in
  ICS, the same instant as the page and JSON-LD; an overnight end uses the next
  day's wall time.
- ICS: control characters in the organizer name are removed from the CN
  parameter (no injected lines).
- `$page->koyomiData()`, `isPast()` and `isOngoing()` lower the page cache expiry.
- The event page template prints only the stored excerpt, so the text is not
  shown twice.
- Documentation: the `formats` keys and tokens, the map image and link output
  of the venue snippet, the layout override with JSON-LD, the multilingual
  rules.

First release.

### Added

- Events as Kirby pages (`koyomi-event`) inside a calendar page
  (`koyomi-calendar`): title, excerpt, text blocks, cover image, start and end
  date and time, all-day events, time zone per event, and a default duration
  for events without end time.
- Reusable venues (`koyomi-venue`) and organizers (`koyomi-organizer`) in their
  own containers, so an address, a map image or a photo is maintained once and
  picked by every event that uses it.
- Event status: scheduled, cancelled, postponed, rescheduled (with the original
  start), moved online. Status is shown as text on the page, in the feeds, in the
  calendar files and in the structured data.
- Attendance: in person, online and hybrid, set with two Panel toggles, plus an
  optional `attendance` field for sites that prefer a select. Online
  events show the platform name (for example "Zoom"), never the meeting link.
- Ticket information: free or priced events, currency, price note, availability
  (available, a few places left, sold out), booking link and button text.
- Series of related events with "part n of m" navigation.
- Calendar page with a filter form (category, format, language, featured), a
  paginated list of upcoming events and an archive of past events
  (`?view=past`). Filters are plain URL parameters; no JavaScript.
- Time model with local wall time and an IANA zone per event. Offsets are
  calculated at output, so daylight saving time is correct for every date.
  Ends are exclusive, and ongoing events stay in the upcoming list.
- Calendar files: a per-event `.ics` download, a calendar subscription feed
  (`/<calendar>.ics`, offered as `webcal://`) with cancelled events marked as
  cancelled, and VTIMEZONE blocks for the zones in use.
- RSS 2.0 feed (`/<calendar>.rss`) with the event module (start, end and
  location). Cancelled events keep their item, with the status in the title.
- Optional JSON feed (`/<calendar>.json`), off by default.
- schema.org `Event` JSON-LD on every event page: attendance mode, event status,
  location (place, virtual location or both), offers, organizer and language.
- "Add to Google Calendar" link on upcoming events, a plain link that opens only
  on click.
- Map: a self-hosted map image and an OpenStreetMap link that opens on click.
  No map is embedded and no map service is contacted.
- Options `koyomi.*` for the master switch, zones, currencies, languages, paging,
  durations, feed window, output switches, the Google link, cancelled events,
  duplicate reset, date formats, the map link, the page ids of the containers
  and a pinned clock for previews.
- Duplicating an event resets its status, previous date, status note and
  availability (`duplicateReset`).
- Snippets with `koyomi-*` classes only and no inline styles, scripts or
  external resources: `koyomi/layout`, `list`, `card`, `event`, `past`,
  `venue`, `filters`, `pagination`, `subscribe` and `jsonld`. Each one is
  overridable under `site/snippets/koyomi/`.
- Templates for the calendar, event, venue and organizer pages, overridable
  under `site/templates/`, and Panel blueprints, overridable under
  `site/blueprints/pages/`.
- Texts in English and German, registered as Kirby translations under the
  `koyomi.` prefix.
- Page models for events, calendars, venues and organizers with the helpers
  `koyomiData()`, `koyomiList()`, `isPast()`, `isOngoing()` and friends, and
  global helpers `koyomi_option()`, `koyomi_event()`, `koyomi_expires()` and others.
- Page cache expiry lowered to the next start or end of an event, so cached
  calendars refresh on time.

### [privacy text]

- Introduces the privacy texts: `PRIVACY.md` (English summary) and
  `DATENSCHUTZ.md` (German text block for the site's privacy policy). The text
  block describes what the outputs make public: pages, calendar files, RSS, JSON
  and JSON-LD, and states that the online meeting link and the organizer's
  email (unless `showEmail` is on) are never public.
- Feeds are public addresses. The privacy text says so, and a site that
  publishes them must include that in its policy.
- The Google Calendar link sends its data to Google when clicked. The privacy
  text describes that.

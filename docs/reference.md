# Koyomi reference

Panel fields, date formats, filters, snippets and PHP API. Setup and options are in the [README](../README.md).

## Fields

Read a field in a template with `$page->content()->get('startDate')`.

### Event (`koyomi-event`)

| Field | Type | Notes |
|---|---|---|
| `title` | title | |
| `excerpt` | textarea | Plain text for lists, feeds and search. Empty: the start of `text`, cut to 300 characters. |
| `text` | blocks | Full description. |
| `cover` | file | Cover image; its alt text is used too. |
| `startDate`, `startTime` | date, time | Required. The time is not needed when `allDay` is on. |
| `endDate`, `endTime` | date, time | Last day and end time. Empty `endTime`: `defaultDuration` applies. |
| `allDay` | toggle | Dates only; times are hidden. |
| `timezone` | select | IANA zone of the wall times. The UTC offset is calculated on output. |
| `inPerson`, `online` | toggle | `inPerson` defaults on, `online` off. |
| `venue` | page | Required when `inPerson` is on. |
| `onlinePlatform`, `onlineUrl` | text, url | Platform, shown publicly ("Online via Zoom"). The meeting link, required when `online` is on, is Panel only and never output. |
| `eventStatus` | select | `scheduled` (default), `cancelled`, `postponed`, `rescheduled`, `movedOnline`. |
| `previousStart` | date with time | Original start, shown when `rescheduled`. |
| `statusNote` | textarea | Reason or note on the status. |
| `isFree`, `price`, `currency`, `priceNote` | toggle, number, select, text | Free events hide the price fields. Price in `currency`. |
| `availability` | select | `available` (default), `limited`, `soldout`. |
| `bookingUrl`, `bookingLabel` | url, text | Link to an external booking provider; button text (default "Register"). |
| `organizers` | pages | From the organizers container. |
| `categories` | tags | Used by the filter. |
| `eventLanguages` | multiselect | Languages of the event; used by the filter. |
| `featured` | toggle | Used by the filter. |
| `series`, `seriesPart` | pages, number | Sibling events of the same series, and this event's part number. |

**Attendance** is derived from `inPerson` and `online`: `offline`, `online` or `mixed` (hybrid). A stored `attendance` field with a valid value takes precedence. The status `movedOnline` makes the effective attendance online.

**Languages.** Dates, times, status, price, availability, booking link, platform and organizers are stored in the default language and shared by all translations. Title, excerpt, text, notes, categories and venue directions are translated. All translations share one ICS UID.

### Venue (`koyomi-venue`)

| Field | Type | Notes |
|---|---|---|
| `title` | title | |
| `street`, `postalCode`, `city` | text | Required. |
| `region` | text | Optional. |
| `country` | text | ISO code, e.g. `DE`. Pages show the country name, unless it is the `country` option. |
| `lat`, `lng` | number | Used for the OpenStreetMap link; without them, the address is searched. |
| `mapImage`, `showMap` | file, toggle | Your own static map image, shown on the venue page when `showMap` is on. No third-party maps. |
| `website` | url | |
| `directions` | textarea | How to get there. |

### Organizer (`koyomi-organizer`)

| Field | Type | Notes |
|---|---|---|
| `title` | title | Name of the person or organization. |
| `kind` | select | `person` or `organization` (default). Sets the schema.org type. |
| `email`, `showEmail` | email, toggle | `showEmail` (default off) publishes `email` on the organizer page, the event and calendar files. |
| `website` | url | |
| `photo` | file | Photo or logo. |

**Calendar** (`koyomi-calendar`): `title` is the heading, subscription name and RSS channel title; `intro` is shown above the list.

### Fude

Fude 1.7 or later reads the `fude` markers, `required`, `when` and `fudeAsk` from the blueprints. The events section of `koyomi-calendar` is one collection per calendar, named `<calendar-id>-events`, key `startDate`, actions `create` and `update` (cancel with `eventStatus`, no deleting). The start date names one event: a day with several events is edited in the Panel, the bot gets a 409 there. A free event becomes paid with `isFree: false` and the price in one draft; Fude 1.7.2 or later refuses a price alone on a free event. A site may be stricter in its Fude declaration, never looser: a `pages` collection in `site/config/fude.php` for the calendar and `koyomi-event` replaces the derived one. A site that replaces the calendar blueprint keeps the `fude` key on its events section; without it, every event becomes a collection of its own.

| | Event |
|---|---|
| **Reachable** | `title`, `excerpt`, `startDate`, `startTime`, `endDate`, `endTime`, `inPerson`, `online`, `venue`, `onlineUrl`, `eventStatus`, `statusNote`, `isFree`, `price`, `priceNote`, `availability`, `bookingUrl` |
| **Not reachable** | `text` (blocks), `categories` (tags), `eventLanguages` (multiselect), `organizers` and `series` (several pages): types Fude cannot set; also `cover`, `allDay`, `timezone`, `onlinePlatform`, `previousStart`, `currency`, `bookingLabel`, `featured`, `seriesPart` |

| | Event | Venue |
|---|---|---|
| **Required** | `title`, `startDate`, `startTime` (unless `allDay`), `venue` (in person), `onlineUrl` (online), `eventStatus` (has a default) | `title`, `street`, `postalCode`, `city` |
| **Asked once** ("don't know" is accepted) | `endTime`, `price` (unless `isFree`), `bookingUrl` | none |
| **Free** | everything else | everything else |

## Date formats

`formats` merges over the defaults per language (en / de):

| Key | en | de | Used for |
|---|---|---|---|
| `date` | `l, j F Y` | `l, j. F Y` | one day |
| `time` | `H:i` | `H:i` | a time |
| `rangeSameMonth` | `j`, `j F Y` | `j.`, `j. F Y` | days of one month |
| `rangeSameYear` | `j F`, `j F Y` | `j. F`, `j. F Y` | months of one year |
| `rangeOther` | `j F Y`, `j F Y` | `j. F Y`, `j. F Y` | across years |
| `rangeSameMonthSep` | `–` | `–` | between the days of `rangeSameMonth` |
| `rangeSep` | ` – ` | ` – ` | between the parts of the other ranges |

Range keys take two patterns (start, end). Tokens: `d j` day, `m n` month, `Y y` year, `H G` hour, `i s` minute and second, `F M` month name, `l D` weekday name. `\x` prints `x` literally; other characters are copied. Example: `['de' => ['time' => 'G.i']]` prints `18.00`.

## Filters

The calendar's filter form uses the URL query. There is no JavaScript, and filtered URLs can be bookmarked.

| Parameter | Values |
|---|---|
| `category` | A category name. |
| `attendance` | `offline`, `online` or `mixed`. |
| `language` | A code from the `languages` option. |
| `featured` | `1` for featured events only. |
| `view` | `past` for the archive; anything else is upcoming. |
| `page` | Page number. |

Unknown parameters and values that do not fit are ignored. Example: `/events?category=Retreat&attendance=mixed&language=de&page=2`.

## Snippets

Snippets print unstyled markup with `koyomi-*` classes. Pass data as arguments. Without `list` or `calendar`, the list, switch, filters and pagination snippets use the site calendar.

| Snippet | Arguments | Prints |
|---|---|---|
| `koyomi/layout` | `page`, slot | HTML document with `<title>`, language and `<main class="koyomi-main">`. Prints its slot even when Koyomi is off. |
| `koyomi/list` | `list` or `events`; `title` (text or `false`); `level` (2); `group` (`'month'` or `false`) | Events as a month timeline, or one flat list; upcoming/past switch. |
| `koyomi/card` | `event`; `level` (3) | One event: date, title, time, status, place, price, availability, excerpt. |
| `koyomi/event` | `event` or `page`; `calendarUrl` | The facts of one event, calendar links, booking link and series navigation. |
| `koyomi/past` | `event`; `calendarUrl` | Note on a past event, linking to the upcoming ones. |
| `koyomi/venue` | `venue`; `compact` | Name and address; with `compact` off also directions and map image. |
| `koyomi/switch` | `list` or `calendar` | "All · In person · Online" links with counts. Nothing when a view has no in-person or no online event. |
| `koyomi/filters` | `list` or `calendar`; `attendance` (true) | The filter form. With `attendance` off, the format select is left out. |
| `koyomi/pagination` | `list` or `calendar` | Previous and next links, when there is more than one page. |
| `koyomi/subscribe` | `calendar` | `webcal://` and RSS links, as far as their switches are on. |
| `koyomi/jsonld` | `event` or `page` | The `application/ld+json` block of an event. |

The booking link carries a `koyomi-sr-only` span; the site's own CSS must hide it, as the plugin ships no CSS. Templates are overridden in `site/templates/`, blueprints in `site/blueprints/pages/`.

## Page methods and helpers

| Method | Returns |
|---|---|
| `$page->koyomi()` | The `Event` of an event page, or `null`. |
| `$page->koyomiData()` | `[]` for an invalid start date. Otherwise `Event::toArray()` (the basis of the JSON feed; no online meeting link) plus `labels`, `isPast`, `isOngoing`, `googleCalendarUrl`. |
| `$page->isPast()`, `$page->isOngoing()` | Booleans; `false` without a valid event. |
| `$page->koyomiList($query)` | Calendar: `events` (current page), `pagination`, `facets`, `view`, `filtered`, `filters`, `baseUrl`, `upcomingUrl`, `pastUrl`. |
| `$page->koyomiUpcoming()`, `$page->koyomiPast()` | Lists of `Event` objects. |
| `$page->icsUrl()`, `webcalUrl()`, `rssUrl()`, `jsonUrl()` | Calendar feed addresses. |
| `$site->koyomiCalendar()` | The calendar page, or `null`. |

| Helper | Returns |
|---|---|
| `koyomi_option($name, $default)` | The value of `koyomi.<name>`. |
| `koyomi_active()` | Whether Koyomi is switched on. |
| `koyomi_now()` | The clock in the configured zone (`koyomi.now` pins it). |
| `koyomi_i18n()` | Sets language and date formats for the request; returns the language. Call it before printing texts yourself. |
| `koyomi_t($key, $values)` | A Koyomi text in the current language. The texts are also Kirby translations, e.g. `t('koyomi.status.cancelled')`. |
| `koyomi_event($value)` | The `Event` of an event page or an `Event`; otherwise `null`. |
| `koyomi_expires($events)`, `koyomi_expires_at($timestamp)` | Lower the page cache expiry. Koyomi already does this for its own output. |

For Panel fields, the queries are `kirby.collection("koyomi/events")`, `"koyomi/venues"` and `"koyomi/organizers"`.

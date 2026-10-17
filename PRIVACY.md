# Koyomi: privacy notes

This is an English summary of [DATENSCHUTZ.md](DATENSCHUTZ.md), the German text
block for privacy policies, which is the authoritative version. Both describe the
implementation, not legal advice. Each operator has to determine the legal basis
and the wording for their own site.

## What the visitor's browser does

Nothing towards third parties. The plugin loads no script, no font, no embed and
no image from another server. Two links leave the site, and only when a visitor
clicks them:

- **Google Calendar**: a plain link to Google's calendar, pre-filled with the
  event. Google receives the data in the link when the visitor follows it: the
  title, the date and time, the zone, the location, a shortened description and
  the event's URL.
- **OpenStreetMap**: a plain link to the venue's location on the map. The map
  picture shown on the venue page is stored on the site's own server.

Booking links lead to the booking provider you enter. That provider has its own
privacy terms, and the link is opened in a new tab when the visitor clicks it.

The plugin sets no cookies, uses no browser storage and records nothing about
visitors.

## What is public, per output

Everything below is public for anyone who can reach the address, as long as the
event is not a draft and the output is switched on. An unlisted event is public
on its own URL and in its own `.ics` file, but it is in no list and no feed.

| Output | Public content |
|---|---|
| Calendar and event pages (HTML) | Title, date and time, status, place and its address, description, cover image, organizers' names and websites, price and booking link, categories, languages. |
| Event calendar file (`/<event>.ics`) | The same event data, the organizer's public email only when `showEmail` is on, and a link to the event page. |
| Subscription feed (`/<calendar>.ics`) | Upcoming events and recent past events (see `feedPastDays`), with the same fields. |
| RSS feed (`/<calendar>.rss`) | Upcoming events: title, link, description, categories, dates and the venue name and address. |
| JSON feed (`/<calendar>.json`, off by default) | Upcoming events, with the same fields as the event data, including venue coordinates and directions. |
| JSON-LD (in the event page) | Title, description, dates, status, location (venue address and coordinates), offers, cover image, organizer name, link and, when `showEmail` is on, the organizer's public email. |

Venue data, including coordinates, directions and the website, is public by
design: it is the place where the event happens.

### Never public

- **The online meeting link** (`onlineUrl`). It is entered in the Panel and
  stored in the content file. It appears in no output: not on the page, not in a
  calendar file, not in a feed, not in the JSON-LD and not in the JSON. The event
  object of the plugin has no such field, so no template can print it by mistake.
  Send the link to participants yourself.
- **An organizer's email address**, unless the organizer's `showEmail` toggle is
  on. Without it the address is not read at all.
- **Drafts.** An event in draft status appears nowhere. A draft, or a page below
  a draft, that another event links to (a series part, a venue, an organizer) is
  left out of that event's output as well: its title, date, address and link
  are not shown until it is published.

## Feeds are public addresses

The feeds and the calendar files are ordinary public URLs. Anyone who knows the
address can read them, and every calendar app that subscribes to one fetches it
again at its own intervals. Do not publish a feed for events that must stay
private, and do not put anything into an event's description or title that you
would not print on the page.

## What is stored, and where

Event, venue and organizer data lives in the site's content files, in the
repository and on the server, like the rest of the site. The plugin itself writes
nothing else: no database and no log of visitors. If Kirby's page cache is
active, it stores rendered pages as it does for any Kirby site. PHP errors about
invalid events go to the PHP error log and name the event ID, not any visitor.

## Organizers and people named in events

Organizers are people or organizations who run events. Their names, websites and
photos are published when the operator enters them. A person's email address
should only be published with that person's agreement; the `showEmail` toggle is
the place where that decision is recorded.

## Not legal advice

This summary describes the implementation. It is not legal advice. The operator
decides on the legal basis, on the wording of the privacy policy, and on what to
publish about organizers and venues.

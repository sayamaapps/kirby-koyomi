<?php

namespace Sayamaapps\Koyomi\Models;

use InvalidArgumentException;
use Kirby\Cms\Page;
use Sayamaapps\Koyomi\Event;
use Sayamaapps\Koyomi\Format;
use Sayamaapps\Koyomi\GoogleLink;
use Sayamaapps\Koyomi\Mapper;
use Sayamaapps\Koyomi\Options;

/**
 * Page model of template `koyomi-event`.           OWNER: task `kirby`
 * Contract: SPEC.md §8.11.
 *
 * Method names must not shadow content fields a template might call
 * ($page->eventStatus(), $page->price() …): none of the names below is a
 * field name.
 */
class EventPage extends Page
{
    /** Memoized by koyomi(); the flag also remembers a null result. */
    private ?Event $koyomiEvent = null;
    private bool $koyomiLoaded = false;

    /**
     * The Event (Mapper::event($this, Options::eventDefaults()), memoized on
     * this object. Null when the start date is invalid (error_log once).
     */
    public function koyomi(): ?Event
    {
        if (!$this->koyomiLoaded) {
            $this->koyomiLoaded = true;
            try {
                $this->koyomiEvent = Mapper::event($this, Options::eventDefaults($this->kirby()));
            } catch (InvalidArgumentException $e) {
                error_log('Koyomi: skipped ' . $this->id() . ': ' . $e->getMessage());
                $this->koyomiEvent = null;
            }
        }
        return $this->koyomiEvent;
    }

    /**
     * Ended (end <= Options::now()). False without a valid Event. For sitemap/SEO plugins.
     * Lowers the page cache expiry to the next start/end, like every time-dependent value here.
     */
    public function isPast(): bool
    {
        $event = $this->koyomi();
        if ($event === null) {
            return false;
        }
        koyomi_expires([$event]);
        return $event->isPast(Options::now($this->kirby()));
    }

    /** start <= now < end. Lowers the page cache expiry like isPast(). */
    public function isOngoing(): bool
    {
        $event = $this->koyomi();
        if ($event === null) {
            return false;
        }
        koyomi_expires([$event]);
        return $event->isOngoing(Options::now($this->kirby()));
    }

    /** $this->url() . '.ics' */
    public function icsUrl(): string
    {
        return $this->url() . '.ics';
    }

    /** GoogleLink::url($event, koyomi_i18n()) or "" when `koyomi.google` is off or no Event. */
    public function googleCalendarUrl(): string
    {
        $event = $this->koyomi();
        if ($event === null || !Options::google($this->kirby())) {
            return '';
        }
        return GoogleLink::url($event, koyomi_i18n());
    }

    /**
     * Plain array for own markup (SPEC.md §8.11):
     *   Event::toArray() + ['labels' => Format::labels($event, Options::languages()),
     *                       'isPast' => bool, 'isOngoing' => bool,
     *                       'googleCalendarUrl' => string]
     * [] without a valid Event (checked BEFORE koyomi_i18n(), so an invalid
     * page never touches Format). Then calls koyomi_i18n().
     *
     * Also lowers the page cache expiry to the next start/end: "Happening now"
     * and the calendar links in the array stop being true at that moment.
     * Own templates therefore need nothing more than this call.
     */
    public function koyomiData(): array
    {
        $event = $this->koyomi();
        if ($event === null) {
            return [];
        }

        koyomi_expires([$event]);
        koyomi_i18n();
        $kirby = $this->kirby();
        $now = Options::now($kirby);
        return $event->toArray() + [
            'labels' => Format::labels($event, Options::languages($kirby)),
            'isPast' => $event->isPast($now),
            'isOngoing' => $event->isOngoing($now),
            'googleCalendarUrl' => $this->googleCalendarUrl(),
        ];
    }

    /** The calendar this event belongs to (parent with template koyomi-calendar), else null. */
    public function koyomiCalendar(): ?Page
    {
        $parent = $this->parent();
        return $parent !== null && $parent->intendedTemplate()->name() === 'koyomi-calendar' ? $parent : null;
    }
}

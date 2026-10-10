<?php

namespace Koyomi\Models;

use Kirby\Cms\Page;
use Koyomi\Event;

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
    /**
     * The Event (Mapper::event($this, Options::eventDefaults())), memoized on
     * this object. Null when the start date is invalid (error_log once).
     */
    public function koyomi(): ?Event
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** Ended (end <= Options::now()). False without a valid Event. For sitemap/SEO plugins. */
    public function isPast(): bool
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** start <= now < end. */
    public function isOngoing(): bool
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** $this->url() . '.ics' */
    public function icsUrl(): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** GoogleLink::url($event, koyomi_i18n()) or "" when `koyomi.google` is off or no Event. */
    public function googleCalendarUrl(): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /**
     * Plain array for own markup (SPEC.md §8.11):
     *   Event::toArray() + ['labels' => Format::labels($event, Options::languages()),
     *                       'isPast' => bool, 'isOngoing' => bool,
     *                       'googleCalendarUrl' => string]
     * [] without a valid Event (checked BEFORE koyomi_i18n(), so an invalid
     * page never touches Format). Then calls koyomi_i18n().
     */
    public function koyomiData(): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** The calendar this event belongs to (parent with template koyomi-calendar), else null. */
    public function koyomiCalendar(): ?Page
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }
}

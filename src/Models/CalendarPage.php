<?php

namespace Koyomi\Models;

use Kirby\Cms\Page;

/**
 * Page model of template `koyomi-calendar`.        OWNER: task `kirby`
 * Contract: SPEC.md §8.11.
 */
class CalendarPage extends Page
{
    /** Repository::upcoming($this, $filters). @return list<\Koyomi\Event> */
    public function koyomiUpcoming(array $filters = []): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** Repository::past($this, $filters). @return list<\Koyomi\Event> */
    public function koyomiPast(array $filters = []): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /**
     * Repository::list($this, $query); $query null = the current request's
     * query parameters ($this->kirby()->request()->query()->toArray()).
     */
    public function koyomiList(?array $query = null): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** $this->url() . '.ics' */
    public function icsUrl(): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** icsUrl() with the scheme replaced by "webcal://" (https://ex.test/events.ics → webcal://ex.test/events.ics). */
    public function webcalUrl(): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** $this->url() . '.rss' */
    public function rssUrl(): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** $this->url() . '.json' */
    public function jsonUrl(): string
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }
}

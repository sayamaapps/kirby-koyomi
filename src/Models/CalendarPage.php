<?php

namespace Sayamaapps\Koyomi\Models;

use Kirby\Cms\Page;
use Sayamaapps\Koyomi\Repository;

/**
 * Page model of template `koyomi-calendar`.        OWNER: task `kirby`
 * Contract: SPEC.md §8.11.
 */
class CalendarPage extends Page
{
    /** Repository::upcoming($this, $filters). @return list<\Sayamaapps\Koyomi\Event> */
    public function koyomiUpcoming(array $filters = []): array
    {
        return Repository::upcoming($this, $filters);
    }

    /** Repository::past($this, $filters). @return list<\Sayamaapps\Koyomi\Event> */
    public function koyomiPast(array $filters = []): array
    {
        return Repository::past($this, $filters);
    }

    /**
     * Repository::list($this, $query); $query null = the current request's
     * query parameters ($this->kirby()->request()->query()->toArray()).
     */
    public function koyomiList(?array $query = null): array
    {
        $query ??= $this->kirby()->request()->query()->toArray();
        return Repository::list($this, $query);
    }

    /** $this->url() . '.ics' */
    public function icsUrl(): string
    {
        return $this->url() . '.ics';
    }

    /** icsUrl() with the scheme replaced by "webcal://" (https://ex.test/events.ics → webcal://ex.test/events.ics). */
    public function webcalUrl(): string
    {
        return preg_replace('~^https?://~i', 'webcal://', $this->icsUrl()) ?? $this->icsUrl();
    }

    /** $this->url() . '.rss' */
    public function rssUrl(): string
    {
        return $this->url() . '.rss';
    }

    /** $this->url() . '.json' */
    public function jsonUrl(): string
    {
        return $this->url() . '.json';
    }
}

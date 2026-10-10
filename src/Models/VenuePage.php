<?php

namespace Koyomi\Models;

use Kirby\Cms\Page;
use Koyomi\Venue;

/**
 * Page model of template `koyomi-venue`.           OWNER: task `kirby`
 */
class VenuePage extends Page
{
    /** Mapper::venue($this), memoized. */
    public function koyomiVenue(): Venue
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** Repository::forVenue($this->kirby(), $this). @return list<\Koyomi\Event> */
    public function upcomingEvents(): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }
}

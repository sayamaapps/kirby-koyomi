<?php

namespace Koyomi\Models;

use Kirby\Cms\Page;
use Koyomi\Organizer;

/**
 * Page model of template `koyomi-organizer`.       OWNER: task `kirby`
 */
class OrganizerPage extends Page
{
    /** Mapper::organizer($this), memoized. */
    public function koyomiOrganizer(): Organizer
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }

    /** Repository::forOrganizer($this->kirby(), $this). @return list<\Koyomi\Event> */
    public function upcomingEvents(): array
    {
        throw new \LogicException('Koyomi: not implemented yet: ' . __METHOD__);
    }
}

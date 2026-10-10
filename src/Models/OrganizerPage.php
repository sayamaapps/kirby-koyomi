<?php

namespace Koyomi\Models;

use Kirby\Cms\Page;
use Koyomi\Mapper;
use Koyomi\Organizer;
use Koyomi\Repository;

/**
 * Page model of template `koyomi-organizer`.       OWNER: task `kirby`
 */
class OrganizerPage extends Page
{
    private ?Organizer $koyomiOrganizerValue = null;

    /** Mapper::organizer($this), memoized. */
    public function koyomiOrganizer(): Organizer
    {
        return $this->koyomiOrganizerValue ??= Mapper::organizer($this);
    }

    /** Repository::forOrganizer($this->kirby(), $this). @return list<\Koyomi\Event> */
    public function upcomingEvents(): array
    {
        return Repository::forOrganizer($this->kirby(), $this);
    }
}

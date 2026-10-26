<?php

namespace Sayamaapps\Koyomi\Models;

use Kirby\Cms\Page;
use Sayamaapps\Koyomi\Mapper;
use Sayamaapps\Koyomi\Organizer;
use Sayamaapps\Koyomi\Repository;

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

    /** Repository::forOrganizer($this->kirby(), $this). @return list<\Sayamaapps\Koyomi\Event> */
    public function upcomingEvents(): array
    {
        return Repository::forOrganizer($this->kirby(), $this);
    }
}

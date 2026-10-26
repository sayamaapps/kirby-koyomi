<?php

namespace Sayamaapps\Koyomi\Models;

use Kirby\Cms\Page;
use Sayamaapps\Koyomi\Mapper;
use Sayamaapps\Koyomi\Repository;
use Sayamaapps\Koyomi\Venue;

/**
 * Page model of template `koyomi-venue`.
 */
class VenuePage extends Page
{
	private Venue|null $koyomiVenueValue = null;

	/** Mapper::venue($this), memoized. */
	public function koyomiVenue(): Venue
	{
		return $this->koyomiVenueValue ??= Mapper::venue($this);
	}

	/** Repository::forVenue($this->kirby(), $this). @return list<\Sayamaapps\Koyomi\Event> */
	public function upcomingEvents(): array
	{
		return Repository::forVenue($this->kirby(), $this);
	}
}

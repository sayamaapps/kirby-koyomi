<?php

namespace Sayamaapps\Koyomi\Models;

use Kirby\Cms\Page;
use Sayamaapps\Koyomi\Event;
use Sayamaapps\Koyomi\Mapper;
use Sayamaapps\Koyomi\Repository;
use Sayamaapps\Koyomi\Venue;

/**
 * Page model of template `koyomi-venue`.
 *
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-koyomi
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class VenuePage extends Page
{
	private Venue|null $koyomiVenueValue = null;

	/** Same as upcomingEvents(), with the plugin prefix. */
	public function koyomiUpcomingEvents(): array
	{
		return $this->upcomingEvents();
	}

	/** Mapper::venue($this), memoized. */
	public function koyomiVenue(): Venue
	{
		return $this->koyomiVenueValue ??= Mapper::venue($this);
	}

	/**
	 * Repository::forVenue($this->kirby(), $this).
	 *
	 * @return list<Event>
	 */
	public function upcomingEvents(): array
	{
		return Repository::forVenue($this->kirby(), $this);
	}
}

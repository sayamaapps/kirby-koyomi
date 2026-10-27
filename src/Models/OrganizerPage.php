<?php

namespace Sayamaapps\Koyomi\Models;

use Kirby\Cms\Page;
use Sayamaapps\Koyomi\Event;
use Sayamaapps\Koyomi\Mapper;
use Sayamaapps\Koyomi\Organizer;
use Sayamaapps\Koyomi\Repository;

/**
 * Page model of template `koyomi-organizer`.
 *
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-koyomi
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class OrganizerPage extends Page
{
	private Organizer|null $koyomiOrganizerValue = null;

	/** Mapper::organizer($this), memoized. */
	public function koyomiOrganizer(): Organizer
	{
		return $this->koyomiOrganizerValue ??= Mapper::organizer($this);
	}

	/** Same as upcomingEvents(), with the plugin prefix. */
	public function koyomiUpcomingEvents(): array
	{
		return $this->upcomingEvents();
	}

	/**
	 * Repository::forOrganizer($this->kirby(), $this).
	 *
	 * @return list<Event>
	 */
	public function upcomingEvents(): array
	{
		return Repository::forOrganizer($this->kirby(), $this);
	}
}

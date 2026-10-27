<?php

namespace Sayamaapps\Koyomi\Models;

use Kirby\Cms\Page;
use Sayamaapps\Koyomi\Event;
use Sayamaapps\Koyomi\Repository;

/**
 * Page model of template `koyomi-calendar`.
 *
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-koyomi
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class CalendarPage extends Page
{
	/** $this->url() . '.ics' */
	public function icsUrl(): string
	{
		return $this->url() . '.ics';
	}

	/** $this->url() . '.json' */
	public function jsonUrl(): string
	{
		return $this->url() . '.json';
	}

	/** Same as icsUrl(), with the plugin prefix. */
	public function koyomiIcsUrl(): string
	{
		return $this->icsUrl();
	}

	/** Same as jsonUrl(), with the plugin prefix. */
	public function koyomiJsonUrl(): string
	{
		return $this->jsonUrl();
	}

	/**
	 * Repository::list($this, $query); $query null = the current request's
	 * query parameters ($this->kirby()->request()->query()->toArray()).
	 */
	public function koyomiList(array|null $query = null): array
	{
		$query ??= $this->kirby()->request()->query()->toArray();
		return Repository::list($this, $query);
	}

	/**
	 * Repository::past($this, $filters).
	 *
	 * @return list<Event>
	 */
	public function koyomiPast(array $filters = []): array
	{
		return Repository::past($this, $filters);
	}

	/** Same as rssUrl(), with the plugin prefix. */
	public function koyomiRssUrl(): string
	{
		return $this->rssUrl();
	}

	/**
	 * Repository::upcoming($this, $filters).
	 *
	 * @return list<Event>
	 */
	public function koyomiUpcoming(array $filters = []): array
	{
		return Repository::upcoming($this, $filters);
	}

	/** Same as webcalUrl(), with the plugin prefix. */
	public function koyomiWebcalUrl(): string
	{
		return $this->webcalUrl();
	}

	/** $this->url() . '.rss' */
	public function rssUrl(): string
	{
		return $this->url() . '.rss';
	}

	/** icsUrl() with the scheme replaced by "webcal://" (https://ex.test/events.ics → webcal://ex.test/events.ics). */
	public function webcalUrl(): string
	{
		return preg_replace('~^https?://~i', 'webcal://', $this->icsUrl()) ?? $this->icsUrl();
	}
}

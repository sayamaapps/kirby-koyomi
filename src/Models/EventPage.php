<?php

namespace Sayamaapps\Koyomi\Models;

use InvalidArgumentException;
use Kirby\Cms\Page;
use Sayamaapps\Koyomi\Api;
use Sayamaapps\Koyomi\Event;
use Sayamaapps\Koyomi\Format;
use Sayamaapps\Koyomi\GoogleLink;
use Sayamaapps\Koyomi\Mapper;
use Sayamaapps\Koyomi\Options;

/**
 * Page model of template `koyomi-event`.
 *
 * Method names must not shadow content fields a template might call
 * ($page->eventStatus(), $page->price() …): none of the names below is a
 * field name.
 */
class EventPage extends Page
{
	/** Memoized by koyomi(); the flag also remembers a null result. */
	private Event|null $koyomiEvent = null;
	private bool $koyomiLoaded = false;

	/** GoogleLink::url($event, Api::i18n($this->kirby())) or "" when `koyomi.google` is off or no Event. */
	public function googleCalendarUrl(): string
	{
		$event = $this->koyomi();
		if ($event === null || !Options::google($this->kirby())) {
			return '';
		}
		return GoogleLink::url($event, Api::i18n($this->kirby()));
	}

	/** $this->url() . '.ics' */
	public function icsUrl(): string
	{
		return $this->url() . '.ics';
	}

	/** start <= now < end. Lowers the page cache expiry like isPast(). */
	public function isOngoing(): bool
	{
		$event = $this->koyomi();
		if ($event === null) {
			return false;
		}
		Api::expires($this->kirby(), [$event]);
		return $event->isOngoing(Options::now($this->kirby()));
	}

	/**
	 * Ended (end <= Options::now()). False without a valid Event. For sitemap/SEO plugins.
	 * Lowers the page cache expiry to the next start/end, like every time-dependent value here.
	 */
	public function isPast(): bool
	{
		$event = $this->koyomi();
		if ($event === null) {
			return false;
		}
		Api::expires($this->kirby(), [$event]);
		return $event->isPast(Options::now($this->kirby()));
	}

	/**
	 * The Event (Mapper::event($this, Options::eventDefaults()), memoized on
	 * this object. Null when the start date is invalid (error_log once).
	 */
	public function koyomi(): Event|null
	{
		if (!$this->koyomiLoaded) {
			$this->koyomiLoaded = true;
			try {
				$this->koyomiEvent = Mapper::event($this, Options::eventDefaults($this->kirby()));
			} catch (InvalidArgumentException $e) {
				error_log('Koyomi: skipped ' . $this->id() . ': ' . $e->getMessage());
				$this->koyomiEvent = null;
			}
		}
		return $this->koyomiEvent;
	}

	/** The calendar this event belongs to (parent with template koyomi-calendar), else null. */
	public function koyomiCalendar(): Page|null
	{
		$parent = $this->parent();
		return $parent !== null && $parent->intendedTemplate()->name() === 'koyomi-calendar' ? $parent : null;
	}

	/**
	 * Plain array for own markup:
	 *   Event::toArray() + ['labels' => Format::labels($event, Options::languages()),
	 *                       'isPast' => bool, 'isOngoing' => bool,
	 *                       'googleCalendarUrl' => string]
	 * [] without a valid Event (checked BEFORE Api::i18n(), so an invalid
	 * page never touches Format). Then calls Api::i18n().
	 *
	 * Also lowers the page cache expiry to the next start/end: "Happening now"
	 * and the calendar links in the array stop being true at that moment.
	 * Own templates therefore need nothing more than this call.
	 */
	public function koyomiData(): array
	{
		$event = $this->koyomi();
		if ($event === null) {
			return [];
		}

		Api::expires($this->kirby(), [$event]);
		Api::i18n($this->kirby());
		$kirby = $this->kirby();
		$now = Options::now($kirby);
		return $event->toArray() + [
			'labels' => Format::labels($event, Options::languages($kirby)),
			'isPast' => $event->isPast($now),
			'isOngoing' => $event->isOngoing($now),
			'googleCalendarUrl' => $this->googleCalendarUrl(),
		];
	}
}

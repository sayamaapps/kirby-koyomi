<?php

namespace Sayamaapps\Koyomi;

use Kirby\Cms\Blocks;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Content\Content;
use Kirby\Content\Field;
use Kirby\Toolkit\Str;

/**
 * Kirby pages → Koyomi value objects.
 *
 * Reads fields ONLY via $page->content()->get('<field>') — never via magic
 * methods: `status` and `image` are Page methods, and a site's own page
 * model might add more.
 *
 * Shared fields (Blueprints::SHARED_FIELDS: dates, status, price, venue,
 * organizers …) are read from the DEFAULT language on a multi-language site.
 * Kirby only stops writing them into translations; a value saved in a
 * translation before that (or a file edited by hand) would otherwise still
 * win, and the same event would then have two dates under one ICS UID.
 *
 * PRIVACY: never reads `onlineUrl`; reads organizer `email` only when
 * `showEmail` is on.
 *
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-koyomi
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class Mapper
{
	/** Longest description derived from the text blocks. */
	public const EXCERPT_LENGTH = 300;

	/**
	 * Event::fromArray(self::raw($page), $defaults).
	 *
	 * @param array{timezone?:string,currency?:string,defaultDuration?:int} $defaults Options::eventDefaults()
	 * @throws \InvalidArgumentException when the page has no valid start date
	 */
	public static function event(Page $page, array $defaults = []): Event
	{
		return Event::fromArray(self::raw($page), $defaults);
	}

	/**
	 * Plain text of the text blocks: HTML → text, entities decoded,
	 * whitespace collapsed, cut to EXCERPT_LENGTH characters.
	 */
	private static function excerptFromText(Blocks $blocks): string
	{
		// Block-level tags become spaces, so two paragraphs do not run together;
		// inline tags (<strong>, <em>) vanish without a space.
		$html = (string)preg_replace('~</?(p|h[1-6]|li|ul|ol|div|blockquote|br|figure|section)\b[^>]*>~i', ' ', $blocks->toHtml());
		$plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$plain = trim((string)preg_replace('/[\s\x{00A0}]+/u', ' ', $plain));
		return $plain === '' ? '' : Str::excerpt($plain, self::EXCERPT_LENGTH, false);
	}

	/** The field $name from the shared content when it is shared, else from the page's own content. */
	private static function field(Content $own, Content $shared, string $name): Field
	{
		$content = in_array($name, Blueprints::SHARED_FIELDS, true) ? $shared : $own;
		/** @var Field $field */
		$field = $content->get($name);

		return $field;
	}

	/**
	 * @return array{0: string, 1: string} URL and alt text of a file, empty strings when there is none
	 */
	private static function file(File|null $file): array
	{
		if ($file === null) {
			return ['', ''];
		}
		return [$file->url(), Normalize::string($file->alt()->value())];
	}

	/** Page UUID id without "page://" when UUIDs are on, else the page id with "/" → "-". */
	public static function id(Page $page): string
	{
		return $page->uuid()?->id() ?? str_replace('/', '-', $page->id());
	}

	/**
	 * A draft, or a page below one. Kirby's own isDraft() looks at the page
	 * alone: a venue under a container the editor put back into draft is
	 * itself "published" (unlisted) for Kirby, yet its URL answers 404 because
	 * Site::find() does not walk into drafts, and toPage()/page() resolve it
	 * anyway. Every public published-check of Koyomi goes through here.
	 */
	public static function isDraft(Page $page): bool
	{
		if ($page->isDraft()) {
			return true;
		}
		foreach ($page->parents() as $parent) {
			if ($parent->isDraft()) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Not a draft (nor below one) and of the expected template. Drafts are
	 * what an editor has not published yet; they must not reach any output
	 * through a reference. Unlisted pages are published
	 * (they have their own URL) and stay usable.
	 */
	private static function isPublished(Page $page, string $template): bool
	{
		return !self::isDraft($page) && $page->intendedTemplate()->name() === $template;
	}

	/**
	 * Organizer::fromArray() from a koyomi-organizer page.
	 * The email is passed only when `showEmail` is on, so a hidden address
	 * never becomes part of any object.
	 */
	public static function organizer(Page $page): Organizer
	{
		/** @var Content $content */
		$content = $page->content();
		$shared = self::sharedContent($page);
		$field = fn (string $name): Field => self::field($content, $shared, $name);
		$get = fn (string $name): mixed => $field($name)->value();
		[$image, $imageAlt] = self::file($field('photo')->toFile());
		$showEmail = Normalize::bool($get('showEmail'));

		return Organizer::fromArray([
			'id' => self::id($page),
			'name' => $content->get('title')->value(),
			'kind' => $get('kind'),
			'url' => $page->url(),
			'website' => $get('website'),
			'email' => $showEmail ? $get('email') : '',
			'image' => $image,
			'imageAlt' => $imageAlt,
		]);
	}

	/**
	 * The fromArray() input for an event page.
	 * Exposed for tests and debugging. Venue and organizers are already
	 * value objects, which Event::fromArray() accepts as they are.
	 */
	public static function raw(Page $page): array
	{
		/** @var Content $content */
		$content = $page->content();
		$shared = self::sharedContent($page);
		$field = fn (string $name): Field => self::field($content, $shared, $name);
		$get = fn (string $name): mixed => $field($name)->value();

		// Toggles: an empty stored value means the Panel default (in person on, online off).
		$inPersonField = $field('inPerson');
		$inPerson = $inPersonField->isEmpty() ? true : Normalize::bool($inPersonField->value());
		$online = Normalize::bool($get('online'));
		$storedAttendance = Normalize::string($get('attendance'));
		$attendance = in_array($storedAttendance, Attendance::ALL, true)
			? $storedAttendance
			: Attendance::fromToggles($inPerson, $online);

		$excerpt = Normalize::string($get('excerpt'));
		if ($excerpt === '') {
			$excerpt = self::excerptFromText($content->get('text')->toBlocks());
		}

		[$image, $imageAlt] = self::file($field('cover')->toFile());

		// A reference to a draft (or to a page of another template) is dropped:
		// the field stores a UUID, and toPage()/toPages() resolve drafts too.
		// Public output must not show the title, date, address or URL of an
		// unpublished page; the link would answer 404 anyway.
		$venuePage = $field('venue')->toPage();
		$organizers = [];
		foreach ($field('organizers')->toPages() as $organizerPage) {
			if (self::isPublished($organizerPage, 'koyomi-organizer')) {
				$organizers[] = self::organizer($organizerPage);
			}
		}

		$series = [];
		foreach ($field('series')->toPages() as $sibling) {
			// Only parts of this same calendar: a UUID can point anywhere.
			if (!self::isPublished($sibling, 'koyomi-event') || $sibling->parent()?->id() !== $page->parent()?->id()) {
				continue;
			}
			$siblingShared = self::sharedContent($sibling);
			/** @var Content $siblingContent */
			$siblingContent = $sibling->content();
			$siblingField = fn (string $name): Field => self::field($siblingContent, $siblingShared, $name);
			$series[] = [
				'id' => self::id($sibling),
				'title' => $siblingContent->get('title')->value(),
				'url' => $sibling->url(),
				'startDate' => $siblingField('startDate')->value(),
				'startTime' => $siblingField('startTime')->value(),
				'allDay' => $siblingField('allDay')->value(),
				'timezone' => $siblingField('timezone')->value(),
				'part' => $siblingField('seriesPart')->value(),
			];
		}

		return [
			'id' => self::id($page),
			'title' => $content->get('title')->value(),
			'url' => $page->url(),
			'startDate' => $get('startDate'),
			'startTime' => $get('startTime'),
			'endDate' => $get('endDate'),
			'endTime' => $get('endTime'),
			'allDay' => $get('allDay'),
			'timezone' => $get('timezone'),
			'description' => $excerpt,
			'image' => $image,
			'imageAlt' => $imageAlt,
			'attendance' => $attendance,
			'venue' => $venuePage === null || !self::isPublished($venuePage, 'koyomi-venue') ? null : self::venue($venuePage),
			'onlinePlatform' => $get('onlinePlatform'),
			'status' => $get('eventStatus'),
			'previousStart' => $get('previousStart'),
			'statusNote' => $get('statusNote'),
			'isFree' => $get('isFree'),
			'price' => $get('price'),
			'currency' => $get('currency'),
			'priceNote' => $get('priceNote'),
			'availability' => $get('availability'),
			'bookingUrl' => $get('bookingUrl'),
			'bookingLabel' => $get('bookingLabel'),
			'organizers' => $organizers,
			'categories' => $get('categories'),
			'languages' => $get('eventLanguages'),
			'featured' => $get('featured'),
			'series' => $series,
			'seriesPart' => $get('seriesPart'),
			'modified' => (int)$page->modified(),
		];
	}

	/**
	 * The content that holds the shared fields: the default language's on a
	 * multi-language site, the current one otherwise (the same object).
	 */
	private static function sharedContent(Page $page): Content
	{
		$kirby = $page->kirby();
		$default = $kirby->multilang() ? $kirby->defaultLanguage() : null;
		return $default === null ? $page->content() : $page->content($default->code());
	}

	/**
	 * Venue::fromArray() from a koyomi-venue page. The
	 * `sayamaapps.koyomi.mapUrl` closure, when set, supplies the map link; it receives
	 * the venue without one.
	 */
	public static function venue(Page $page): Venue
	{
		/** @var Content $content */
		$content = $page->content();
		$shared = self::sharedContent($page);
		$field = fn (string $name): Field => self::field($content, $shared, $name);
		$get = fn (string $name): mixed => $field($name)->value();
		[$mapImage, $mapImageAlt] = self::file($field('mapImage')->toFile());

		$data = [
			'id' => self::id($page),
			'name' => $content->get('title')->value(),
			'street' => $get('street'),
			'postalCode' => $get('postalCode'),
			'city' => $get('city'),
			'region' => $get('region'),
			'country' => $get('country'),
			'lat' => $get('lat'),
			'lng' => $get('lng'),
			'url' => $page->url(),
			'website' => $get('website'),
			'directions' => $content->get('directions')->value(),
			'mapImage' => $mapImage,
			'mapImageAlt' => $mapImageAlt,
			'showMap' => $get('showMap'),
		];

		$venue = Venue::fromArray($data);
		$mapUrl = Options::mapUrl($page->kirby());
		if ($mapUrl !== null) {
			$venue = Venue::fromArray($data + ['mapLink' => (string)$mapUrl($venue)]);
		}
		return $venue;
	}
}

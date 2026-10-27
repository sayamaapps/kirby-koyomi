<?php

namespace Sayamaapps\Koyomi;

/**
 * Page blueprints as PHP arrays, Kirby-free.
 *
 * index.php registers them as closures
 *   'pages/koyomi-event' => fn () => Blueprints::event(Options::blueprintSettings(App::instance()))
 * so option-dependent choices (zones, currencies, languages) are read when
 * the Panel needs the blueprint. A site overrides one by placing
 * site/blueprints/pages/koyomi-event.yml (Kirby looks there first).
 *
 * Labels are I18n::all('<key>') arrays (['en' => …, 'de' => …]).
 *
 * $settings shape (Options::blueprintSettings()):
 *   ['timezone' => 'Europe/Berlin', 'timezones' => ['Europe/Berlin', …],
 *    'currency' => 'EUR', 'currencies' => ['EUR', 'CZK', 'CHF'],
 *    'languages' => ['de' => '', 'en' => '', 'cs' => 'Čeština']]   // '' = translation language.<code>
 *
 * Why arrays and not YAML: the choices depend on the configuration, and a
 * closure per blueprint is the only way Kirby re-reads them at Panel time.
 * Missing settings keys fall back to the defaults below, so a hand-made
 * array (tests) works too.
 *
 * @package   Sayamaapps\Koyomi
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-koyomi
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
final class Blueprints
{
	/** Where a page's status is set: the Panel offers drafts and unlisted pages only (containers and children). */
	private const CONTAINER_STATUS = ['draft' => true, 'unlisted' => true, 'listed' => false];

	/** Fallbacks when a settings key is missing (same values as Options' defaults). */
	private const DEFAULT_CURRENCIES = ['EUR', 'CZK', 'CHF'];

	/**
	 * Event fields a chat bot may set through the package Fude: the fields
	 * of the events collection that the calendar's events section declares.
	 * Fields of a type Fude cannot set (blocks, files, tags, multiselect,
	 * pages with several pages) are not among them.
	 */
	public const FUDE_FIELDS = [
		'excerpt', 'startDate', 'startTime', 'endDate', 'endTime', 'inPerson', 'online', 'venue', 'onlineUrl',
		'eventStatus', 'statusNote', 'isFree', 'price', 'priceNote', 'availability', 'bookingUrl',
	];

	/** Blueprint names index.php registers, all under pages/. */
	public const NAMES = ['koyomi-calendar', 'koyomi-event', 'koyomi-venues', 'koyomi-venue', 'koyomi-organizers', 'koyomi-organizer'];

	/**
	 * Fields with one value for every language: dates, status, prices, links,
	 * venue and organizer references, and the venue and organizer facts.
	 * Kirby stores them only in the default language (translate: false), so a
	 * cancellation or a new date reaches every language's page, ICS file and
	 * feed, which all share one UID. Text fields (title, excerpt, text, notes,
	 * directions, labels, categories, intro) stay translatable.
	 */
	public const SHARED_FIELDS = [
		// event
		'cover', 'startDate', 'startTime', 'endDate', 'endTime', 'allDay', 'timezone',
		'inPerson', 'online', 'venue', 'onlinePlatform', 'onlineUrl',
		'eventStatus', 'previousStart', 'isFree', 'price', 'currency', 'availability', 'bookingUrl',
		'organizers', 'eventLanguages', 'featured', 'series', 'seriesPart',
		// venue
		'street', 'postalCode', 'city', 'region', 'country', 'lat', 'lng', 'mapImage', 'showMap', 'website',
		// organizer
		'kind', 'email', 'showEmail', 'photo',
	];

	public static function calendar(array $settings): array
	{
		return [
			'title' => I18n::all('blueprint.calendar'),
			'icon' => 'calendar',
			'options' => ['changeTemplate' => false],
			'columns' => [
				'main' => [
					'width' => '2/3',
					'sections' => [
						// Unpublished events first: they are what the editor still has to finish.
						'drafts' => [
							'type' => 'pages',
							'label' => I18n::all('section.drafts'),
							'status' => 'draft',
							'templates' => ['koyomi-event'],
							'sortBy' => 'startDate desc',
							'info' => '{{ page.startDate.toDate("d.m.Y") }}',
						],
						'events' => [
							'type' => 'pages',
							'label' => I18n::all('section.events'),
							'status' => 'published',
							'templates' => ['koyomi-event'],
							'sortBy' => 'startDate desc',
							'info' => '{{ page.startDate.toDate("d.m.Y") }}',
							'limit' => 50,
							// For the package Fude (1.7 or later): the events are one collection a chat
							// bot may change, with the fields marked in koyomi-event. Kirby ignores the key.
							// The start date is the key: the same workshop comes back under the same title.
							'fude' => ['key' => 'startDate', 'description' => I18n::all('fude.events')],
						],
					],
				],
				'sidebar' => [
					'width' => '1/3',
					'sections' => [
						'fields' => [
							'type' => 'fields',
							'fields' => [
								'intro' => [
									'label' => I18n::all('field.intro'),
									'type' => 'textarea',
								],
							],
						],
					],
				],
			],
		];
	}

	public static function event(array $settings): array
	{
		return self::fude(self::shared([
			'title' => I18n::all('blueprint.event'),
			'icon' => 'calendar',
			// Folder name from the start date: 20270312_retreat. Kirby re-derives it on change.
			'num' => '{{ page.startDate.toDate("Ymd") }}',
			// A new event asks for its start date first; the rest is filled in its tabs.
			'create' => ['fields' => ['startDate']],
			'options' => ['changeTemplate' => false],
			'tabs' => [
				'event' => [
					'label' => I18n::all('tab.event'),
					'columns' => [
						'main' => [
							'width' => '2/3',
							'fields' => [
								'excerpt' => [
									'label' => I18n::all('field.excerpt'),
									'type' => 'textarea',
									'buttons' => false,
									'maxlength' => 500,
									'help' => I18n::all('field.excerpt.help'),
								],
								'text' => [
									'label' => I18n::all('field.text'),
									'type' => 'blocks',
								],
							],
						],
						'sidebar' => [
							'width' => '1/3',
							'fields' => [
								'cover' => [
									'label' => I18n::all('field.cover'),
									'type' => 'files',
									'multiple' => false,
									'query' => 'page.images',
								],
							],
						],
					],
				],
				'when' => [
					'label' => I18n::all('tab.when'),
					'fields' => [
						'startDate' => [
							'label' => I18n::all('field.startDate'),
							'type' => 'date',
							'required' => true,
							'width' => '1/2',
						],
						'startTime' => [
							'label' => I18n::all('field.startTime'),
							'type' => 'time',
							'width' => '1/2',
							'when' => ['allDay' => false],
							'required' => true,
						],
						'endDate' => [
							'label' => I18n::all('field.endDate'),
							'type' => 'date',
							'width' => '1/2',
							'help' => I18n::all('field.endDate.help'),
						],
						'endTime' => [
							'label' => I18n::all('field.endTime'),
							'type' => 'time',
							'width' => '1/2',
							'when' => ['allDay' => false],
							// For the package Fude: a chat bot asks for it before a new event. Kirby ignores the key.
							'fudeAsk' => true,
						],
						'allDay' => [
							'label' => I18n::all('field.allDay'),
							'type' => 'toggle',
							'default' => false,
							'width' => '1/2',
						],
						'timezone' => [
							'label' => I18n::all('field.timezone'),
							'type' => 'select',
							'options' => self::zoneOptions($settings),
							'default' => self::setting($settings, 'timezone', Time::DEFAULT_ZONE),
							'required' => true,
							'width' => '1/2',
						],
						'inPerson' => [
							'label' => I18n::all('field.inPerson'),
							'type' => 'toggle',
							'default' => true,
							'width' => '1/2',
						],
						'online' => [
							'label' => I18n::all('field.online'),
							'type' => 'toggle',
							'default' => false,
							'width' => '1/2',
						],
						// Venue is required for offline and hybrid events only (Kirby's when tests equality).
						'venue' => [
							'label' => I18n::all('field.venue'),
							'type' => 'pages',
							'multiple' => false,
							'query' => 'kirby.collection("koyomi/venues")',
							'when' => ['inPerson' => true],
							'required' => true,
						],
						'onlinePlatform' => [
							'label' => I18n::all('field.onlinePlatform'),
							'type' => 'text',
							'width' => '1/2',
							'when' => ['online' => true],
							'placeholder' => I18n::all('field.onlinePlatform.placeholder'),
						],
						// Private: stored in the content, read by nothing that renders (Event has no such property).
						'onlineUrl' => [
							'label' => I18n::all('field.onlineUrl'),
							'type' => 'url',
							'width' => '1/2',
							'when' => ['online' => true],
							'required' => true,
							'help' => I18n::all('field.onlineUrl.help'),
						],
					],
				],
				'status' => [
					'label' => I18n::all('tab.status'),
					'fields' => [
						'eventStatus' => [
							'label' => I18n::all('field.eventStatus'),
							'type' => 'select',
							'options' => self::options(Status::ALL, 'status.'),
							'default' => Status::SCHEDULED,
							'required' => true,
						],
						'previousStart' => [
							'label' => I18n::all('field.previousStart'),
							'type' => 'date',
							'time' => true,
							'when' => ['eventStatus' => Status::RESCHEDULED],
						],
						'statusNote' => [
							'label' => I18n::all('field.statusNote'),
							'type' => 'textarea',
							'buttons' => false,
						],
					],
				],
				'tickets' => [
					'label' => I18n::all('tab.tickets'),
					'fields' => [
						'isFree' => [
							'label' => I18n::all('field.isFree'),
							'type' => 'toggle',
							'default' => false,
						],
						'price' => [
							'label' => I18n::all('field.price'),
							'type' => 'number',
							'min' => 0,
							'step' => 0.01,
							'width' => '1/3',
							'when' => ['isFree' => false],
							'fudeAsk' => true,
						],
						'currency' => [
							'label' => I18n::all('field.currency'),
							'type' => 'select',
							'options' => self::plainOptions($settings['currencies'] ?? self::DEFAULT_CURRENCIES),
							'default' => self::setting($settings, 'currency', self::DEFAULT_CURRENCIES[0]),
							'width' => '1/3',
							'when' => ['isFree' => false],
						],
						'availability' => [
							'label' => I18n::all('field.availability'),
							'type' => 'select',
							'options' => self::options(Availability::ALL, 'availability.'),
							'default' => Availability::AVAILABLE,
							'width' => '1/3',
						],
						'priceNote' => [
							'label' => I18n::all('field.priceNote'),
							'type' => 'text',
							'placeholder' => I18n::all('field.priceNote.placeholder'),
						],
						'bookingUrl' => [
							'label' => I18n::all('field.bookingUrl'),
							'type' => 'url',
							'width' => '2/3',
							'fudeAsk' => true,
						],
						'bookingLabel' => [
							'label' => I18n::all('field.bookingLabel'),
							'type' => 'text',
							'width' => '1/3',
							'placeholder' => I18n::all('booking.default'),
						],
					],
				],
				'details' => [
					'label' => I18n::all('tab.details'),
					'fields' => [
						'organizers' => [
							'label' => I18n::all('field.organizers'),
							'type' => 'pages',
							'query' => 'kirby.collection("koyomi/organizers")',
						],
						'categories' => [
							'label' => I18n::all('field.categories'),
							'type' => 'tags',
						],
						'eventLanguages' => [
							'label' => I18n::all('field.languages'),
							'type' => 'multiselect',
							'options' => self::languageOptions($settings),
						],
						'featured' => [
							'label' => I18n::all('field.featured'),
							'type' => 'toggle',
							'default' => false,
						],
						'series' => [
							'label' => I18n::all('field.series'),
							'type' => 'pages',
							'query' => 'page.siblings(false)',
						],
						'seriesPart' => [
							'label' => I18n::all('field.seriesPart'),
							'type' => 'number',
							'min' => 1,
							'step' => 1,
							'width' => '1/3',
						],
					],
				],
			],
		]));
	}

	/**
	 * Marks every field named in FUDE_FIELDS with `fude: true`, wherever it
	 * sits. The package Fude reads the markers of koyomi-event only through
	 * the calendar's events section; Kirby ignores the key.
	 */
	private static function fude(array $blueprint): array
	{
		$walk = function (array $node) use (&$walk): array {
			foreach ($node as $key => $value) {
				if (!is_array($value)) {
					continue;
				}
				if ($key === 'fields') {
					foreach ($value as $name => $field) {
						if (is_string($name) && in_array($name, self::FUDE_FIELDS, true) && is_array($field)) {
							$value[$name] = $field + ['fude' => true];
						}
					}
				}
				$node[$key] = $walk($value);
			}
			return $node;
		};
		return $walk($blueprint);
	}

	/**
	 * Event languages: text = the custom label when set, else the shipped
	 * translation of language.<code>, else the upper-cased code (unknown codes).
	 */
	private static function languageOptions(array $settings): array
	{
		$options = [];
		foreach ($settings['languages'] ?? [] as $key => $value) {
			// A plain list (['de', 'cs']) has no labels: the value is the code.
			[$code, $label] = is_int($key) ? [(string)$value, ''] : [(string)$key, trim((string)$value)];
			if ($label !== '') {
				$text = $label;
			} elseif (I18n::has('language.' . $code)) {
				$text = I18n::all('language.' . $code);
			} else {
				$text = strtoupper($code);
			}
			$options[] = ['value' => $code, 'text' => $text];
		}
		return $options;
	}

	/**
	 * Select options [['value' => 'scheduled', 'text' => I18n::all('status.scheduled')], …] for a list of values and a key prefix.
	 *
	 * @param list<string> $values
	 */
	public static function options(array $values, string $prefix): array
	{
		$options = [];
		foreach ($values as $value) {
			$options[] = ['value' => $value, 'text' => I18n::all($prefix . $value)];
		}
		return $options;
	}

	public static function organizer(array $settings): array
	{
		return self::shared([
			'title' => I18n::all('blueprint.organizer'),
			'icon' => 'user',
			'status' => self::CONTAINER_STATUS,
			// New venues, organizers and containers are unlisted, not drafts: a draft
			// venue or organizer is hidden from the public event page (see Mapper).
			'create' => ['status' => 'unlisted'],
			'columns' => [
				'main' => [
					'width' => '2/3',
					'fields' => [
						'kind' => [
							'label' => I18n::all('field.kind'),
							'type' => 'select',
							'options' => self::options(['person', 'organization'], 'kind.'),
							'default' => 'organization',
							'width' => '1/2',
						],
						'website' => [
							'label' => I18n::all('field.website'),
							'type' => 'url',
							'width' => '1/2',
						],
						'email' => [
							'label' => I18n::all('field.email'),
							'type' => 'email',
							'width' => '1/2',
						],
						// Public only when on; Mapper passes the address to outputs only then.
						'showEmail' => [
							'label' => I18n::all('field.showEmail'),
							'type' => 'toggle',
							'default' => false,
							'width' => '1/2',
						],
					],
				],
				'sidebar' => [
					'width' => '1/3',
					'fields' => [
						'photo' => [
							'label' => I18n::all('field.photo'),
							'type' => 'files',
							'multiple' => false,
						],
					],
				],
			],
		]);
	}

	public static function organizers(array $settings): array
	{
		return [
			'title' => I18n::all('blueprint.organizers'),
			'icon' => 'users',
			'status' => self::CONTAINER_STATUS,
			// New venues, organizers and containers are unlisted, not drafts: a draft
			// venue or organizer is hidden from the public event page (see Mapper).
			'create' => ['status' => 'unlisted'],
			'sections' => [
				'organizers' => [
					'type' => 'pages',
					'label' => I18n::all('section.organizers'),
					'templates' => ['koyomi-organizer'],
					'sortBy' => 'title asc',
				],
			],
		];
	}

	/** @param list<string> $values */
	private static function plainOptions(array $values): array
	{
		return array_map(fn (string $value) => ['value' => $value, 'text' => $value], $values);
	}

	private static function setting(array $settings, string $key, string $fallback): string
	{
		return isset($settings[$key]) && is_string($settings[$key]) && $settings[$key] !== '' ? $settings[$key] : $fallback;
	}

	/**
	 * Marks every field named in SHARED_FIELDS with `translate: false`,
	 * wherever it sits (tabs, columns, sections). Names are unique across the
	 * event, venue and organizer blueprints, so one list serves all of them.
	 */
	private static function shared(array $blueprint): array
	{
		$walk = function (array $node) use (&$walk): array {
			foreach ($node as $key => $value) {
				if (!is_array($value)) {
					continue;
				}
				if ($key === 'fields') {
					foreach ($value as $name => $field) {
						if (is_string($name) && in_array($name, self::SHARED_FIELDS, true) && is_array($field)) {
							$value[$name] = $field + ['translate' => false];
						}
					}
				}
				$node[$key] = $walk($value);
			}
			return $node;
		};
		return $walk($blueprint);
	}

	public static function venue(array $settings): array
	{
		return self::shared([
			'title' => I18n::all('blueprint.venue'),
			'icon' => 'pin',
			'status' => self::CONTAINER_STATUS,
			// New venues, organizers and containers are unlisted, not drafts: a draft
			// venue or organizer is hidden from the public event page (see Mapper).
			'create' => ['status' => 'unlisted'],
			'columns' => [
				'main' => [
					'width' => '2/3',
					'fields' => [
						// An in-person event needs an address: a venue without one is incomplete.
						'street' => [
							'label' => I18n::all('field.street'),
							'type' => 'text',
							'width' => '2/3',
							'required' => true,
						],
						'postalCode' => [
							'label' => I18n::all('field.postalCode'),
							'type' => 'text',
							'width' => '1/3',
							'required' => true,
						],
						'city' => [
							'label' => I18n::all('field.city'),
							'type' => 'text',
							'width' => '1/2',
							'required' => true,
						],
						'region' => [
							'label' => I18n::all('field.region'),
							'type' => 'text',
							'width' => '1/2',
						],
						'country' => [
							'label' => I18n::all('field.country'),
							'type' => 'text',
							'maxlength' => 2,
							'width' => '1/3',
							'placeholder' => 'DE',
						],
						'lat' => [
							'label' => I18n::all('field.lat'),
							'type' => 'number',
							'step' => 0.000001,
							'min' => -90,
							'max' => 90,
							'width' => '1/3',
						],
						'lng' => [
							'label' => I18n::all('field.lng'),
							'type' => 'number',
							'step' => 0.000001,
							'min' => -180,
							'max' => 180,
							'width' => '1/3',
						],
						'website' => [
							'label' => I18n::all('field.website'),
							'type' => 'url',
						],
						'directions' => [
							'label' => I18n::all('field.directions'),
							'type' => 'textarea',
							'buttons' => false,
						],
					],
				],
				'sidebar' => [
					'width' => '1/3',
					'sections' => [
						'fields' => [
							'type' => 'fields',
							'fields' => [
								'mapImage' => [
									'label' => I18n::all('field.mapImage'),
									'type' => 'files',
									'multiple' => false,
									'help' => I18n::all('field.mapImage.help'),
								],
								'showMap' => [
									'label' => I18n::all('field.showMap'),
									'type' => 'toggle',
									'default' => false,
								],
							],
						],
						'files' => [
							'type' => 'files',
							'label' => I18n::all('section.files'),
						],
					],
				],
			],
		]);
	}

	public static function venues(array $settings): array
	{
		return [
			'title' => I18n::all('blueprint.venues'),
			'icon' => 'pin',
			'status' => self::CONTAINER_STATUS,
			// New venues, organizers and containers are unlisted, not drafts: a draft
			// venue or organizer is hidden from the public event page (see Mapper).
			'create' => ['status' => 'unlisted'],
			'sections' => [
				'venues' => [
					'type' => 'pages',
					'label' => I18n::all('section.venues'),
					'templates' => ['koyomi-venue'],
					'sortBy' => 'title asc',
				],
			],
		];
	}

	/** Zones as plain select options, the value doubles as text (zone names are not translated). */
	private static function zoneOptions(array $settings): array
	{
		return self::plainOptions($settings['timezones'] ?? [Time::DEFAULT_ZONE]);
	}
}

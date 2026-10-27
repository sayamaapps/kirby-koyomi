<?php

namespace Sayamaapps\Koyomi;

/**
 * A place, Kirby-free. Built by Mapper from a `koyomi-venue` page, or by
 * tests from a plain array. Read-only.
 *
 * Koyomi never embeds a map and never requests anything from a map service.
 * `mapImage` is a self-hosted static image the editor uploaded; `mapLink` is
 * a plain link (OpenStreetMap by default, `koyomi.mapUrl` to change) that
 * only opens on click.
 *
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-koyomi
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class Venue
{
	/**
	 * @param $id stable id (page UUID without scheme, else page id)
	 * @param $country ISO 3166-1 alpha-2, upper case ("DE"), or ""
	 * @param $url the venue page URL ("" if unknown)
	 * @param $mapImage absolute URL of the self-hosted map image, or ""
	 * @param $mapLink "" = computed by defaultMapLink()
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $name,
		public readonly string $street = '',
		public readonly string $postalCode = '',
		public readonly string $city = '',
		public readonly string $region = '',
		public readonly string $country = '',
		public readonly float|null $lat = null,
		public readonly float|null $lng = null,
		public readonly string $url = '',
		public readonly string $website = '',
		public readonly string $directions = '',
		public readonly string $mapImage = '',
		public readonly string $mapImageAlt = '',
		public readonly bool $showMap = false,
		public readonly string $mapLink = '',
	) {
	}

	/**
	 * One line, parts joined with ", " and empty parts left out:
	 * "Musterstraße 1, 12345 Musterstadt, DE". Postal code and city share one part.
	 */
	public function addressLine(): string
	{
		$cityPart = trim($this->postalCode . ' ' . $this->city);
		return implode(', ', array_values(array_filter([$this->street, $cityPart, $this->country], fn ($p) => $p !== '')));
	}

	/** Up to 6 decimals, trailing zeros trimmed, always "." — 47.85 → "47.85", 12.0 → "12". */
	public static function coordinate(float $value): string
	{
		$text = rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
		return $text === '-0' ? '0' : $text;
	}

	/**
	 * OpenStreetMap, plain link:
	 * - coordinates: https://www.openstreetmap.org/?mlat=48.123&mlon=11.456#map=17/48.123/11.456
	 * - address only: https://www.openstreetmap.org/search?query=<rawurlencode(addressLine())>
	 * - neither: ""
	 */
	public function defaultMapLink(): string
	{
		if ($this->lat !== null && $this->lng !== null) {
			$lat = self::coordinate($this->lat);
			$lng = self::coordinate($this->lng);
			return 'https://www.openstreetmap.org/?mlat=' . $lat . '&mlon=' . $lng . '#map=17/' . $lat . '/' . $lng;
		}
		if ($this->hasAddress()) {
			return 'https://www.openstreetmap.org/search?query=' . rawurlencode($this->addressLine());
		}
		return '';
	}

	/**
	 * Keys as in the constructor; values are normalized (strings trimmed,
	 * lat/lng range-checked, URLs must be http(s), country upper-cased and
	 * dropped unless two letters). Unknown keys are ignored.
	 */
	public static function fromArray(array $data): self
	{
		$country = strtoupper(Normalize::string($data['country'] ?? ''));
		$venue = new self(
			id: Normalize::string($data['id'] ?? ''),
			name: Normalize::string($data['name'] ?? ''),
			street: Normalize::string($data['street'] ?? ''),
			postalCode: Normalize::string($data['postalCode'] ?? ''),
			city: Normalize::string($data['city'] ?? ''),
			region: Normalize::string($data['region'] ?? ''),
			country: preg_match('/^[A-Z]{2}$/', $country) ? $country : '',
			lat: Normalize::coordinate($data['lat'] ?? null, -90, 90),
			lng: Normalize::coordinate($data['lng'] ?? null, -180, 180),
			url: Normalize::url($data['url'] ?? ''),
			website: Normalize::url($data['website'] ?? ''),
			directions: Normalize::string($data['directions'] ?? ''),
			mapImage: Normalize::url($data['mapImage'] ?? ''),
			mapImageAlt: Normalize::string($data['mapImageAlt'] ?? ''),
			showMap: Normalize::bool($data['showMap'] ?? false),
			mapLink: Normalize::url($data['mapLink'] ?? ''),
		);
		return $venue;
	}

	/** A street or a city is set. */
	public function hasAddress(): bool
	{
		return $this->street !== '' || $this->city !== '';
	}

	/** Latitude and longitude are both set. */
	public function hasCoordinates(): bool
	{
		return $this->lat !== null && $this->lng !== null;
	}

	/** Name plus address: "Seminarhaus Beispielhof, Musterstraße 1, 12345 Musterstadt, DE". */
	public function label(): string
	{
		return implode(', ', array_values(array_filter([$this->name, $this->addressLine()], fn ($p) => $p !== '')));
	}

	/** The configured link, else defaultMapLink(). */
	public function mapLink(): string
	{
		return $this->mapLink !== '' ? $this->mapLink : $this->defaultMapLink();
	}

	/**
	 * Public array (JSON output, $page->koyomiData()):
	 *   ['id' => 'venue-beispielhof', 'name' => 'Seminarhaus Beispielhof', 'street' => 'Musterstraße 1',
	 *    'postalCode' => '12345', 'city' => 'Musterstadt', 'region' => 'Musterland', 'country' => 'DE',
	 *    'address' => 'Musterstraße 1, 12345 Musterstadt, DE', 'lat' => 48.123, 'lng' => 11.456,
	 *    'url' => 'https://…/venues/beispielhof', 'website' => 'https://…', 'directions' => '…',
	 *    'mapImage' => 'https://…/map.jpg'|'' (only when showMap), 'mapImageAlt' => '…', 'mapLink' => 'https://www.openstreetmap.org/…']
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->id,
			'name' => $this->name,
			'street' => $this->street,
			'postalCode' => $this->postalCode,
			'city' => $this->city,
			'region' => $this->region,
			'country' => $this->country,
			'address' => $this->addressLine(),
			'lat' => $this->lat,
			'lng' => $this->lng,
			'url' => $this->url,
			'website' => $this->website,
			'directions' => $this->directions,
			'mapImage' => $this->showMap ? $this->mapImage : '',
			'mapImageAlt' => $this->showMap ? $this->mapImageAlt : '',
			'mapLink' => $this->mapLink(),
		];
	}
}

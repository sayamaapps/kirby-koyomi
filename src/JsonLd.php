<?php

namespace Sayamaapps\Koyomi;

/**
 * schema.org Event as JSON-LD, Kirby-free.
 *
 * Contract: SPEC.md §8.3 (full example array there). Rules in short:
 * - Only for the single event page (snippet koyomi/jsonld), never for lists.
 * - Keys with empty values are left out.
 * - VirtualLocation.url is the EVENT PAGE URL — never a meeting link (Event
 *   has none).
 */
final class JsonLd
{
	public const CONTEXT = 'https://schema.org';

	/** Prefix of every schema.org enumeration value. */
	private const VOCABULARY = 'https://schema.org/';

	/** "https://schema.org/OfflineEventAttendanceMode" | Online… | Mixed… for an Attendance value. */
	public static function attendanceMode(string $attendance): string
	{
		return self::VOCABULARY . match ($attendance) {
			Attendance::ONLINE => 'OnlineEventAttendanceMode',
			Attendance::MIXED => 'MixedEventAttendanceMode',
			default => 'OfflineEventAttendanceMode',
		};
	}

	/** "https://schema.org/InStock" | LimitedAvailability | SoldOut for an Availability value. */
	public static function availability(string $availability): string
	{
		return self::VOCABULARY . match ($availability) {
			Availability::LIMITED => 'LimitedAvailability',
			Availability::SOLDOUT => 'SoldOut',
			default => 'InStock',
		};
	}

	/**
	 * json_encode with JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE |
	 * JSON_HEX_TAG | JSON_HEX_AMP | JSON_PRESERVE_ZERO_FRACTION |
	 * JSON_THROW_ON_ERROR. JSON_HEX_TAG turns "<" ">" into < >, so
	 * "</script>" in a title can never close the script element.
	 */
	public static function encode(array $data): string
	{
		return json_encode(
			$data,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
			| JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
		);
	}

	/**
	 * @param array{language?: string} $options language for nothing visible yet; reserved
	 * @return array<string,mixed> plain array, see SPEC.md §8.3
	 */
	public static function event(Event $event, array $options = []): array
	{
		$allDay = $event->allDay;
		$previousStart = $event->previousStart;

		$data = [
			'@context' => self::CONTEXT,
			'@type' => 'Event',
			'name' => $event->title,
			'description' => $event->description,
			'url' => $event->url,
			'image' => $event->image === '' ? [] : [$event->image],
			// All-day: dates only, and the END is the inclusive last day (schema.org
			// has no exclusive end for dates; ICS keeps the exclusive form).
			// A timed event without an end in the content gets no endDate: the
			// default duration is not an end anybody entered.
			'startDate' => $allDay ? Time::date($event->start) : Time::iso($event->start),
			'endDate' => $allDay
				? Time::date($event->lastDay())
				: ($event->hasEndDate ? Time::iso($event->end) : null),
			'eventAttendanceMode' => self::attendanceMode($event->effectiveAttendance()),
			'eventStatus' => self::eventStatus($event->status),
			'previousStartDate' => $event->status === Status::RESCHEDULED && $previousStart !== null
				? ($allDay ? Time::date($previousStart) : Time::iso($previousStart))
				: null,
			'location' => self::location($event),
			'offers' => self::offers($event),
			'organizer' => self::organizers($event),
			'inLanguage' => self::languages($event->languages),
		];

		return self::keep($data);
	}

	/** "https://schema.org/EventScheduled" … per Status (see src/Status.php table). */
	public static function eventStatus(string $status): string
	{
		return self::VOCABULARY . match ($status) {
			Status::CANCELLED => 'EventCancelled',
			Status::POSTPONED => 'EventPostponed',
			Status::RESCHEDULED => 'EventRescheduled',
			Status::MOVED_ONLINE => 'EventMovedOnline',
			default => 'EventScheduled',
		};
	}

	/**
	 * Drops the keys whose value is null, an empty string or an empty array.
	 * 0 and 0.0 stay: a free event's price and a latitude on the equator are data.
	 *
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>
	 */
	private static function keep(array $data): array
	{
		return array_filter($data, fn ($value) => $value !== null && $value !== '' && $value !== []);
	}

	/** One language → string, several → list, none → null. */
	private static function languages(array $languages): string|array|null
	{
		if ($languages === []) {
			return null;
		}
		return count($languages) === 1 ? $languages[0] : array_values($languages);
	}

	/**
	 * The place(s) of the event: a Place for an in-person venue, a
	 * VirtualLocation for online attendance. One → the object itself, two
	 * (mixed) → a list, none → null (key omitted).
	 */
	private static function location(Event $event): array|null
	{
		$places = [];
		$venue = $event->physicalVenue();
		if ($venue !== null) {
			$places[] = self::place($venue);
		}
		if ($event->isOnline()) {
			// The event PAGE stands in for the meeting: the meeting link is never output.
			$places[] = self::keep(['@type' => 'VirtualLocation', 'url' => $event->url]);
		}
		if ($places === []) {
			return null;
		}
		return count($places) === 1 ? $places[0] : $places;
	}

	/**
	 * Only when tickets exist: free, a price, or a booking link. The price is
	 * 0.0 for a free event (kept by keep(), which only drops null, '' and []).
	 *
	 * @return array<string,mixed>|null
	 */
	private static function offers(Event $event): array|null
	{
		if (!$event->isFree && $event->price === null && $event->bookingUrl === '') {
			return null;
		}
		$offer = [
			'@type' => 'Offer',
			'url' => $event->bookingUrl !== '' ? $event->bookingUrl : $event->url,
		];
		if ($event->isFree) {
			$offer['price'] = 0.0;
			$offer['priceCurrency'] = $event->currency;
		} elseif ($event->price !== null) {
			$offer['price'] = $event->price;
			$offer['priceCurrency'] = $event->currency;
		}
		$offer['availability'] = self::availability($event->availability);
		return self::keep($offer);
	}

	/** One organizer → object, several → list, none → null. */
	private static function organizers(Event $event): array|null
	{
		$items = [];
		foreach ($event->organizers as $organizer) {
			$items[] = self::keep([
				'@type' => $organizer->kind === Organizer::PERSON ? 'Person' : 'Organization',
				'name' => $organizer->name,
				'url' => $organizer->link(),
				// Organizer::email is only ever the PUBLIC address (Mapper honours showEmail).
				'email' => $organizer->email,
			]);
		}
		if ($items === []) {
			return null;
		}
		return count($items) === 1 ? $items[0] : $items;
	}

	/** @return array<string,mixed> */
	private static function place(Venue $venue): array
	{
		$address = self::keep([
			'@type' => 'PostalAddress',
			'streetAddress' => $venue->street,
			'postalCode' => $venue->postalCode,
			'addressLocality' => $venue->city,
			'addressRegion' => $venue->region,
			'addressCountry' => $venue->country,
		]);
		// Coordinates may be 0.0 (equator), so only null counts as "absent".
		$geo = $venue->hasCoordinates()
			? ['@type' => 'GeoCoordinates', 'latitude' => $venue->lat, 'longitude' => $venue->lng]
			: null;

		return self::keep([
			'@type' => 'Place',
			'name' => $venue->name,
			'address' => count($address) > 1 ? $address : null,
			'geo' => $geo,
			'url' => $venue->website,
		]);
	}

	/** '<script type="application/ld+json">' . encode($data) . '</script>' */
	public static function script(array $data): string
	{
		return '<script type="application/ld+json">' . self::encode($data) . '</script>';
	}
}

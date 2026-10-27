<?php

/**
 * Shared test helpers (loaded by tests/bootstrap.php). Assertions are PHPUnit's own,
 * called as $this->assertSame(), $this->assertTrue() and so on.
 */

/** Loads every Kirby-free class of the plugin (no Kirby, no index.php). */
function koyomi_require_core(): void
{
	$src = dirname(__DIR__) . '/src/';
	foreach (['I18n', 'Normalize', 'Time', 'Status', 'Attendance', 'Availability', 'Venue', 'Organizer', 'Event',
		'Query', 'Expiry', 'Filters', 'Format', 'GoogleLink', 'VTimezone', 'Ics', 'JsonLd', 'Rss', 'Blueprints'] as $class) {
		require_once $src . $class . '.php';
	}
}

/**
 * A typical event as raw content (Event::fromArray() input). Override any key.
 * Defaults: timed, Europe/Berlin, 2027-03-12 18:00–20:00, offline, no venue.
 */
function koyomi_raw_event(array $override = []): array
{
	return $override + [
		'id' => 'evt-test',
		'title' => 'Test event',
		'url' => 'https://example.test/events/test-event',
		'startDate' => '2027-03-12',
		'startTime' => '18:00',
		'endDate' => '',
		'endTime' => '20:00',
		'allDay' => false,
		'timezone' => 'Europe/Berlin',
		'modified' => 1791000000,
	];
}

/** The venue used across Kirby-free tests. */
function koyomi_raw_venue(array $override = []): array
{
	return $override + [
		'id' => 'venue-beispielhof',
		'name' => 'Seminarhaus Beispielhof',
		'street' => 'Musterstraße 1',
		'postalCode' => '12345',
		'city' => 'Musterstadt',
		'region' => 'Musterland',
		'country' => 'DE',
		'lat' => '48.123',
		'lng' => '11.456',
		'url' => 'https://example.test/venues/beispielhof',
		'website' => 'https://beispielhof.example',
		'directions' => 'Ten minutes from the station.',
	];
}

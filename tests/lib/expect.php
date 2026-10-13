<?php
/**
 * Shared assertions for the standalone test scripts. Not a test itself
 * (tests/run.sh only runs tests/*Test.php).
 *
 *   require __DIR__ . '/lib/expect.php';
 *   koyomi_test('Koyomi Ics', function () {
 *       expect('a\\,b', Ics::escape('a,b'), 'comma escaped');
 *       holds(str_contains($ics, "\r\n"), 'CRLF line endings');
 *   });
 *
 * koyomi_test() prints "<name>: all N checks passed" and exits 0, or prints
 * the failing check to STDERR and exits 1. Exceptions inside the callback
 * (including "not implemented yet" stubs) count as failures.
 */

$GLOBALS['koyomiChecks'] = 0;

/** Strict equality (===); the message names the check, the actual value is appended on failure. */
function expect(mixed $expected, mixed $actual, string $what): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($what . "\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true));
    }
    $GLOBALS['koyomiChecks']++;
}

/** A condition that must be true. */
function holds(bool $ok, string $what): void
{
    if (!$ok) {
        throw new RuntimeException($what);
    }
    $GLOBALS['koyomiChecks']++;
}

/** The callable must throw an instance of $class. */
function throws(string $class, callable $fn, string $what): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $class) {
            $GLOBALS['koyomiChecks']++;
            return;
        }
        throw new RuntimeException($what . ': threw ' . get_class($e) . ' (' . $e->getMessage() . ') instead of ' . $class);
    }
    throw new RuntimeException($what . ': nothing thrown, expected ' . $class);
}

/** Runs the checks and exits with 0 (all passed) or 1. */
function koyomi_test(string $name, callable $checks, ?callable $finally = null): never
{
    try {
        $checks();
        echo $name . ': all ' . $GLOBALS['koyomiChecks'] . " checks passed\n";
        $code = 0;
    } catch (Throwable $e) {
        fwrite(STDERR, $name . ': ' . $e->getMessage() . "\n  @ " . basename($e->getFile()) . ':' . $e->getLine() . "\n");
        foreach (array_slice($e->getTrace(), 0, 4) as $frame) {
            if (isset($frame['file'])) {
                fwrite(STDERR, '    ' . basename($frame['file']) . ':' . ($frame['line'] ?? '?') . "\n");
            }
        }
        $code = 1;
    } finally {
        if ($finally !== null) {
            $finally();
        }
    }
    exit($code);
}

/** Loads every Kirby-free class of the plugin (no Kirby, no index.php). */
function koyomi_require_core(): void
{
    $src = dirname(__DIR__, 2) . '/src/';
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

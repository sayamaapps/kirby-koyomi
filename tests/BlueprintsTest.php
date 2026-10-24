<?php
/**
 * Panel blueprints (Koyomi\Blueprints). Two parts:
 *   1. Kirby-free: every field of SPEC §5.1–5.3 with its type, when, default,
 *      required flag, options from settings, labels, no status/image field.
 *   2. Kirby: the blueprints load through Kirby for one page per template,
 *      proving Kirby accepts the syntax; options follow the configuration.
 */
require __DIR__ . '/lib/expect.php';
koyomi_require_core();
require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/lib/Fixture.php';

use Kirby\Cms\Blueprint;
use Koyomi\Availability;
use Koyomi\Blueprints;
use Koyomi\I18n;
use Koyomi\Status;

$root = KoyomiFixture::create();

/**
 * Every field definition of a blueprint, keyed by field name. Walks the whole
 * array: a field is an entry of a `fields` map (not the list of names inside
 * `create`).
 */
function koyomi_blueprint_fields(array $node, array &$out = []): array
{
    foreach ($node as $key => $value) {
        if (!is_array($value)) {
            continue;
        }
        if ($key === 'fields' && array_keys($value) !== range(0, count($value) - 1)) {
            foreach ($value as $name => $field) {
                if (is_array($field)) {
                    $out[$name] = $field;
                }
            }
        }
        koyomi_blueprint_fields($value, $out);
    }
    return $out;
}

/**
 * The tab names of a Kirby blueprint. Blueprint::tabs() returns a list of
 * arrays in Kirby 5 and a Tabs collection of Tab objects in Kirby 6.
 */
function koyomi_tab_names(object $blueprint): array
{
    $names = [];
    foreach ($blueprint->tabs() as $tab) {
        $names[] = is_array($tab) ? $tab['name'] : $tab->name();
    }
    return $names;
}

/**
 * The section names of a Kirby blueprint. Kirby 5 keeps sections apart
 * (Blueprint::sections()); Kirby 6 folds every section into a field of the
 * same name and no longer has the method.
 */
function koyomi_section_names(object $blueprint): array
{
    return array_keys(method_exists($blueprint, 'sections') ? $blueprint->sections() : $blueprint->fields());
}

/** Every key used as a field name, at any depth (for the status/image rule). */
function koyomi_field_names(array $node): array
{
    return array_keys(koyomi_blueprint_fields($node));
}

/** The label of a field or section is ['en' => …, 'de' => …] with both languages filled. */
function koyomi_assert_labels(mixed $label, string $what): void
{
    holds(is_array($label) && isset($label['en'], $label['de']) && $label['en'] !== '' && $label['de'] !== '', "$what: labels in en and de");
}

$settings = [
    'timezone' => 'Europe/Berlin',
    'timezones' => ['Europe/Berlin', 'Europe/Prague', 'UTC'],
    'currency' => 'EUR',
    'currencies' => ['EUR', 'CZK', 'CHF', 'USD'],
    'languages' => ['de' => '', 'en' => '', 'cs' => 'Čeština', 'pt-br' => ''],
];

koyomi_test('Koyomi Blueprints', function () use ($settings, $root) {
    // --- 1. Kirby-free ---------------------------------------------------

    expect(['koyomi-calendar', 'koyomi-event', 'koyomi-venues', 'koyomi-venue', 'koyomi-organizers', 'koyomi-organizer'], Blueprints::NAMES, 'NAMES lists the six blueprints');
    foreach (['calendar', 'event', 'venues', 'venue', 'organizers', 'organizer'] as $method) {
        holds(is_array(Blueprints::$method($settings)), "$method() returns an array");
        holds(isset(Blueprints::$method($settings)['title']), "$method() has a title");
    }

    // Multi-language (review): every non-text field is shared by all languages, the text fields stay translatable.
    $eventFields = koyomi_blueprint_fields(Blueprints::event($settings));
    $shared = ['cover', 'startDate', 'startTime', 'endDate', 'endTime', 'allDay', 'timezone', 'inPerson', 'online', 'venue',
        'onlinePlatform', 'onlineUrl', 'eventStatus', 'previousStart', 'isFree', 'price', 'currency', 'availability', 'bookingUrl',
        'organizers', 'eventLanguages', 'featured', 'series', 'seriesPart'];
    foreach ($shared as $name) {
        expect(false, $eventFields[$name]['translate'] ?? null, "event $name: translate false");
    }
    foreach (['excerpt', 'text', 'statusNote', 'priceNote', 'bookingLabel', 'categories'] as $name) {
        holds(!array_key_exists('translate', $eventFields[$name] ?? []), "event $name: stays translatable");
    }
    $venueFields = koyomi_blueprint_fields(Blueprints::venue($settings));
    foreach (['street', 'postalCode', 'city', 'region', 'country', 'lat', 'lng', 'mapImage', 'showMap', 'website'] as $name) {
        expect(false, $venueFields[$name]['translate'] ?? null, "venue $name: translate false");
    }
    holds(!array_key_exists('translate', $venueFields['directions'] ?? []), 'venue directions stays translatable');
    foreach (['street', 'postalCode', 'city'] as $name) {
        holds(($venueFields[$name]['required'] ?? false) === true, "venue $name is required");
    }
    holds(!isset($venueFields['region']['required']) && !isset($venueFields['country']['required']), 'venue region and country stay optional');
    $organizerFields = koyomi_blueprint_fields(Blueprints::organizer($settings));
    foreach (['kind', 'email', 'showEmail', 'website', 'photo'] as $name) {
        expect(false, $organizerFields[$name]['translate'] ?? null, "organizer $name: translate false");
    }
    // New venues, organizers and containers are unlisted, not drafts (review, finding 9).
    expect('unlisted', Blueprints::venue($settings)['create']['status'] ?? null, 'venue: created unlisted');
    expect('unlisted', Blueprints::organizer($settings)['create']['status'] ?? null, 'organizer: created unlisted');
    expect('unlisted', Blueprints::venues($settings)['create']['status'] ?? null, 'venues container: created unlisted');
    expect('unlisted', Blueprints::organizers($settings)['create']['status'] ?? null, 'organizers container: created unlisted');

    // options() helper
    expect(
        [['value' => 'scheduled', 'text' => I18n::all('status.scheduled')], ['value' => 'cancelled', 'text' => I18n::all('status.cancelled')]],
        Blueprints::options(['scheduled', 'cancelled'], 'status.'),
        'options(): value + translated text per entry'
    );
    expect(
        array_map(fn ($v) => ['value' => $v, 'text' => I18n::all('availability.' . $v)], Availability::ALL),
        Blueprints::options(Availability::ALL, 'availability.'),
        'options(): availability keys'
    );
    expect(
        [['value' => 'person', 'text' => I18n::all('kind.person')], ['value' => 'organization', 'text' => I18n::all('kind.organization')]],
        Blueprints::options(['person', 'organization'], 'kind.'),
        'options(): kind keys'
    );

    // --- event (SPEC §5.1) ----------------------------------------------

    $event = Blueprints::event($settings);
    expect('{{ page.startDate.toDate("Ymd") }}', $event['num'], 'event num from the start date');
    expect(['fields' => ['startDate']], $event['create'], 'event create asks for the start date');
    expect(['changeTemplate' => false], $event['options'], 'event options');
    expect(['event', 'when', 'status', 'tickets', 'details'], array_keys($event['tabs']), 'event tabs in order');
    expect(['en' => 'Event', 'de' => 'Veranstaltung'], $event['title'], 'event title label');
    expect(I18n::all('tab.when'), $event['tabs']['when']['label'], 'when tab label');

    $fields = koyomi_blueprint_fields($event);
    // Field name => [type, extra checks]. Every field of §5.1 must be present.
    $expected = [
        'excerpt' => 'textarea', 'text' => 'blocks', 'cover' => 'files',
        'startDate' => 'date', 'startTime' => 'time', 'endDate' => 'date', 'endTime' => 'time',
        'allDay' => 'toggle', 'timezone' => 'select', 'inPerson' => 'toggle', 'online' => 'toggle',
        'venue' => 'pages', 'onlinePlatform' => 'text', 'onlineUrl' => 'url',
        'eventStatus' => 'select', 'previousStart' => 'date', 'statusNote' => 'textarea',
        'isFree' => 'toggle', 'price' => 'number', 'currency' => 'select', 'priceNote' => 'text',
        'availability' => 'select', 'bookingUrl' => 'url', 'bookingLabel' => 'text',
        'organizers' => 'pages', 'categories' => 'tags', 'eventLanguages' => 'multiselect',
        'featured' => 'toggle', 'series' => 'pages', 'seriesPart' => 'number',
    ];
    foreach ($expected as $name => $type) {
        holds(isset($fields[$name]), "event has field $name");
        expect($type, $fields[$name]['type'] ?? null, "event field $name type");
        koyomi_assert_labels($fields[$name]['label'] ?? null, "event field $name");
    }

    // when conditions (equality only, SPEC §0)
    expect(['allDay' => false], $fields['startTime']['when'], 'startTime when allDay false');
    expect(['allDay' => false], $fields['endTime']['when'], 'endTime when allDay false');
    expect(['inPerson' => true], $fields['venue']['when'], 'venue when inPerson true');
    expect(['online' => true], $fields['onlinePlatform']['when'], 'onlinePlatform when online true');
    expect(['online' => true], $fields['onlineUrl']['when'], 'onlineUrl when online true');
    expect(['eventStatus' => 'rescheduled'], $fields['previousStart']['when'], 'previousStart when rescheduled');
    expect(['isFree' => false], $fields['price']['when'], 'price when not free');
    expect(['isFree' => false], $fields['currency']['when'], 'currency when not free');
    holds(!isset($fields['startDate']['when']) && !isset($fields['endDate']['when']), 'dates have no when');

    // required and defaults
    foreach (['startDate', 'startTime', 'timezone', 'venue', 'onlineUrl', 'eventStatus'] as $name) {
        holds(($fields[$name]['required'] ?? false) === true, "$name is required");
    }
    // For the package Fude: asked before a new event, never required.
    foreach (['endTime', 'price', 'bookingUrl'] as $name) {
        holds(($fields[$name]['fudeAsk'] ?? false) === true && !isset($fields[$name]['required']), "$name is asked by Fude");
    }
    expect(false, $fields['allDay']['default'], 'allDay default false');
    expect(true, $fields['inPerson']['default'], 'inPerson default true');
    expect(false, $fields['online']['default'], 'online default false');
    expect('Europe/Berlin', $fields['timezone']['default'], 'timezone default from settings');
    expect(Status::SCHEDULED, $fields['eventStatus']['default'], 'eventStatus default scheduled');
    expect(Availability::AVAILABLE, $fields['availability']['default'], 'availability default available');
    expect('EUR', $fields['currency']['default'], 'currency default from settings');
    expect(false, $fields['isFree']['default'], 'isFree default false');
    expect(I18n::all('status.scheduled'), $fields['eventStatus']['options'][0]['text'], 'status options use translated text');
    expect(Status::ALL, array_column($fields['eventStatus']['options'], 'value'), 'eventStatus options are the Status enum');
    expect(Availability::ALL, array_column($fields['availability']['options'], 'value'), 'availability options are the Availability enum');

    // widths and attributes that the Panel layout relies on
    expect('1/2', $fields['startDate']['width'], 'startDate width');
    expect('1/3', $fields['price']['width'], 'price width');
    expect(0, $fields['price']['min'], 'price min 0');
    expect(0.01, $fields['price']['step'], 'price step 0.01');
    expect(1, $fields['seriesPart']['min'], 'seriesPart min 1');
    expect(1, $fields['seriesPart']['step'], 'seriesPart step 1');
    expect(500, $fields['excerpt']['maxlength'], 'excerpt maxlength 500');
    expect(false, $fields['excerpt']['buttons'], 'excerpt without buttons');
    expect('page.images', $fields['cover']['query'], 'cover query page.images');
    expect(false, $fields['cover']['multiple'], 'cover single file');
    expect('kirby.collection("koyomi/venues")', $fields['venue']['query'], 'venue collection query');
    expect(false, $fields['venue']['multiple'], 'venue single page');
    expect('kirby.collection("koyomi/organizers")', $fields['organizers']['query'], 'organizers collection query');
    expect('page.siblings(false)', $fields['series']['query'], 'series siblings query');
    expect(true, $fields['previousStart']['time'], 'previousStart with time');
    expect(false, $fields['statusNote']['buttons'], 'statusNote without buttons');

    // Timezone and currency options from settings (plain value as text)
    expect(
        [['value' => 'Europe/Berlin', 'text' => 'Europe/Berlin'], ['value' => 'Europe/Prague', 'text' => 'Europe/Prague'], ['value' => 'UTC', 'text' => 'UTC']],
        $fields['timezone']['options'],
        'timezone options come from settings.timezones'
    );
    expect(
        [['value' => 'EUR', 'text' => 'EUR'], ['value' => 'CZK', 'text' => 'CZK'], ['value' => 'CHF', 'text' => 'CHF'], ['value' => 'USD', 'text' => 'USD']],
        $fields['currency']['options'],
        'currency options come from settings.currencies incl. USD'
    );

    // Languages: custom label, translated key, unknown code upper-cased
    $languages = $fields['eventLanguages']['options'];
    expect(['de', 'en', 'cs', 'pt-br'], array_column($languages, 'value'), 'language values in settings order');
    expect(I18n::all('language.de'), $languages[0]['text'], 'language without label uses translation language.de');
    expect(I18n::all('language.en'), $languages[1]['text'], 'language en uses translation');
    expect('Čeština', $languages[2]['text'], 'custom label wins');
    expect('PT-BR', $languages[3]['text'], 'unknown code is upper-cased');

    // A plain list of languages works too (no labels)
    $plain = Blueprints::event(array_replace($settings, ['languages' => ['cs', 'xx']]));
    expect(
        [['value' => 'cs', 'text' => I18n::all('language.cs')], ['value' => 'xx', 'text' => 'XX']],
        koyomi_blueprint_fields($plain)['eventLanguages']['options'],
        'plain language list: unknown code upper-cased, known one translated'
    );

    // Defaults when settings are missing (hand-made or partial arrays)
    $bare = Blueprints::event([]);
    expect('Europe/Berlin', koyomi_blueprint_fields($bare)['timezone']['default'], 'timezone default without settings');
    expect('EUR', koyomi_blueprint_fields($bare)['currency']['default'], 'currency default without settings');

    // Calendar: drafts + events sections, intro in the sidebar
    $calendar = Blueprints::calendar($settings);
    $calendarFields = koyomi_blueprint_fields($calendar);
    expect('textarea', $calendarFields['intro']['type'], 'calendar intro is a textarea');
    koyomi_assert_labels($calendarFields['intro']['label'], 'calendar intro');
    expect(['draft', 'published'], [$calendar['columns']['main']['sections']['drafts']['status'], $calendar['columns']['main']['sections']['events']['status']], 'calendar sections by status');
    expect(['koyomi-event'], $calendar['columns']['main']['sections']['events']['templates'], 'calendar lists events only');
    expect(50, $calendar['columns']['main']['sections']['events']['limit'], 'calendar events limit 50');
    expect('startDate desc', $calendar['columns']['main']['sections']['events']['sortBy'], 'calendar sorts by start date');

    // Containers and children: draft + unlisted only
    $statusDraftUnlisted = ['draft' => true, 'unlisted' => true, 'listed' => false];
    $venues = Blueprints::venues($settings);
    expect($statusDraftUnlisted, $venues['status'], 'venues container status');
    expect(['koyomi-venue'], $venues['sections']['venues']['templates'], 'venues section lists venues');
    expect('title asc', $venues['sections']['venues']['sortBy'], 'venues sorted by title');

    $venue = Blueprints::venue($settings);
    expect($statusDraftUnlisted, $venue['status'], 'venue status');
    $venueFields = koyomi_blueprint_fields($venue);
    $venueExpected = [
        'street' => 'text', 'postalCode' => 'text', 'city' => 'text', 'region' => 'text', 'country' => 'text',
        'lat' => 'number', 'lng' => 'number', 'mapImage' => 'files', 'showMap' => 'toggle',
        'website' => 'url', 'directions' => 'textarea',
    ];
    foreach ($venueExpected as $name => $type) {
        holds(isset($venueFields[$name]), "venue has field $name");
        expect($type, $venueFields[$name]['type'] ?? null, "venue field $name type");
        koyomi_assert_labels($venueFields[$name]['label'] ?? null, "venue field $name");
    }
    expect(2, $venueFields['country']['maxlength'], 'country maxlength 2');
    expect('DE', $venueFields['country']['placeholder'], 'country placeholder DE');
    expect(0.000001, $venueFields['lat']['step'], 'lat step');
    expect([-90, 90], [$venueFields['lat']['min'], $venueFields['lat']['max']], 'lat range');
    expect([-180, 180], [$venueFields['lng']['min'], $venueFields['lng']['max']], 'lng range');
    expect(false, $venueFields['showMap']['default'], 'showMap default false');
    expect(false, $venueFields['mapImage']['multiple'], 'mapImage single file');
    holds(isset($venue['columns']['sidebar']['sections']['files']), 'venue has a files section');

    $organizers = Blueprints::organizers($settings);
    expect($statusDraftUnlisted, $organizers['status'], 'organizers container status');
    expect(['koyomi-organizer'], $organizers['sections']['organizers']['templates'], 'organizers section lists organizers');

    $organizer = Blueprints::organizer($settings);
    expect($statusDraftUnlisted, $organizer['status'], 'organizer status');
    $organizerFields = koyomi_blueprint_fields($organizer);
    $organizerExpected = ['kind' => 'select', 'email' => 'email', 'showEmail' => 'toggle', 'website' => 'url', 'photo' => 'files'];
    foreach ($organizerExpected as $name => $type) {
        holds(isset($organizerFields[$name]), "organizer has field $name");
        expect($type, $organizerFields[$name]['type'] ?? null, "organizer field $name type");
        koyomi_assert_labels($organizerFields[$name]['label'] ?? null, "organizer field $name");
    }
    expect('organization', $organizerFields['kind']['default'], 'kind default organization');
    expect(['person', 'organization'], array_column($organizerFields['kind']['options'], 'value'), 'kind options');
    expect(false, $organizerFields['showEmail']['default'], 'showEmail default false');
    expect(false, $organizerFields['photo']['multiple'], 'photo single file');

    // No field named status or image anywhere (they are Kirby Page methods).
    foreach (['calendar', 'event', 'venues', 'venue', 'organizers', 'organizer'] as $method) {
        $names = koyomi_field_names(Blueprints::$method($settings));
        holds(!in_array('status', $names, true) && !in_array('image', $names, true), "$method: no status/image field");
    }

    // --- 2. Kirby: the blueprints load, the Panel accepts the syntax -----

    $kirby = KoyomiFixture::app($root);
    $expectedBlueprints = [
        'events' => 'pages/koyomi-calendar',
        'events/retreat' => 'pages/koyomi-event',
        'venues' => 'pages/koyomi-venues',
        'venues/beispielhof' => 'pages/koyomi-venue',
        'organizers' => 'pages/koyomi-organizers',
        'organizers/anna' => 'pages/koyomi-organizer',
    ];
    foreach ($expectedBlueprints as $path => $name) {
        $page = $kirby->page($path);
        holds($page !== null, "fixture page $path exists");
        expect($name, $page->blueprint()->name(), "$path blueprint name");
        // These must not throw: the Panel reads them.
        holds(is_array($page->blueprint()->fields()), "$path fields() works");
        holds(koyomi_tab_names($page->blueprint()) !== [], "$path tabs() works");
        holds(koyomi_section_names($page->blueprint()) !== [], "$path sections() works");
    }

    // Field names as Kirby sees them (lower-cased by the Form layer): the event's fields are all there.
    $kirbyEvent = $kirby->page('events/retreat')->blueprint();
    $kirbyFieldNames = array_map('strtolower', array_keys($kirbyEvent->fields()));
    foreach (['startdate', 'venue', 'eventstatus', 'eventlanguages', 'isfree', 'bookingurl'] as $name) {
        holds(in_array($name, $kirbyFieldNames, true), "Kirby sees event field $name");
    }

    // Options follow the configuration: USD and the Prague zone show up in the registered blueprint.
    $kirby = KoyomiFixture::app($root, ['koyomi.currencies' => ['EUR', 'USD'], 'koyomi.timezone' => 'Europe/Prague']);
    $blueprint = Blueprint::find('pages/koyomi-event');
    holds(is_array($blueprint['tabs'] ?? null), 'registered event blueprint has tabs');
    $encoded = json_encode($blueprint);
    holds(str_contains($encoded, '"USD"') && str_contains($encoded, 'Europe\/Prague'), 'registered blueprint reflects currencies and timezone');
    holds(!str_contains($encoded, '"CZK"'), 'currencies follow the option (no CZK when not configured)');
    expect('pages/koyomi-event', $kirby->page('events/retreat')->blueprint()->name(), 'page blueprint instantiates after config change');

    foreach (['koyomi-calendar', 'koyomi-venues', 'koyomi-venue', 'koyomi-organizers', 'koyomi-organizer'] as $name) {
        holds(is_array(Blueprint::find('pages/' . $name)), "registered blueprint $name");
    }
}, fn () => KoyomiFixture::remove($root));

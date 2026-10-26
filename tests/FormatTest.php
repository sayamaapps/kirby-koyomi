<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Koyomi\Event;
use Koyomi\Format;
use Koyomi\I18n;

/** An event from raw content with the given overrides (Europe/Berlin, 2027-03-12 18:00–20:00 by default). */
function format_test_event(array $override = []): Event
{
    return Event::fromArray(koyomi_raw_event($override));
}

/** Checks the same event in English and German. */
function format_test_both(array $override, string $en, string $de, string $what): void
{
    I18n::setLanguage('en');
    expect($en, Format::when(format_test_event($override)), "$what (en)");
    I18n::setLanguage('de');
    expect($de, Format::when(format_test_event($override)), "$what (de)");
    I18n::setLanguage('en');
}

/**
 * Format: the when() table of SPEC §8.5 in English and German, date ranges,
 * money, prices, status, attendance, availability, series, languages, labels,
 * the configurable patterns (configure/reset) and the apply() tokens.
 * Kirby-free.
 */
#[CoversClass(Format::class)]
final class FormatTest extends TestCase
{
	public function testSuite(): void
	{
		koyomi_require_core();

		try {

		    Format::reset();
		    $berlin = new DateTimeZone('Europe/Berlin');

		    // when(): every row of the SPEC §8.5 table.
		    format_test_both([], 'Friday, 12 March 2027, 18:00–20:00', 'Freitag, 12. März 2027, 18:00–20:00 Uhr', 'timed, one day, with end');
		    format_test_both(['endTime' => ''], 'Friday, 12 March 2027, 18:00', 'Freitag, 12. März 2027, 18:00 Uhr', 'timed, no end time');
		    format_test_both(['startTime' => '23:00', 'endTime' => ''], 'Friday, 12 March 2027, 23:00', 'Freitag, 12. März 2027, 23:00 Uhr', 'timed, late start, no end: the default duration does not make it two days');
		    format_test_both(
		        ['endDate' => '2027-03-14', 'endTime' => '13:00'],
		        'Friday, 12 March 2027, 18:00 – Sunday, 14 March 2027, 13:00',
		        'Freitag, 12. März 2027, 18:00 Uhr – Sonntag, 14. März 2027, 13:00 Uhr',
		        'timed, multi-day with end time'
		    );
		    format_test_both(
		        ['endDate' => '2027-03-14', 'endTime' => ''],
		        '12–14 March 2027, 18:00',
		        '12.–14. März 2027, 18:00 Uhr',
		        'timed, multi-day without end time'
		    );
		    format_test_both(
		        ['startDate' => '2027-03-25', 'startTime' => '', 'allDay' => true],
		        'Thursday, 25 March 2027',
		        'Donnerstag, 25. März 2027',
		        'all-day, one day'
		    );
		    format_test_both(
		        ['startDate' => '2027-03-08', 'endDate' => '2027-03-11', 'startTime' => '', 'allDay' => true],
		        '8–11 March 2027',
		        '8.–11. März 2027',
		        'all-day, same month'
		    );
		    format_test_both(
		        ['startDate' => '2027-03-28', 'endDate' => '2027-04-02', 'startTime' => '', 'allDay' => true],
		        '28 March – 2 April 2027',
		        '28. März – 2. April 2027',
		        'all-day, same year'
		    );
		    format_test_both(
		        ['startDate' => '2027-12-30', 'endDate' => '2028-01-02', 'startTime' => '', 'allDay' => true],
		        '30 December 2027 – 2 January 2028',
		        '30. Dezember 2027 – 2. Januar 2028',
		        'all-day, across years'
		    );
		    format_test_both(
		        ['startDate' => '2027-03-20', 'startTime' => '20:00', 'endTime' => '', 'timezone' => 'Europe/Prague'],
		        'Saturday, 20 March 2027, 20:00 (Europe/Prague)',
		        'Samstag, 20. März 2027, 20:00 Uhr (Europe/Prague)',
		        'timed, zone hint for a foreign zone'
		    );
		    format_test_both(
		        ['timezone' => 'America/New_York', 'startDate' => '2027-03-20', 'startTime' => '20:00', 'endTime' => ''],
		        'Saturday, 20 March 2027, 20:00 (America/New York)',
		        'Samstag, 20. März 2027, 20:00 Uhr (America/New York)',
		        'underscore in the zone name becomes a space'
		    );

		    // Zone hint is compared with the configured site zone, not with Berlin by accident.
		    Format::configure([], 'Europe/Prague');
		    I18n::setLanguage('en');
		    expect('Friday, 12 March 2027, 18:00–20:00 (Europe/Berlin)', Format::when(format_test_event()), 'site zone Prague: Berlin event gets the hint');
		    holds(Format::siteZone() === 'Europe/Prague', 'siteZone() reports the configured zone');
		    Format::reset();
		    expect('Europe/Berlin', Format::siteZone(), 'reset() restores the site zone');
		    expect('Friday, 12 March 2027, 18:00–20:00', Format::when(format_test_event()), 'reset(): no hint for the site zone');

		    // dateRange(): also for timed events, from first to last day.
		    I18n::setLanguage('en');
		    expect('Friday, 12 March 2027', Format::dateRange(format_test_event()), 'dateRange: one day (timed)');
		    expect('12–14 March 2027', Format::dateRange(format_test_event(['endDate' => '2027-03-14', 'endTime' => '13:00'])), 'dateRange: timed multi-day');
		    I18n::setLanguage('de');
		    expect('12.–14. März 2027', Format::dateRange(format_test_event(['endDate' => '2027-03-14', 'endTime' => '13:00'])), 'dateRange: timed multi-day (de)');
		    I18n::setLanguage('en');

		    // date() / time() / apply(): the defaults.
		    $noon = new DateTimeImmutable('2027-03-12 18:00', $berlin);
		    expect('Friday, 12 March 2027', Format::date($noon), 'date() en');
		    expect('18:00', Format::time($noon), 'time()');
		    I18n::setLanguage('de');
		    expect('Freitag, 12. März 2027', Format::date($noon), 'date() de');
		    I18n::setLanguage('en');

		    // money(): the table of §8.5.
		    expect('€380', Format::money(380.0, 'EUR'), 'money en 380 EUR');
		    expect('€1,200.50', Format::money(1200.5, 'EUR'), 'money en 1200.5 EUR');
		    expect('CZK 450', Format::money(450.0, 'CZK'), 'money en 450 CZK');
		    expect('CHF 120', Format::money(120.0, 'CHF'), 'money en 120 CHF');
		    expect('USD 10', Format::money(10.0, 'USD'), 'money en 10 USD');
		    I18n::setLanguage('de');
		    expect('380 €', Format::money(380.0, 'EUR'), 'money de 380 EUR');
		    expect('1.200,50 €', Format::money(1200.5, 'EUR'), 'money de 1200.5 EUR');
		    expect('450 Kč', Format::money(450.0, 'CZK'), 'money de 450 CZK');
		    expect('120 CHF', Format::money(120.0, 'CHF'), 'money de 120 CHF');
		    expect('10 USD', Format::money(10.0, 'USD'), 'money de 10 USD');
		    I18n::setLanguage('en');

		    // price(): free, no price, amount; the priceNote is not part of it.
		    expect('Free', Format::price(format_test_event(['isFree' => true])), 'price: free en');
		    I18n::setLanguage('de');
		    expect('Kostenlos', Format::price(format_test_event(['isFree' => true])), 'price: free de');
		    I18n::setLanguage('en');
		    expect('', Format::price(format_test_event()), 'price: none');
		    expect('€380', Format::price(format_test_event(['price' => '380', 'priceNote' => 'Early bird until 1 Feb'])), 'price: amount, no note');
		    expect('CZK 450', Format::price(format_test_event(['price' => '450', 'currency' => 'CZK'])), 'price: CZK');

		    // status(): '' for scheduled, a label otherwise.
		    expect('', Format::status(format_test_event()), 'status: scheduled is empty');
		    expect('Cancelled', Format::status(format_test_event(['status' => 'cancelled'])), 'status: cancelled en');
		    expect('Postponed', Format::status(format_test_event(['status' => 'postponed'])), 'status: postponed en');
		    expect('Rescheduled', Format::status(format_test_event(['status' => 'rescheduled'])), 'status: rescheduled en');
		    expect('Moved online', Format::status(format_test_event(['status' => 'movedOnline'])), 'status: movedOnline en');
		    I18n::setLanguage('de');
		    expect('Abgesagt', Format::status(format_test_event(['status' => 'cancelled'])), 'status: cancelled de');
		    expect('Verschoben', Format::status(format_test_event(['status' => 'postponed'])), 'status: postponed de');
		    expect('Neuer Termin', Format::status(format_test_event(['status' => 'rescheduled'])), 'status: rescheduled de');
		    expect('Jetzt online', Format::status(format_test_event(['status' => 'movedOnline'])), 'status: movedOnline de');
		    I18n::setLanguage('en');

		    // previousStart(): only for rescheduled events with a previous start.
		    $rescheduled = format_test_event(['status' => 'rescheduled', 'previousStart' => '2027-02-20 20:00:00']);
		    expect('Originally planned for Saturday, 20 February 2027', Format::previousStart($rescheduled), 'previousStart en');
		    I18n::setLanguage('de');
		    expect('Ursprünglich geplant am Samstag, 20. Februar 2027', Format::previousStart($rescheduled), 'previousStart de');
		    I18n::setLanguage('en');
		    expect('', Format::previousStart(format_test_event()), 'previousStart: none');
		    expect('', Format::previousStart(format_test_event(['previousStart' => '2027-02-20 20:00:00'])), 'previousStart ignored unless rescheduled');

		    // attendance(): effective attendance, platform when given.
		    expect('In person', Format::attendance(format_test_event()), 'attendance: offline en');
		    expect('Online', Format::attendance(format_test_event(['attendance' => 'online'])), 'attendance: online en');
		    expect('Online via Zoom', Format::attendance(format_test_event(['attendance' => 'online', 'onlinePlatform' => 'Zoom'])), 'attendance: online via en');
		    expect('Hybrid', Format::attendance(format_test_event(['attendance' => 'mixed'])), 'attendance: mixed en');
		    expect(
		        'Hybrid: in person and online via Zoom',
		        Format::attendance(format_test_event(['attendance' => 'mixed', 'onlinePlatform' => 'Zoom'])),
		        'attendance: mixed via en'
		    );
		    expect(
		        'Online via Zoom',
		        Format::attendance(format_test_event(['attendance' => 'mixed', 'onlinePlatform' => 'Zoom', 'status' => 'movedOnline'])),
		        'attendance: moved online counts as online'
		    );
		    I18n::setLanguage('de');
		    expect('Vor Ort', Format::attendance(format_test_event()), 'attendance: offline de');
		    expect('Online über Zoom', Format::attendance(format_test_event(['attendance' => 'online', 'onlinePlatform' => 'Zoom'])), 'attendance: online via de');
		    expect(
		        'Hybrid: vor Ort und online über Zoom',
		        Format::attendance(format_test_event(['attendance' => 'mixed', 'onlinePlatform' => 'Zoom'])),
		        'attendance: mixed via de'
		    );
		    I18n::setLanguage('en');

		    // availability(): '' for available.
		    expect('', Format::availability(format_test_event()), 'availability: available is empty');
		    expect('Only a few places left', Format::availability(format_test_event(['availability' => 'limited'])), 'availability: limited en');
		    expect('Sold out', Format::availability(format_test_event(['availability' => 'soldout'])), 'availability: soldout en');
		    I18n::setLanguage('de');
		    expect('Nur noch wenige Plätze', Format::availability(format_test_event(['availability' => 'limited'])), 'availability: limited de');
		    expect('Ausgebucht', Format::availability(format_test_event(['availability' => 'soldout'])), 'availability: soldout de');
		    I18n::setLanguage('en');

		    // series(): part of total, '' without a series.
		    $part = [
		        'id' => 'evt-series-1', 'seriesPart' => '2',
		        'series' => [
		            ['id' => 'evt-series-0', 'title' => 'Part one', 'startDate' => '2027-03-05', 'startTime' => '10:00'],
		            ['id' => 'evt-series-2', 'title' => 'Part three', 'startDate' => '2027-03-19', 'startTime' => '10:00'],
		        ],
		    ];
		    expect('', Format::series(format_test_event()), 'series: none');
		    expect('Part 2 of 3', Format::series(format_test_event($part)), 'series: en');
		    I18n::setLanguage('de');
		    expect('Teil 2 von 3', Format::series(format_test_event($part)), 'series: de');
		    I18n::setLanguage('en');

		    // languageName() and languages().
		    expect('Czech', Format::languageName('cs'), 'languageName cs en');
		    expect('Tschechisch', (function () {
		        I18n::setLanguage('de');
		        $name = Format::languageName('cs');
		        I18n::setLanguage('en');
		        return $name;
		    })(), 'languageName cs de');
		    expect('Čeština', Format::languageName('cs', 'Čeština'), 'languageName: custom label wins');
		    expect('PT-BR', Format::languageName('pt-br'), 'languageName: unknown code upper-cased');

		    // countryName() and addressLines().
		    expect('Czechia', Format::countryName('CZ'), 'countryName CZ en');
		    expect('Czechia', Format::countryName('cz'), 'countryName: lower case accepted');
		    expect('MT', Format::countryName('MT'), 'countryName: unknown code as is');
		    $prague = \Koyomi\Venue::fromArray(['name' => 'Stadthalle', 'street' => 'Musterstraße 1', 'postalCode' => '12345', 'city' => 'Musterstadt', 'country' => 'CZ']);
		    expect(['Musterstraße 1', '12345 Musterstadt', 'Czechia'], Format::addressLines($prague), 'addressLines: country by name');
		    expect(['Musterstraße 1', '12345 Musterstadt', 'Czechia'], Format::addressLines($prague, 'DE'), 'addressLines: other country kept');
		    expect(['Musterstraße 1', '12345 Musterstadt'], Format::addressLines($prague, 'cz'), 'addressLines: home country left out');
		    expect([], Format::addressLines(\Koyomi\Venue::fromArray(['name' => 'Online'])), 'addressLines: empty venue');
		    I18n::setLanguage('de');
		    expect(['Musterstraße 1', '12345 Musterstadt', 'Tschechien'], Format::addressLines($prague), 'addressLines de');
		    I18n::setLanguage('en');
		    $retreat = format_test_event(['languages' => 'de, cs']);
		    expect('German, Czech', Format::languages($retreat), 'languages en');
		    expect('German, Čeština', Format::languages($retreat, ['cs' => 'Čeština']), 'languages: custom label');
		    I18n::setLanguage('de');
		    expect('Deutsch, Tschechisch', Format::languages($retreat), 'languages de');
		    I18n::setLanguage('en');

		    // labels(): the keys of SPEC §8.5 and the same values as the single methods.
		    $labels = Format::labels($retreat);
		    expect(
		        ['when', 'dateRange', 'status', 'previousStart', 'attendance', 'availability', 'price', 'series', 'languages'],
		        array_keys($labels),
		        'labels: keys'
		    );
		    expect(Format::when($retreat), $labels['when'], 'labels: when');
		    expect('German, Czech', $labels['languages'], 'labels: languages');

		    // Unknown language falls back to English.
		    I18n::setLanguage('fr');
		    expect('en', I18n::language(), 'unknown language: I18n uses en');
		    expect('Friday, 12 March 2027', Format::date(new DateTimeImmutable('2027-03-12', $berlin)), 'unknown language: English patterns');
		    I18n::setLanguage('en');

		    // configure(): a per-language pattern overrides the default.
		    Format::configure(['de' => ['time' => 'G.i']]);
		    I18n::setLanguage('de');
		    expect('18.00', Format::time($noon), 'configure: time pattern');
		    expect('Freitag, 12. März 2027, 18.00 Uhr', Format::when(format_test_event(['endTime' => ''])), 'configure: time pattern in when()');
		    I18n::setLanguage('en');
		    Format::reset();
		    I18n::setLanguage('de');
		    expect('18:00', Format::time($noon), 'reset(): default time pattern again');
		    I18n::setLanguage('en');

		    // configure(): a range pair override.
		    Format::configure(['en' => ['rangeSameMonth' => ['j.', 'j. F Y']]]);
		    expect('8.–11. March 2027', Format::dateRange(format_test_event(['startDate' => '2027-03-08', 'endDate' => '2027-03-11', 'startTime' => '', 'allDay' => true])), 'configure: range pair override');
		    Format::reset();
		    expect('8–11 March 2027', Format::dateRange(format_test_event(['startDate' => '2027-03-08', 'endDate' => '2027-03-11', 'startTime' => '', 'allDay' => true])), 'reset(): range pair default');

		    // apply(): tokens, escape and copied characters.
		    $t = new DateTimeImmutable('2027-03-12 18:00', $berlin);
		    expect('Fri 12 Mar 27, 18:00 T', Format::apply($t, 'D j M y, G:i \T'), 'apply: short tokens, \T escape');
		    expect('2027-03-12 18:00:00', Format::apply($t, 'Y-m-d H:i:s'), 'apply: ISO-like tokens');
		    expect('3/12/27 F', Format::apply($t, 'n/j/y \F'), 'apply: escaped F is literal');
		    expect('Friday, 12 March 2027', Format::apply($t, 'l, j F Y'), 'apply: long names');
		    expect('12 Mar', Format::apply($t, 'j M'), 'apply: month short');
		    I18n::setLanguage('de');
		    expect('Freitag, 12. März 2027', Format::apply($t, 'l, j. F Y'), 'apply: German names');
		    expect('Fr 12. März', Format::apply($t, 'D j. M'), 'apply: German short names (monthShort.3 is März)');
		    I18n::setLanguage('en');
		    expect('18:00', Format::apply($t, 'H:i'), 'apply: copied characters');
		    expect('abc 2027', Format::apply($t, 'abc Y'), 'apply: plain text copied');
		    expect('', Format::apply($t, ''), 'apply: empty pattern');

		    // Languages of the event are set by the caller; reset() leaves the words alone.
		    expect('en', I18n::language(), 'the language is restored to en');

		} finally {
		Format::reset();
		}	}
}

<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Koyomi\I18n;

/**
 * Translations: every language has the same keys and placeholders as
 * English; fallback rules; the labels the spec names exist in German.
 */
#[CoversClass(I18n::class)]
final class I18nTest extends TestCase
{
	public function testSuite(): void
	{
		    $placeholders = function (string $text): array {
		        preg_match_all('/\{(\w+)\}/', $text, $m);
		        $names = array_unique($m[1]);
		        sort($names);
		        return $names;
		    };

		    $en = I18n::table('en');
		    holds(count($en) > 150, 'English table loaded');
		    foreach (I18n::LANGUAGES as $language) {
		        $table = I18n::table($language);
		        expect([], array_values(array_diff(array_keys($en), array_keys($table))), "$language: no missing keys");
		        expect([], array_values(array_diff(array_keys($table), array_keys($en))), "$language: no extra keys");
		        foreach ($en as $key => $text) {
		            holds(is_string($table[$key]) && trim($table[$key]) !== '', "$language.$key not empty");
		            expect($placeholders($text), $placeholders($table[$key]), "$language.$key: same placeholders as English");
		        }
		    }
		    expect([], I18n::table('fr'), 'unknown language: no table');

		    // Every month, weekday (ISO 1–7) and status/attendance/availability value has a label.
		    foreach (range(1, 12) as $n) {
		        holds(I18n::has("month.$n") && I18n::has("monthShort.$n"), "month $n");
		    }
		    foreach (range(1, 7) as $n) {
		        holds(I18n::has("weekday.$n") && I18n::has("weekdayShort.$n"), "weekday $n");
		    }
		    require_once __DIR__ . '/../src/Normalize.php';
		    require_once __DIR__ . '/../src/Status.php';
		    require_once __DIR__ . '/../src/Attendance.php';
		    require_once __DIR__ . '/../src/Availability.php';
		    foreach (\Sayamaapps\Koyomi\Status::ALL as $v) { holds(I18n::has("status.$v"), "status.$v"); }
		    foreach (\Sayamaapps\Koyomi\Attendance::ALL as $v) { holds(I18n::has("attendance.$v"), "attendance.$v"); }
		    foreach (\Sayamaapps\Koyomi\Availability::ALL as $v) { holds(I18n::has("availability.$v"), "availability.$v"); }

		    I18n::setLanguage('fr');
		    expect('en', I18n::language(), 'unknown language falls back to English');
		    expect('Cancelled', I18n::t('status.cancelled'), 'English default');
		    expect('no.such.key', I18n::t('no.such.key'), 'missing key returns the key');
		    expect('Part 2 of 3', I18n::t('series.part', ['part' => 2, 'total' => 3]), 'placeholders replaced');

		    I18n::setLanguage('DE');
		    expect('de', I18n::language(), 'German selected, case-insensitive');
		    // The German wording the spec asks for.
		    expect('Online', I18n::t('attendance.online'), 'Online');
		    expect('Vor Ort', I18n::t('attendance.offline'), 'Vor Ort');
		    expect('Hybrid', I18n::t('attendance.mixed'), 'Hybrid');
		    expect('Abgesagt', I18n::t('status.cancelled'), 'Abgesagt');
		    expect('Verschoben', I18n::t('status.postponed'), 'Verschoben');
		    expect('Neuer Termin', I18n::t('status.rescheduled'), 'Neuer Termin');
		    expect('Jetzt online', I18n::t('status.movedOnline'), 'Jetzt online');
		    expect('Kostenlos', I18n::t('price.free'), 'Kostenlos');
		    expect('Nur noch wenige Plätze', I18n::t('availability.limited'), 'Nur noch wenige Plätze');
		    expect('Ausgebucht', I18n::t('availability.soldout'), 'Ausgebucht');
		    expect('In den Kalender', I18n::t('calendar.add'), 'In den Kalender');
		    expect('Hat stattgefunden', I18n::t('event.past'), 'Hat stattgefunden');
		    expect('18:00–20:00 Uhr', I18n::t('when.timeRange', ['start' => '18:00', 'end' => '20:00']), 'German time range');

		    expect(['en' => 'Start date', 'de' => 'Beginn (Datum)'], I18n::all('field.startDate'), 'all(): every language for Panel labels');
		    expect(['en' => 'x.y', 'de' => 'x.y'], I18n::all('x.y'), 'all(): unknown key');
		    I18n::setLanguage('en');	}
}

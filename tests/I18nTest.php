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
		$this->assertTrue(count($en) > 150, 'English table loaded');
		foreach (I18n::LANGUAGES as $language) {
			$table = I18n::table($language);
			$this->assertSame([], array_values(array_diff(array_keys($en), array_keys($table))), "$language: no missing keys");
			$this->assertSame([], array_values(array_diff(array_keys($table), array_keys($en))), "$language: no extra keys");
			foreach ($en as $key => $text) {
				$this->assertTrue(is_string($table[$key]) && trim($table[$key]) !== '', "$language.$key not empty");
				$this->assertSame($placeholders($text), $placeholders($table[$key]), "$language.$key: same placeholders as English");
			}
		}
		$this->assertSame([], I18n::table('fr'), 'unknown language: no table');

		// Every month, weekday (ISO 1–7) and status/attendance/availability value has a label.
		foreach (range(1, 12) as $n) {
			$this->assertTrue(I18n::has("month.$n") && I18n::has("monthShort.$n"), "month $n");
		}
		foreach (range(1, 7) as $n) {
			$this->assertTrue(I18n::has("weekday.$n") && I18n::has("weekdayShort.$n"), "weekday $n");
		}
		require_once __DIR__ . '/../src/Normalize.php';
		require_once __DIR__ . '/../src/Status.php';
		require_once __DIR__ . '/../src/Attendance.php';
		require_once __DIR__ . '/../src/Availability.php';
		foreach (\Sayamaapps\Koyomi\Status::ALL as $v) {
			$this->assertTrue(I18n::has("status.$v"), "status.$v");
		}
		foreach (\Sayamaapps\Koyomi\Attendance::ALL as $v) {
			$this->assertTrue(I18n::has("attendance.$v"), "attendance.$v");
		}
		foreach (\Sayamaapps\Koyomi\Availability::ALL as $v) {
			$this->assertTrue(I18n::has("availability.$v"), "availability.$v");
		}

		I18n::setLanguage('fr');
		$this->assertSame('en', I18n::language(), 'unknown language falls back to English');
		$this->assertSame('Cancelled', I18n::t('status.cancelled'), 'English default');
		$this->assertSame('no.such.key', I18n::t('no.such.key'), 'missing key returns the key');
		$this->assertSame('Part 2 of 3', I18n::t('series.part', ['part' => 2, 'total' => 3]), 'placeholders replaced');

		I18n::setLanguage('DE');
		$this->assertSame('de', I18n::language(), 'German selected, case-insensitive');
		// The German wording the spec asks for.
		$this->assertSame('Online', I18n::t('attendance.online'), 'Online');
		$this->assertSame('Vor Ort', I18n::t('attendance.offline'), 'Vor Ort');
		$this->assertSame('Hybrid', I18n::t('attendance.mixed'), 'Hybrid');
		$this->assertSame('Abgesagt', I18n::t('status.cancelled'), 'Abgesagt');
		$this->assertSame('Verschoben', I18n::t('status.postponed'), 'Verschoben');
		$this->assertSame('Neuer Termin', I18n::t('status.rescheduled'), 'Neuer Termin');
		$this->assertSame('Jetzt online', I18n::t('status.movedOnline'), 'Jetzt online');
		$this->assertSame('Kostenlos', I18n::t('price.free'), 'Kostenlos');
		$this->assertSame('Nur noch wenige Plätze', I18n::t('availability.limited'), 'Nur noch wenige Plätze');
		$this->assertSame('Ausgebucht', I18n::t('availability.soldout'), 'Ausgebucht');
		$this->assertSame('In den Kalender', I18n::t('calendar.add'), 'In den Kalender');
		$this->assertSame('Hat stattgefunden', I18n::t('event.past'), 'Hat stattgefunden');
		$this->assertSame('18:00–20:00 Uhr', I18n::t('when.timeRange', ['start' => '18:00', 'end' => '20:00']), 'German time range');

		$this->assertSame(['en' => 'Start date', 'de' => 'Beginn (Datum)'], I18n::all('field.startDate'), 'all(): every language for Panel labels');
		$this->assertSame(['en' => 'x.y', 'de' => 'x.y'], I18n::all('x.y'), 'all(): unknown key');
		I18n::setLanguage('en');
	}
}

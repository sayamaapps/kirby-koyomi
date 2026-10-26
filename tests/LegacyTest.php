<?php

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Koyomi\Api;
use Sayamaapps\Koyomi\Options;

/**
 * The 1.x names keep working until the next major version: the global
 * functions (legacy.php) and the `koyomi.*` option spelling. Each one logs a
 * deprecation in debug mode, and the current spelling wins when both are set.
 */
#[CoversNothing]
final class LegacyTest extends TestCase
{
	public function testSuite(): void
	{
		$root = KoyomiFixture::create();
		$deprecations = [];
		set_error_handler(function (int $number, string $message) use (&$deprecations): bool {
			if ($number !== E_USER_DEPRECATED) {
				return false;
			}
			$deprecations[] = $message;
			return true;
		}, E_USER_DEPRECATED);

		try {
			// The 1.x spelling only: the plugin must still switch on, and say so.
			$kirby = KoyomiFixture::app($root, ['sayamaapps.koyomi.active' => null, 'koyomi.active' => true]);
			expect(true, Api::active($kirby), '1.x koyomi.active switches the plugin on');
			expect(['Koyomi: koyomi.active is deprecated, use sayamaapps.koyomi.active'], array_slice($deprecations, -1), 'option: deprecation names the new key');

			// Both spellings: the current one wins, without a deprecation.
			$both = KoyomiFixture::app($root, ['sayamaapps.koyomi.timezone' => 'Europe/Prague', 'koyomi.timezone' => 'Europe/Vienna']);
			$count = count($deprecations);
			expect('Europe/Prague', Options::timezone($both), 'the current option name wins over 1.x');
			expect($count, count($deprecations), 'the current option name logs nothing');

			// The 1.x functions still answer, each with a deprecation.
			$kirby = KoyomiFixture::app($root);
			$event = $kirby->page('events/retreat')->koyomi();
			holds($event !== null, 'fixture: the retreat event is valid');

			expect(true, koyomi_active(), 'koyomi_active() still works');
			expect(Api::now($kirby)->format('c'), koyomi_now()->format('c'), 'koyomi_now() gives the same clock');
			expect('en', koyomi_i18n(), 'koyomi_i18n() sets and returns the language');
			expect(Api::t('list.empty'), koyomi_t('list.empty'), 'koyomi_t() gives the same text');
			expect($event, koyomi_event($event), 'koyomi_event() passes the event through');
			expect(true, koyomi_option('active'), 'koyomi_option() reads the switch');
			koyomi_expires([$event]);
			koyomi_expires_at(null);

			expect(8, count(array_filter($deprecations, fn (string $message): bool => str_contains($message, '() is deprecated, use Sayamaapps\Koyomi\Api::'))), 'each of the eight 1.x functions logs one deprecation');
		} finally {
			restore_error_handler();
			KoyomiFixture::remove($root);
		}
	}
}

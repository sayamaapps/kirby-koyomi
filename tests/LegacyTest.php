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
	/** @var list<string> deprecation messages raised during the current test */
	private array $deprecations = [];

	protected function setUp(): void
	{
		$this->deprecations = [];
		set_error_handler(function (int $number, string $message): bool {
			if ($number !== E_USER_DEPRECATED) {
				return false;
			}
			$this->deprecations[] = $message;
			return true;
		}, E_USER_DEPRECATED);
	}

	protected function tearDown(): void
	{
		restore_error_handler();
	}

	public function testCurrentOptionNameWinsOverOneXWithoutDeprecation(): void
	{
		$root = KoyomiFixture::create();
		try {
			$both = KoyomiFixture::app($root, ['sayamaapps.koyomi.timezone' => 'Europe/Prague', 'koyomi.timezone' => 'Europe/Vienna']);
			$count = count($this->deprecations);
			$this->assertSame('Europe/Prague', Options::timezone($both), 'the current option name wins over 1.x');
			$this->assertSame($count, count($this->deprecations), 'the current option name logs nothing');
		} finally {
			KoyomiFixture::remove($root);
		}
	}

	public function testEachOneXFunctionStillAnswersAndLogsOneDeprecation(): void
	{
		$root = KoyomiFixture::create();
		try {
			$kirby = KoyomiFixture::app($root);
			$event = $kirby->page('events/retreat')->koyomi();
			$this->assertTrue($event !== null, 'fixture: the retreat event is valid');

			$this->assertSame(true, koyomi_active(), 'koyomi_active() still works');
			$this->assertSame(Api::now($kirby)->format('c'), koyomi_now()->format('c'), 'koyomi_now() gives the same clock');
			$this->assertSame('en', koyomi_i18n(), 'koyomi_i18n() sets and returns the language');
			$this->assertSame(Api::t('list.empty'), koyomi_t('list.empty'), 'koyomi_t() gives the same text');
			$this->assertSame($event, koyomi_event($event), 'koyomi_event() passes the event through');
			$this->assertSame(true, koyomi_option('active'), 'koyomi_option() reads the switch');
			koyomi_expires([$event]);
			koyomi_expires_at(null);

			$this->assertSame(
				8,
				count(array_filter($this->deprecations, fn (string $message): bool => str_contains($message, '() is deprecated, use Sayamaapps\Koyomi\Api::'))),
				'each of the eight 1.x functions logs one deprecation'
			);
		} finally {
			KoyomiFixture::remove($root);
		}
	}

	public function testOneXSwitchStillSwitchesThePluginOnAndSaysSo(): void
	{
		$root = KoyomiFixture::create();
		try {
			$kirby = KoyomiFixture::app($root, ['sayamaapps.koyomi.active' => null, 'koyomi.active' => true]);
			$this->assertSame(true, Api::active($kirby), '1.x koyomi.active switches the plugin on');
			$this->assertSame(['Koyomi: koyomi.active is deprecated, use sayamaapps.koyomi.active'], array_slice($this->deprecations, -1), 'option: deprecation names the new key');
		} finally {
			KoyomiFixture::remove($root);
		}
	}
}

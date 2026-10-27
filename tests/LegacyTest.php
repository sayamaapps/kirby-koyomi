<?php

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Koyomi\Api;

/**
 * The 1.x names keep working in 1.x: the global functions (legacy.php) and the
 * `koyomi.*` option names. They log nothing, so a site with debug mode on runs
 * as it did before. Only the 1.x names are read.
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

	public function testOneXFunctionsStillAnswerWithoutDeprecation(): void
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

			$this->assertSame([], $this->deprecations, 'the 1.x functions log nothing');
		} finally {
			KoyomiFixture::remove($root);
		}
	}

	public function testOneXSwitchSwitchesThePluginOn(): void
	{
		$root = KoyomiFixture::create();
		try {
			$kirby = KoyomiFixture::app($root, ['koyomi.active' => true]);
			$this->assertSame(true, Api::active($kirby), 'koyomi.active switches the plugin on');
			$this->assertSame([], $this->deprecations, 'the 1.x option logs nothing');
		} finally {
			KoyomiFixture::remove($root);
		}
	}

	public function testOnlyTheOneXOptionNameIsRead(): void
	{
		$root = KoyomiFixture::create();
		try {
			$kirby = KoyomiFixture::app($root, ['koyomi.active' => false, 'sayamaapps.koyomi.active' => true]);
			$this->assertSame(false, Api::active($kirby), 'a name 1.x never read is not read');
		} finally {
			KoyomiFixture::remove($root);
		}
	}
}

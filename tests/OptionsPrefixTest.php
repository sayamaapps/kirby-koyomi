<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Koyomi\Api;
use Sayamaapps\Koyomi\Options;

/**
 * Options are read as `sayamaapps.koyomi.<key>`. The 1.x name `koyomi.<key>` is
 * read as a fallback, silently, and the new name wins when both are set.
 */
#[CoversClass(Options::class)]
final class OptionsPrefixTest extends TestCase
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

	public function testDefaultWhenNeitherIsSet(): void
	{
		$root = KoyomiFixture::create();
		try {
			$kirby = KoyomiFixture::app($root);
			$this->assertSame(12, Options::perPage($kirby), 'nothing set gives the default');
		} finally {
			KoyomiFixture::remove($root);
		}
	}

	public function testLegacyWrapperReadsTheNewName(): void
	{
		$root = KoyomiFixture::create();
		try {
			$kirby = KoyomiFixture::app($root, ['sayamaapps.koyomi.perPage' => 7]);
			$this->assertSame(7, Api::option($kirby, 'perPage'), 'Api::option() reads the new name');
			$this->assertSame(7, koyomi_option('perPage'), 'koyomi_option() reads the new name');
			$this->assertSame([], $this->deprecations, 'the 1.x functions log nothing');
		} finally {
			KoyomiFixture::remove($root);
		}
	}

	public function testNewNameIsRead(): void
	{
		$root = KoyomiFixture::create();
		try {
			$kirby = KoyomiFixture::app($root, ['sayamaapps.koyomi.perPage' => 3]);
			$this->assertSame(3, Options::perPage($kirby), 'sayamaapps.koyomi.perPage is read');
		} finally {
			KoyomiFixture::remove($root);
		}
	}

	public function testNewNameWinsWhenBothAreSet(): void
	{
		$root = KoyomiFixture::create();
		try {
			$kirby = KoyomiFixture::app($root, ['koyomi.perPage' => 5, 'sayamaapps.koyomi.perPage' => 3]);
			$this->assertSame(3, Options::perPage($kirby), 'the new name wins');
		} finally {
			KoyomiFixture::remove($root);
		}
	}

	public function testOneXNameIsReadWithoutDeprecation(): void
	{
		$root = KoyomiFixture::create();
		try {
			$kirby = KoyomiFixture::app($root, ['koyomi.perPage' => 5]);
			$this->assertSame(5, Options::perPage($kirby), 'koyomi.perPage is read as fallback');
			$this->assertSame([], $this->deprecations, 'the 1.x name logs nothing, debug is on');
		} finally {
			KoyomiFixture::remove($root);
		}
	}

	public function testOneXNestedFormIsRead(): void
	{
		$root = KoyomiFixture::create();
		try {
			$kirby = KoyomiFixture::app($root, ['koyomi' => ['perPage' => 4]]);
			$this->assertSame(4, Options::perPage($kirby), "'koyomi' => ['perPage' => 4] is read as fallback");
		} finally {
			KoyomiFixture::remove($root);
		}
	}
}

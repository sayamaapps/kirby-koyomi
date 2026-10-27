<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Koyomi\Options;

/**
 * Options::languages() reads the option as a list or as a map of code => label
 * and returns code => label. Values are cast to strings, so a broken value
 * neither throws nor triggers a deprecation.
 */
#[CoversClass(Options::class)]
final class OptionsTest extends TestCase
{
	public function testLanguagesReadsListAndMap(): void
	{
		$root = KoyomiFixture::create();
		try {
			$kirby = KoyomiFixture::app($root, [
				'sayamaapps.koyomi.languages' => ['de', 'cs' => 'Čeština'],
			]);
			$this->assertSame(['de' => '', 'cs' => 'Čeština'], Options::languages($kirby));
		} finally {
			KoyomiFixture::remove($root);
		}
	}

	public function testLanguagesCastsNullLabelToEmptyString(): void
	{
		$root = KoyomiFixture::create();
		try {
			$kirby = KoyomiFixture::app($root, [
				'sayamaapps.koyomi.languages' => ['de' => null],
			]);
			$this->assertSame(['de' => ''], Options::languages($kirby));
		} finally {
			KoyomiFixture::remove($root);
		}
	}

	public function testLanguagesCastsArrayLabelWithoutTypeError(): void
	{
		// The cast turns the array into "Array" with a warning, as before. It must not throw a TypeError.
		$root = KoyomiFixture::create();
		try {
			$kirby = KoyomiFixture::app($root, [
				'sayamaapps.koyomi.languages' => ['de' => ['x']],
			]);
			$warnings = [];
			set_error_handler(function (int $number, string $message) use (&$warnings): bool {
				$warnings[] = $message;
				return true;
			});
			try {
				$languages = Options::languages($kirby);
			} finally {
				restore_error_handler();
			}
			$this->assertSame(['de' => 'Array'], $languages);
			$this->assertSame(['Array to string conversion'], $warnings);
		} finally {
			KoyomiFixture::remove($root);
		}
	}
}

<?php

namespace Sayamaapps\Koyomi;

/**
 * Strings of snippets, feeds and Panel labels, Kirby-free.
 *
 * The tables live in translations/<language>.php and are also registered as
 * Kirby plugin translations under `koyomi.<key>` (index.php), so site
 * snippets can use t('koyomi.status.cancelled'). Koyomi itself reads its own
 * tables: a missing Kirby translation must never silently turn a German
 * page English.
 *
 * The language is set per request (koyomi_i18n() in index.php, from
 * Options::language()) and defaults to English. Missing keys fall back to
 * English, then to the key itself.
 */
final class I18n
{
	/** Languages Koyomi ships. English is the default and the fallback. */
	public const LANGUAGES = ['en', 'de'];

	private static string $language = 'en';

	/** @var array<string, array<string,string>> */
	private static array $tables = [];

	/**
	 * The key in every shipped language, for Panel labels in blueprints:
	 * `['en' => 'Start date', 'de' => 'Beginn (Datum)']`. Kirby picks the
	 * entry of the Panel user's language and falls back to English.
	 *
	 * @return array<string,string>
	 */
	public static function all(string $key): array
	{
		$out = [];
		foreach (self::LANGUAGES as $language) {
			$out[$language] = self::table($language)[$key] ?? self::table('en')[$key] ?? $key;
		}
		return $out;
	}

	/** True when the English table has this key. */
	public static function has(string $key): bool
	{
		return array_key_exists($key, self::table('en'));
	}

	public static function language(): string
	{
		return self::$language;
	}

	public static function setLanguage(string $language): void
	{
		$language = strtolower($language);
		self::$language = in_array($language, self::LANGUAGES, true) ? $language : 'en';
	}

	/**
	 * `{name}` placeholders are replaced with the given values, unescaped —
	 * escaping is the caller's job (snippets use esc()).
	 */
	public static function t(string $key, array $values = []): string
	{
		$text = self::table(self::$language)[$key] ?? self::table('en')[$key] ?? $key;
		if ($values === []) {
			return $text;
		}
		$pairs = [];
		foreach ($values as $name => $value) {
			$pairs['{' . $name . '}'] = (string)$value;
		}
		return strtr($text, $pairs);
	}

	/** @return array<string,string> */
	public static function table(string $language): array
	{
		if (!in_array($language, self::LANGUAGES, true)) {
			return [];
		}
		return self::$tables[$language] ??= require dirname(__DIR__) . '/translations/' . $language . '.php';
	}
}

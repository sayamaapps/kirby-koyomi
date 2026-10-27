<?php

namespace Sayamaapps\Koyomi;

/**
 * Turns raw content values (Kirby stores everything as strings: toggles as
 * "true"/"false", tags as "a, b", numbers as "120,50") into typed values.
 * Kirby-free. Used by Event/Venue/Organizer::fromArray() and Filters.
 *
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-koyomi
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class Normalize
{
	/** "120", "120.5", "120,50", 120 → float; "", "abc", negatives, NAN → null. */
	public static function amount(mixed $value): float|null
	{
		if (is_int($value) || is_float($value)) {
			$number = (float)$value;
		} else {
			$text = str_replace([' ', "\u{00A0}"], '', self::string($value));
			if ($text === '') {
				return null;
			}
			// "1.200,50" (German) → "1200.50"; "1,200.50" (English) → "1200.50"; "120,5" → "120.5".
			if (str_contains($text, ',') && str_contains($text, '.')) {
				$text = strrpos($text, ',') > strrpos($text, '.')
					? str_replace(['.', ','], ['', '.'], $text)
					: str_replace(',', '', $text);
			} else {
				$text = str_replace(',', '.', $text);
			}
			if (!is_numeric($text)) {
				return null;
			}
			$number = (float)$text;
		}
		return is_finite($number) && $number >= 0 ? $number : null;
	}

	/** true, 1, "1", "true", "yes", "on" (any case) → true; everything else false. */
	public static function bool(mixed $value): bool
	{
		if (is_bool($value)) {
			return $value;
		}
		if (is_int($value)) {
			return $value === 1;
		}
		return in_array(strtolower(self::string($value)), ['1', 'true', 'yes', 'on'], true);
	}

	/** One of $allowed (exact match first, then case-insensitive), else $default. */
	public static function choice(mixed $value, array $allowed, string $default): string
	{
		$value = self::string($value);
		if (in_array($value, $allowed, true)) {
			return $value;
		}
		foreach ($allowed as $candidate) {
			if (strcasecmp($candidate, $value) === 0) {
				return $candidate;
			}
		}
		return $default;
	}

	/** A float in [$min, $max] or null ("48.137" → 48.137, "48,137" → 48.137). */
	public static function coordinate(mixed $value, float $min, float $max): float|null
	{
		$text = str_replace(',', '.', self::string($value));
		if (is_float($value) || is_int($value)) {
			$text = (string)$value;
		}
		if ($text === '' || !is_numeric($text)) {
			return null;
		}
		$number = (float)$text;
		return $number >= $min && $number <= $max ? $number : null;
	}

	/** Plausible email address or "". */
	public static function email(mixed $value): string
	{
		$email = self::string($value);
		return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : '';
	}

	/**
	 * Like list(), lower-cased, only codes matching /^[a-z]{2,3}(-[a-z0-9]{2,8})?$/ ("de", "cs", "pt-br").
	 *
	 * @return list<string>
	 */
	public static function languages(mixed $value): array
	{
		$out = [];
		foreach (self::list($value) as $code) {
			$code = strtolower($code);
			if (preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $code) && !in_array($code, $out, true)) {
				$out[] = $code;
			}
		}
		return $out;
	}

	/**
	 * A list of non-empty, trimmed, unique strings in input order. Accepts an
	 * array or a comma-separated string ("Retreat, Meditation").
	 * Uniqueness is case-insensitive; the first spelling wins.
	 *
	 * @return list<string>
	 */
	public static function list(mixed $value): array
	{
		$items = is_array($value) ? $value : explode(',', self::string($value));
		$out = [];
		$seen = [];
		foreach ($items as $item) {
			$item = self::string($item);
			$key = mb_strtolower($item);
			if ($item === '' || isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$out[] = $item;
		}
		return $out;
	}

	/** Positive integer or null ("2" → 2, "0", "", "x" → null). */
	public static function positiveInt(mixed $value): int|null
	{
		$text = self::string($value);
		if (is_int($value)) {
			return $value > 0 ? $value : null;
		}
		return preg_match('/^\d+$/', $text) && (int)$text > 0 ? (int)$text : null;
	}

	/** Trimmed string; arrays, objects without __toString and null give "". Control characters except \n and \t are removed. */
	public static function string(mixed $value): string
	{
		if (is_string($value) || is_int($value) || is_float($value) || $value instanceof \Stringable) {
			$text = str_replace(["\r\n", "\r"], "\n", (string)$value);
			return trim((string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text));
		}
		return '';
	}

	/** http(s) URL or "". Everything else (javascript:, mailto:, relative paths) is dropped. */
	public static function url(mixed $value): string
	{
		$url = self::string($value);
		return preg_match('~^https?://[^\s<>"]+$~i', $url) ? $url : '';
	}
}

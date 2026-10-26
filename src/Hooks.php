<?php

namespace Sayamaapps\Koyomi;

use Kirby\Cms\App;
use Kirby\Cms\Page;

/**
 * Kirby hooks.                                   OWNER: task `panel`
 * Contract: SPEC.md §8.13.
 */
final class Hooks
{
	/** Field values a duplicated event gets (dates and everything else are kept — the copy is a draft). */
	public const DUPLICATE_RESET = [
		'eventStatus' => Status::SCHEDULED,
		'previousStart' => '',
		'statusNote' => '',
		'availability' => Availability::AVAILABLE,
	];

	/**
	 * page.duplicate:after. Only when Options::active() and
	 * Options::duplicateReset(), and only for intendedTemplate koyomi-event:
	 * $duplicatePage->update(self::DUPLICATE_RESET). Never throws (errors go
	 * to error_log): a failing reset must not break duplicating. Returns the
	 * updated page (or the unchanged one).
	 */
	public static function duplicateAfter(Page $duplicatePage, Page $originalPage): Page
	{
		$kirby = App::instance();
		// Kirby replaces the duplicate with whatever this returns, so the page is always returned.
		if (!Options::active($kirby) || !Options::duplicateReset($kirby)) {
			return $duplicatePage;
		}
		if ($duplicatePage->intendedTemplate()->name() !== 'koyomi-event') {
			return $duplicatePage;
		}
		try {
			// update() without a language writes only the current one. The
			// status fields are shared across languages (translate: false), but
			// a text field of another translation is not: every language gets the
			// reset, so no translation of the copy keeps "cancelled".
			$page = $duplicatePage;
			$languages = $kirby->multilang() ? $kirby->languages()->codes() : [null];
			foreach ($languages as $code) {
				$page = $page->update(self::DUPLICATE_RESET, $code);
			}
			return $page;
		} catch (\Throwable $e) {
			// A failing reset must not break duplicating; the copy stays as a plain copy.
			error_log('Koyomi: duplicate reset failed: ' . $e->getMessage());
			return $duplicatePage;
		}
	}
}

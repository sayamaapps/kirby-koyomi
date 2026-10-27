<?php

use Kirby\Filesystem\F;

/**
 * Plugin registration for Koyomi (暦, "calendar, almanac"): an event calendar for
 * Kirby 5, with venues, organizers, status, online attendance, ICS, JSON-LD and RSS.
 *
 * Kirby loads this file. It registers the class map, the 1.x names and the
 * definitions in config/. The logic lives in src/. Off by default: without
 * `'koyomi.active' => true` every snippet prints nothing and every feed URL is
 * Kirby's 404.
 */
F::loadClasses(require __DIR__ . '/autoload.php', __DIR__);
require_once __DIR__ . '/legacy.php';

Kirby::plugin(
	name: 'sayamaapps/koyomi',
	extends: [
		'translations' => require __DIR__ . '/config/translations.php',
		'blueprints'   => require __DIR__ . '/config/blueprints.php',
		'pageModels'   => require __DIR__ . '/config/page-models.php',
		'templates'    => require __DIR__ . '/config/templates.php',
		'snippets'     => require __DIR__ . '/config/snippets.php',
		'collections'  => require __DIR__ . '/config/collections.php',
		'siteMethods'  => require __DIR__ . '/config/site-methods.php',
		'hooks'        => require __DIR__ . '/config/hooks.php',
	],
	root: __DIR__
);

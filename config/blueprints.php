<?php

use Kirby\Cms\App;
use Sayamaapps\Koyomi\Blueprints;
use Sayamaapps\Koyomi\Options;

// Prefixed names, so a site's own "event" or "venue" blueprint never collides.
// The closures let the select options (zones, currencies, languages) follow the
// config. A site overrides one with site/blueprints/pages/koyomi-event.yml.
return [
	'pages/koyomi-calendar' => function () {
		return Blueprints::calendar(Options::blueprintSettings(App::instance()));
	},
	'pages/koyomi-event' => function () {
		return Blueprints::event(Options::blueprintSettings(App::instance()));
	},
	'pages/koyomi-venues' => function () {
		return Blueprints::venues(Options::blueprintSettings(App::instance()));
	},
	'pages/koyomi-venue' => function () {
		return Blueprints::venue(Options::blueprintSettings(App::instance()));
	},
	'pages/koyomi-organizers' => function () {
		return Blueprints::organizers(Options::blueprintSettings(App::instance()));
	},
	'pages/koyomi-organizer' => function () {
		return Blueprints::organizer(Options::blueprintSettings(App::instance()));
	},
];

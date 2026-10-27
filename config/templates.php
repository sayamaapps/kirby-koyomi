<?php

// HTML templates are plain defaults (wrapped in snippet koyomi/layout, which a site
// overrides to use its own layout). The .ics, .rss and .json templates are Kirby
// content representations: /events.ics, /events/retreat.ics, /events.rss, /events.json.
$root = dirname(__DIR__);

return [
	'koyomi-calendar' => $root . '/templates/koyomi-calendar.php',
	'koyomi-calendar.ics' => $root . '/templates/koyomi-calendar.ics.php',
	'koyomi-calendar.rss' => $root . '/templates/koyomi-calendar.rss.php',
	'koyomi-calendar.json' => $root . '/templates/koyomi-calendar.json.php',
	'koyomi-event' => $root . '/templates/koyomi-event.php',
	'koyomi-event.ics' => $root . '/templates/koyomi-event.ics.php',
	'koyomi-venue' => $root . '/templates/koyomi-venue.php',
	'koyomi-organizer' => $root . '/templates/koyomi-organizer.php',
];

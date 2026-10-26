<?php

// Unstyled defaults with koyomi-* classes only. A site overrides any of them with
// site/snippets/koyomi/<name>.php (Kirby looks there first).
$root = dirname(__DIR__);

return [
	'koyomi/layout' => $root . '/snippets/koyomi/layout.php',
	'koyomi/list' => $root . '/snippets/koyomi/list.php',
	'koyomi/card' => $root . '/snippets/koyomi/card.php',
	'koyomi/event' => $root . '/snippets/koyomi/event.php',
	'koyomi/filters' => $root . '/snippets/koyomi/filters.php',
	'koyomi/switch' => $root . '/snippets/koyomi/switch.php',
	'koyomi/pagination' => $root . '/snippets/koyomi/pagination.php',
	'koyomi/past' => $root . '/snippets/koyomi/past.php',
	'koyomi/venue' => $root . '/snippets/koyomi/venue.php',
	'koyomi/subscribe' => $root . '/snippets/koyomi/subscribe.php',
	'koyomi/jsonld' => $root . '/snippets/koyomi/jsonld.php',
];

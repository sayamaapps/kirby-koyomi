<?php

use Sayamaapps\Koyomi\Api;

/**
 * schema.org JSON-LD of one event (SPEC §8.3, §8.14), for the <head> of an
 * event page. Koyomi's layout calls it for koyomi-event pages only; a site
 * with its own layout calls it the same way:
 *
 *   <?php snippet('koyomi/jsonld', ['event' => $page]) ?>
 *
 * Prints nothing when Koyomi is inactive or when the data is not an event.
 * Never prints lists: one event, one script element.
 *
 * @var \Sayamaapps\Koyomi\Event|\Sayamaapps\Koyomi\Models\EventPage|null $event  or $page
 */
if (!Api::active(kirby())) {
	return;
}
Api::i18n(kirby());

$e = Api::event($event ?? $page ?? null);
if (!$e instanceof \Sayamaapps\Koyomi\Event) {
	return;
}
echo \Sayamaapps\Koyomi\JsonLd::script(\Sayamaapps\Koyomi\JsonLd::event($e));

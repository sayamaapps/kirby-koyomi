<?php
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
if (!koyomi_active()) {
	return;
}
koyomi_i18n();

$e = koyomi_event($event ?? $page ?? null);
if (!$e instanceof \Sayamaapps\Koyomi\Event) {
	return;
}
echo \Sayamaapps\Koyomi\JsonLd::script(\Sayamaapps\Koyomi\JsonLd::event($e));

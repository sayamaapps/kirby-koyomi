<?php

use Sayamaapps\Koyomi\Api;

/**
 * The note "This event has already taken place." with a link back to the
 * upcoming dates. Prints nothing for an event that is not past, and nothing
 * when Koyomi is inactive.
 *
 *   <?php snippet('koyomi/past', ['event' => $event, 'calendarUrl' => $calendarUrl]) ?>
 *
 * @var \Sayamaapps\Koyomi\Event|\Sayamaapps\Koyomi\Models\EventPage|null $event       (required)
 * @var string|null $calendarUrl link target; default: the site's calendar
 */
if (!Api::active(kirby())) {
	return;
}
Api::i18n(kirby());

$e = Api::event($event ?? null);
if ($e === null || !$e->isPast(Api::now(kirby()))) {
	return;
}
if (!isset($calendarUrl)) {
	$calendarUrl = site()->koyomiCalendar()?->url();
}
$calendarUrl = (string)($calendarUrl ?? '');
?>
<p class="koyomi-past" role="note"><?= esc(Api::t('event.pastNote')) ?><?php if ($calendarUrl !== '') : ?> <a href="<?= esc($calendarUrl) ?>"><?= esc(Api::t('event.toUpcoming')) ?></a><?php endif ?></p>

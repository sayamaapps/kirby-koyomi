<?php
/**
 * The note "This event has already taken place." with a link back to the
 * upcoming dates. Prints nothing for an event that is not past, and nothing
 * when Koyomi is inactive.
 *
 *   <?php snippet('koyomi/past', ['event' => $event, 'calendarUrl' => $calendarUrl]) ?>
 *
 * @var \Koyomi\Event|\Koyomi\Models\EventPage|null $event       (required)
 * @var string|null                                  $calendarUrl link target; default: the site's calendar
 */
if (!koyomi_active()) {
    return;
}
koyomi_i18n();

$e = koyomi_event($event ?? null);
if ($e === null || !$e->isPast(koyomi_now())) {
    return;
}
if (!isset($calendarUrl)) {
    $calendarUrl = site()->koyomiCalendar()?->url();
}
$calendarUrl = (string) ($calendarUrl ?? '');
?>
<p class="koyomi-past" role="note"><?= esc(koyomi_t('event.pastNote')) ?><?php if ($calendarUrl !== '') : ?> <a href="<?= esc($calendarUrl) ?>"><?= esc(koyomi_t('event.toUpcoming')) ?></a><?php endif ?></p>

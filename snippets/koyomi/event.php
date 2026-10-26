<?php

use Sayamaapps\Koyomi\Api;

/**
 * The facts of one event: status, when, where, price, languages, organizers,
 * booking, calendar links and the series. NOT the title, the cover or the
 * text blocks — the event template prints those.
 *
 *   <?php snippet('koyomi/event', ['event' => $page]) ?>
 *   <?php snippet('koyomi/event', ['event' => $event, 'calendarUrl' => 'https://example.test/events']) ?>
 *
 * Calls Api::expires(kirby(), ) with this event alone, so the page is not cached
 * past its start or end. Prints nothing when Koyomi is inactive.
 *
 * @var \Sayamaapps\Koyomi\Event|\Sayamaapps\Koyomi\Models\EventPage|null $event       (or $page)
 * @var \Kirby\Cms\Page|null $page        (used when $event is not given)
 * @var string|null $calendarUrl link back to the calendar; default: the page's calendar
 */
if (!Api::active(kirby())) {
	return;
}
$language = Api::i18n(kirby());

$e = Api::event($event ?? ($page ?? null));
if ($e === null) {
	return;
}
Api::expires(kirby(), [$e]);

if (!isset($calendarUrl)) {
	// The calendar is the parent of an event page; a plain Event has none.
	$owner = $event ?? ($page ?? null);
	$parent = $owner instanceof \Kirby\Cms\Page ? $owner->parent() : null;
	$calendarUrl = $parent !== null && $parent->intendedTemplate()->name() === 'koyomi-calendar'
		? $parent->url()
		: site()->koyomiCalendar()?->url();
}
$calendarUrl = (string)($calendarUrl ?? '');

$kirby = \Kirby\Cms\App::instance();
$now = Api::now(kirby());
$past = $e->isPast($now);
$ongoing = $e->isOngoing($now);
$cancelled = $e->isCancelled();

$classes = ['koyomi-event', 'koyomi-event--' . $e->status];
if ($e->featured) {
	$classes[] = 'koyomi-event--featured';
}
if ($past) {
	$classes[] = 'koyomi-event--past';
}
if ($ongoing) {
	$classes[] = 'koyomi-event--ongoing';
}

$datetime = $e->allDay ? \Sayamaapps\Koyomi\Time::date($e->start) : \Sayamaapps\Koyomi\Time::iso($e->start);
$status = \Sayamaapps\Koyomi\Format::status($e);
$previous = \Sayamaapps\Koyomi\Format::previousStart($e);
$venue = $e->physicalVenue();
$price = \Sayamaapps\Koyomi\Format::price($e);
$availability = \Sayamaapps\Koyomi\Format::availability($e);
$languages = \Sayamaapps\Koyomi\Format::languages($e, \Sayamaapps\Koyomi\Options::languages($kirby));
$organizers = array_values(array_filter($e->organizers, fn (\Sayamaapps\Koyomi\Organizer $o) => $o->name !== ''));
$categories = implode(', ', $e->categories);

$icsLink = \Sayamaapps\Koyomi\Options::route($kirby, 'ics') && $e->icsUrl() !== '';
$googleLink = \Sayamaapps\Koyomi\Options::google($kirby) ? \Sayamaapps\Koyomi\GoogleLink::url($e, $language) : '';
$showCalendarLinks = !$past && !$cancelled && ($icsLink || $googleLink !== '');

$series = $e->series;
$seriesTotal = count($series);
?>
<div class="<?= esc(implode(' ', $classes)) ?>">
  <?php snippet('koyomi/past', ['event' => $e, 'calendarUrl' => $calendarUrl]) ?>

  <?php if ($status !== '') : ?>
  <p class="koyomi-status koyomi-status--<?= esc($e->status) ?>" role="status"><strong><?= esc($status) ?></strong><?php if ($e->statusNote !== '') : ?> <?= esc($e->statusNote) ?><?php endif ?></p>
  <?php endif ?>
  <?php if ($previous !== '') : ?>
  <p class="koyomi-previous"><?= esc($previous) ?></p>
  <?php endif ?>

  <dl class="koyomi-facts">
    <dt><?= esc(Api::t('event.when')) ?></dt>
    <dd class="koyomi-when"><time datetime="<?= esc($datetime) ?>"><?= esc(\Sayamaapps\Koyomi\Format::when($e)) ?></time></dd>

    <dt><?= esc(Api::t('event.where')) ?></dt>
    <dd class="koyomi-where">
      <?php if ($venue !== null) : ?>
      <?php snippet('koyomi/venue', ['venue' => $venue, 'compact' => true]) ?>
      <?php endif ?>
      <p class="koyomi-attendance"><?= esc(\Sayamaapps\Koyomi\Format::attendance($e)) ?></p>
    </dd>

    <?php if ($price !== '' || $e->priceNote !== '' || $availability !== '') : ?>
    <dt><?= esc(Api::t('price.label')) ?></dt>
    <dd class="koyomi-price"><?= esc($price) ?><?php if ($e->priceNote !== '') : ?> <span class="koyomi-price-note"><?= esc($e->priceNote) ?></span><?php endif ?><?php if ($availability !== '') : ?> <span class="koyomi-availability"><?= esc($availability) ?></span><?php endif ?></dd>
    <?php endif ?>

    <?php if ($languages !== '') : ?>
    <dt><?= esc(Api::t('event.languages')) ?></dt>
    <dd class="koyomi-languages"><?= esc($languages) ?></dd>
    <?php endif ?>

    <?php if ($organizers !== []) : ?>
    <dt><?= esc(Api::t(count($organizers) > 1 ? 'event.organizers' : 'event.organizer')) ?></dt>
    <dd class="koyomi-organizers">
      <ul>
        <?php foreach ($organizers as $organizer) : ?>
        <li class="koyomi-organizer"><?php if ($organizer->link() !== '') : ?><a href="<?= esc($organizer->link()) ?>"><?= esc($organizer->name) ?></a><?php else : ?><?= esc($organizer->name) ?><?php endif ?><?php if ($organizer->email !== '') : ?> <a class="koyomi-organizer-email" href="mailto:<?= esc($organizer->email) ?>"><?= esc($organizer->email) ?></a><?php endif ?></li>
        <?php endforeach ?>
      </ul>
    </dd>
    <?php endif ?>

    <?php if ($categories !== '') : ?>
    <dt><?= esc(Api::t('event.categories')) ?></dt>
    <dd class="koyomi-categories"><?= esc($categories) ?></dd>
    <?php endif ?>
  </dl>

  <?php if ($e->bookingUrl !== '' && !$past && !$cancelled) : ?>
    <?php if ($e->availability === \Sayamaapps\Koyomi\Availability::SOLDOUT) : ?>
    <p class="koyomi-booking koyomi-booking--soldout"><?= esc(Api::t('availability.soldout')) ?></p>
    <?php else : ?>
    <p class="koyomi-booking"><a class="koyomi-booking-link" href="<?= esc($e->bookingUrl) ?>" target="_blank" rel="noopener"><?= esc($e->bookingLabel !== '' ? $e->bookingLabel : Api::t('booking.default')) ?><span class="koyomi-sr-only"> <?= esc(Api::t('booking.opensNewTab')) ?></span></a></p>
    <?php endif ?>
  <?php endif ?>

  <?php if ($showCalendarLinks) : ?>
  <p class="koyomi-calendar-links"><span class="koyomi-calendar-heading"><?= esc(Api::t('calendar.add')) ?></span><?php if ($icsLink) : ?> <a class="koyomi-calendar-ics" href="<?= esc($e->icsUrl()) ?>" download><?= esc(Api::t('calendar.ics')) ?></a><?php endif ?><?php if ($googleLink !== '') : ?> <a class="koyomi-calendar-google" href="<?= esc($googleLink) ?>" target="_blank" rel="noopener nofollow"><?= esc(Api::t('calendar.google')) ?></a><?php endif ?></p>
  <?php endif ?>

  <?php if ($seriesTotal > 0) : ?>
  <nav class="koyomi-series" aria-label="<?= esc(Api::t('series.title')) ?>">
    <p><?= esc(\Sayamaapps\Koyomi\Format::series($e)) ?></p>
    <ol>
      <?php foreach ($series as $item) : ?>
      <?php $itemTitle = $item['title'] !== '' ? $item['title'] : Api::t('series.part', ['part' => $item['part'], 'total' => $seriesTotal]); ?>
      <li>
        <?php if ($item['current']) : ?>
        <span aria-current="page"><?= esc($itemTitle) ?></span> <?= esc(Api::t('series.current')) ?>
        <?php elseif ($item['url'] !== '') : ?>
        <a href="<?= esc($item['url']) ?>"><?= esc($itemTitle) ?></a>
        <?php else : ?>
        <?= esc($itemTitle) ?>
        <?php endif ?>
        · <?= esc(\Sayamaapps\Koyomi\Format::date($item['start'])) ?>
      </li>
      <?php endforeach ?>
    </ol>
  </nav>
  <?php endif ?>
</div>

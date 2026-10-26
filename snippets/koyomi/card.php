<?php
/**
 * One event as a card: image, title, when, status, place, price, excerpt.
 * Used by koyomi/list and the venue and organizer templates. Unstyled; the
 * classes are the hooks for a site's CSS.
 *
 *   <?php snippet('koyomi/card', ['event' => $event]) ?>
 *   <?php snippet('koyomi/card', ['event' => $event, 'level' => 2]) ?>
 *
 * Prints nothing when Koyomi is inactive or the data is not an event.
 * Status, "Happening now" and "has taken place" are text, never icons.
 *
 * For a timeline the card also carries a calendar-sheet date
 * (.koyomi-card-date: weekday, day, month) and the time of day alone
 * (.koyomi-card-time). Both are aria-hidden: .koyomi-card-when keeps the full
 * date and time for screen readers, a site may hide it visually.
 *
 * @var \Sayamaapps\Koyomi\Event|\Sayamaapps\Koyomi\Models\EventPage|null $event (required)
 * @var int|null $level heading level of the title, 3 by default
 */
if (!koyomi_active()) {
    return;
}
koyomi_i18n();

$e = koyomi_event($event ?? null);
if ($e === null) {
    return;
}

$now = koyomi_now();
$level = min(6, max(1, (int) ($level ?? 3)));
$past = $e->isPast($now);
$ongoing = $e->isOngoing($now);

$classes = ['koyomi-card', 'koyomi-card--' . $e->status];
if ($e->featured) {
    $classes[] = 'koyomi-card--featured';
}
if ($past) {
    $classes[] = 'koyomi-card--past';
}
if ($ongoing) {
    $classes[] = 'koyomi-card--ongoing';
}

$datetime = $e->allDay ? \Sayamaapps\Koyomi\Time::date($e->start) : \Sayamaapps\Koyomi\Time::iso($e->start);
$sheet = \Sayamaapps\Koyomi\Format::dayBlock($e);
$status = \Sayamaapps\Koyomi\Format::status($e);

// Place: venue name and city (in person only), then the attendance label.
$venue = $e->physicalVenue();
$where = implode(', ', array_values(array_filter([$venue?->name ?? '', $venue?->city ?? ''], fn (string $part) => $part !== '')));
$placeLine = implode(' · ', array_values(array_filter([$where, \Sayamaapps\Koyomi\Format::attendance($e)], fn (string $part) => $part !== '')));

$priceLine = implode(' · ', array_values(array_filter(
    [\Sayamaapps\Koyomi\Format::price($e), \Sayamaapps\Koyomi\Format::availability($e)],
    fn (string $part) => $part !== ''
)));
?>
<article class="<?= esc(implode(' ', $classes)) ?>">
  <?php if ($e->image !== '') : ?>
  <img class="koyomi-card-image" src="<?= esc($e->image) ?>" alt="<?= esc($e->imageAlt) ?>" loading="lazy">
  <?php endif ?>
  <p class="koyomi-card-date" aria-hidden="true"><span class="koyomi-card-weekday"><?= esc($sheet['weekday']) ?></span> <span class="koyomi-card-day"><?= esc($sheet['day']) ?></span> <span class="koyomi-card-month"><?= esc($sheet['month']) ?></span><?php if ($sheet['until'] !== '') : ?> <span class="koyomi-card-until"><?= esc($sheet['until']) ?></span><?php endif ?></p>
  <h<?= $level ?> class="koyomi-card-title"><?php if ($e->url !== '') : ?><a href="<?= esc($e->url) ?>"><?= esc($e->title) ?></a><?php else : ?><?= esc($e->title) ?><?php endif ?></h<?= $level ?>>
  <p class="koyomi-card-when"><time datetime="<?= esc($datetime) ?>"><?= esc(\Sayamaapps\Koyomi\Format::when($e)) ?></time></p>
  <p class="koyomi-card-time" aria-hidden="true"><?= esc(\Sayamaapps\Koyomi\Format::clock($e)) ?></p>
  <?php if ($status !== '') : ?>
  <p class="koyomi-card-status"><strong><?= esc($status) ?></strong></p>
  <?php endif ?>
  <?php if ($ongoing) : ?>
  <p class="koyomi-card-badge"><?= esc(koyomi_t('event.ongoing')) ?></p>
  <?php elseif ($past) : ?>
  <p class="koyomi-card-badge"><?= esc(koyomi_t('event.past')) ?></p>
  <?php endif ?>
  <p class="koyomi-card-place"><?= esc($placeLine) ?></p>
  <?php if ($priceLine !== '') : ?>
  <p class="koyomi-card-price"><?= esc($priceLine) ?></p>
  <?php endif ?>
  <?php if ($e->description !== '') : ?>
  <p class="koyomi-card-excerpt"><?= esc($e->description) ?></p>
  <?php endif ?>
</article>

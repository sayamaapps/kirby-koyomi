<?php

use Sayamaapps\Koyomi\Api;

/**
 * A venue: name, address, and (outside compact mode) directions and the
 * self-hosted map image. Links: a plain OpenStreetMap link (or
 * `koyomi.mapUrl`) and the venue's website. The map image is only shown when
 * the venue has `showMap` on — Koyomi never loads a map from a third party.
 *
 *   <?php snippet('koyomi/venue', ['venue' => $venue]) ?>
 *   <?php snippet('koyomi/venue', ['venue' => $venue, 'compact' => true]) ?>
 *
 * Prints nothing when Koyomi is inactive or the data is not a venue.
 *
 * @var \Sayamaapps\Koyomi\Venue|\Sayamaapps\Koyomi\Models\VenuePage|null $venue   (required)
 * @var bool|null $compact name and address only (used inside an event)
 */
if (!Api::active(kirby())) {
	return;
}
Api::i18n(kirby());

$v = $venue ?? null;
if ($v instanceof \Sayamaapps\Koyomi\Models\VenuePage) {
	$v = $v->koyomiVenue();
}
if (!$v instanceof \Sayamaapps\Koyomi\Venue) {
	return;
}
$compact = (bool)($compact ?? false);

$addressLines = \Sayamaapps\Koyomi\Format::addressLines($v, \Sayamaapps\Koyomi\Options::country(\Kirby\Cms\App::instance()));
$mapLink = $v->mapLink();
?>
<div class="koyomi-venue">
  <p class="koyomi-venue-name"><?php if ($v->url !== '') : ?><a href="<?= esc($v->url) ?>"><?= esc($v->name) ?></a><?php else : ?><?= esc($v->name) ?><?php endif ?></p>
  <?php if ($addressLines !== []) : ?>
  <p class="koyomi-venue-address"><?= implode('<br>', array_map(fn (string $line) => esc($line), $addressLines)) ?></p>
  <?php endif ?>
  <?php if (!$compact && $v->directions !== '') : ?>
  <p class="koyomi-venue-directions"><strong><?= esc(Api::t('venue.directions')) ?></strong><br><?= nl2br(esc($v->directions)) ?></p>
  <?php endif ?>
  <?php if (!$compact && $v->showMap && $v->mapImage !== '') : ?>
  <img class="koyomi-venue-map" src="<?= esc($v->mapImage) ?>" alt="<?= esc($v->mapImageAlt !== '' ? $v->mapImageAlt : Api::t('venue.mapAlt', ['name' => $v->name])) ?>" loading="lazy">
  <?php endif ?>
  <?php if ($mapLink !== '' || $v->website !== '') : ?>
  <p class="koyomi-venue-links">
    <?php if ($mapLink !== '') : ?><a href="<?= esc($mapLink) ?>" target="_blank" rel="noopener"><?= esc(Api::t('venue.map')) ?></a><?php endif ?>
    <?php if ($v->website !== '') : ?><a href="<?= esc($v->website) ?>" rel="noopener"><?= esc(Api::t('venue.website')) ?></a><?php endif ?>
  </p>
  <?php endif ?>
</div>

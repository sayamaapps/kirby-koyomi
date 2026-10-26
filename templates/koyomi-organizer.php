<?php

use Sayamaapps\Koyomi\Api;

/**
 * Organizer page (template koyomi-organizer, unlisted): name, photo, website,
 * the public email (only when the organizer chose to show it — the model
 * never holds a hidden address) and the upcoming events as cards.
 */
Api::i18n(kirby());
$organizer = $page->koyomiOrganizer();
$photo = $page->content()->get('photo')->toFile();
$upcoming = Api::active(kirby()) ? $page->upcomingEvents() : [];
?>
<?php snippet('koyomi/layout', ['page' => $page], slots: true) ?>
<article class="koyomi-organizer-page">
  <h1><?= esc($page->title()) ?></h1>
  <?php if ($photo) : ?>
  <figure class="koyomi-photo">
    <img class="koyomi-photo-image" src="<?= esc($photo->url()) ?>" alt="<?= esc((string)$photo->alt()) ?>" loading="lazy">
  </figure>
  <?php endif ?>
  <?php if ($organizer->website !== '') : ?>
  <p class="koyomi-organizer-website"><a href="<?= esc($organizer->website) ?>" rel="noopener"><?= esc(Api::t('organizer.website')) ?></a></p>
  <?php endif ?>
  <?php if ($organizer->email !== '') : ?>
  <p class="koyomi-organizer-email"><a href="mailto:<?= esc($organizer->email) ?>"><?= esc($organizer->email) ?></a></p>
  <?php endif ?>
  <?php if (Api::active(kirby())) : ?>
  <section class="koyomi-list koyomi-list--upcoming">
    <h2 class="koyomi-list-title"><?= esc(Api::t('organizer.upcoming')) ?></h2>
    <?php if ($upcoming !== []) : ?>
    <ol class="koyomi-events">
      <?php foreach ($upcoming as $event) : ?>
      <li class="koyomi-events-item"><?php snippet('koyomi/card', ['event' => $event]) ?></li>
      <?php endforeach ?>
    </ol>
    <?php else : ?>
    <p class="koyomi-empty"><?= esc(Api::t('list.empty')) ?></p>
    <?php endif ?>
  </section>
  <?php endif ?>
</article>
<?php endsnippet() ?>

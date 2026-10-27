<?php

use Sayamaapps\Koyomi\Api;

/**
 * Venue page (template koyomi-venue, unlisted): the venue, then its upcoming
 * events as cards. The venues container and the events are read by the page
 * model; the snippets print nothing while `koyomi.active` is off.
 */
Api::i18n(kirby());
$upcoming = Api::active(kirby()) ? $page->upcomingEvents() : [];
?>
<?php snippet('koyomi/layout', ['page' => $page], slots: true) ?>
<h1><?= esc($page->title()) ?>
</h1>
<?php snippet('koyomi/venue', ['venue' => $page]) ?>
<?php if (Api::active(kirby())) : ?>
<section class="koyomi-list koyomi-list--upcoming">
  <h2 class="koyomi-list-title"><?= esc(Api::t('venue.upcoming')) ?>
</h2>
  <?php if ($upcoming !== []) : ?>
  <ol class="koyomi-events">
    <?php foreach ($upcoming as $event) : ?>
    <li class="koyomi-events-item"><?php snippet('koyomi/card', ['event' => $event]) ?>
</li>
    <?php endforeach ?>
  </ol>
  <?php else : ?>
  <p class="koyomi-empty"><?= esc(Api::t('list.empty')) ?>
</p>
  <?php endif ?>
</section>
<?php endif ?>
<?php endsnippet() ?>

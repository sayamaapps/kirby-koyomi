<?php

use Sayamaapps\Koyomi\Api;

/**
 * Event page (template koyomi-event): title, cover, short description, the
 * facts (koyomi/event snippet) and the text blocks. The snippet prints no
 * title and no text, so the template owns those. The .ics representation is
 * a separate template (koyomi-event.ics.php).
 */
Api::i18n(kirby());
$cover = $page->content()->get('cover')->toFile();
// The stored excerpt only: Event::description falls back to the text blocks
// (for lists and feeds), and this page prints the full text right below.
$excerpt = trim((string)$page->content()->get('excerpt')->value());
?>
<?php snippet('koyomi/layout', ['page' => $page], slots: true) ?>
<article class="koyomi-event-page">
  <h1><?= esc($page->title()) ?></h1>
  <?php if ($cover) : ?>
  <figure class="koyomi-cover">
    <img class="koyomi-cover-image" src="<?= esc($cover->url()) ?>" alt="<?= esc((string)$cover->alt()) ?>">
  </figure>
  <?php endif ?>
  <?php if ($excerpt !== '') : ?>
  <p class="koyomi-excerpt"><?= esc($excerpt) ?></p>
  <?php endif ?>
  <?php snippet('koyomi/event', ['event' => $page]) ?>
  <?php if ($page->content()->get('text')->isNotEmpty()) : ?>
  <div class="koyomi-text"><?= $page->content()->get('text')->toBlocks() ?></div>
  <?php endif ?>
</article>
<?php endsnippet() ?>

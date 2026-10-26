<?php
/**
 * The HTML document around Koyomi's pages, used by the four HTML templates.
 * It is the one snippet that is not silent when Koyomi is inactive: it is a
 * layout, so it always prints the slot. A site with its own layout overrides
 * THIS file (site/snippets/koyomi/layout.php) and prints $slot where the
 * content goes.
 *
 *   <?php snippet('koyomi/layout', ['page' => $page], slots: true) ?>
 *     … page content …
 *   <?php endsnippet() ?>
 *
 * @var \Kirby\Cms\Page|null $page   the current page (EventPage gets JSON-LD in the head)
 * @var \Kirby\Template\Slot $slot   the captured content (Kirby slot)
 */
$page ??= null;
$language = koyomi_i18n();
$siteTitle = (string)site()->title();
$pageTitle = (string)($page?->title() ?? '');
$title = $pageTitle !== '' && $pageTitle !== $siteTitle ? $pageTitle . ' – ' . $siteTitle : $siteTitle;
?>
<!doctype html>
<html lang="<?= esc($language) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc($title) ?></title>
  <?php if ($page instanceof \Sayamaapps\Koyomi\Models\EventPage) : ?>
  <?php snippet('koyomi/jsonld', ['page' => $page]) ?>
  <?php endif ?>
</head>
<body>
  <main class="koyomi-main">
    <?= $slot ?>
  </main>
</body>
</html>

<?php

use Sayamaapps\Koyomi\Api;

/**
 * Previous / next links between the pages of a list. Prints nothing when
 * there is only one page, and nothing when Koyomi is inactive. The URLs come
 * from the Repository list array (they keep the filters).
 *
 *   <?php snippet('koyomi/pagination', ['list' => $list]) ?>
 *   <?php snippet('koyomi/pagination', ['calendar' => $page]) ?>   (the list of that calendar)
 *
 * @var array|null $list     Repository::list() shape (SPEC §8.9)
 * @var \Kirby\Cms\Page|null $calendar used when $list is not given
 */
if (!Api::active(kirby())) {
	return;
}
Api::i18n(kirby());

if (!isset($list)) {
	$list = ($calendar ?? site()->koyomiCalendar())?->koyomiList();
}
$pagination = is_array($list) ? ($list['pagination'] ?? null) : null;
if (!is_array($pagination) || (int)($pagination['pages'] ?? 1) <= 1) {
	return;
}

$page = (int)($pagination['page'] ?? 1);
$pages = (int)$pagination['pages'];
$prevUrl = (string)($pagination['prevUrl'] ?? '');
$nextUrl = (string)($pagination['nextUrl'] ?? '');
?>
<nav class="koyomi-pagination" aria-label="<?= esc(Api::t('pagination.label')) ?>">
  <?php if ($prevUrl !== '') : ?>
  <a rel="prev" href="<?= esc($prevUrl) ?>"><?= esc(Api::t('pagination.previous')) ?></a>
  <?php endif ?>
  <span><?= esc(Api::t('pagination.page', ['page' => $page, 'pages' => $pages])) ?></span>
  <?php if ($nextUrl !== '') : ?>
  <a rel="next" href="<?= esc($nextUrl) ?>"><?= esc(Api::t('pagination.next')) ?></a>
  <?php endif ?>
</nav>

<?php

use Sayamaapps\Koyomi\Api;

/**
 * The filter form: a plain GET form (no JavaScript). Selects only for the
 * values that exist in the current facets, so an empty filter never shows.
 * Everything comes from the Repository list array — the snippet builds no
 * URLs of its own beyond the form action.
 *
 *   <?php snippet('koyomi/filters', ['list' => $list]) ?>
 *   <?php snippet('koyomi/filters', ['calendar' => $page]) ?>   (the list of that calendar)
 *
 * Prints nothing when Koyomi is inactive or there is nothing to filter by.
 *
 * @var array|null $list     Repository::list() shape
 * @var \Kirby\Cms\Page|null $calendar used when $list is not given
 * @var bool|null $attendance false = no format select (koyomi/switch shows it);
 *                a chosen format is then kept as a hidden field
 */
if (!Api::active(kirby())) {
	return;
}
Api::i18n(kirby());

if (!isset($list)) {
	$list = ($calendar ?? site()->koyomiCalendar())?->koyomiList();
}
if (!is_array($list)) {
	return;
}

$facets = $list['facets'] ?? [];
$filters = $list['filters'] ?? [];
$view = ($list['view'] ?? 'upcoming') === 'past' ? 'past' : 'upcoming';
$filtered = (bool)($list['filtered'] ?? false);
$baseUrl = (string)($list['baseUrl'] ?? '');
$resetUrl = (string)($view === 'past' ? ($list['pastUrl'] ?? '') : ($list['upcomingUrl'] ?? ''));
if ($resetUrl === '') {
	$resetUrl = $baseUrl;
}
$customLanguages = \Sayamaapps\Koyomi\Options::languages(\Kirby\Cms\App::instance());

$categories = $facets['categories'] ?? [];
$languages = $facets['languages'] ?? [];
$featuredCount = (int)($facets['featured'] ?? 0);
$attendance ??= true;
$attendanceOptions = [];
foreach ($attendance ? \Sayamaapps\Koyomi\Attendance::ALL : [] as $code) {
	$count = (int)($facets['attendance'][$code] ?? 0);
	if ($count > 0) {
		$attendanceOptions[$code] = $count;
	}
}

if ($categories === [] && $languages === [] && $attendanceOptions === [] && $featuredCount === 0 && !$filtered) {
	return;
}

$selectedCategory = isset($filters['category']) ? (string)$filters['category'] : null;
$selectedAttendance = isset($filters['attendance']) ? (string)$filters['attendance'] : null;
$selectedLanguage = isset($filters['language']) ? (string)$filters['language'] : null;
$featuredChecked = !empty($filters['featured']);
?>
<form class="koyomi-filters" method="get" action="<?= esc($baseUrl) ?>" aria-label="<?= esc(Api::t('filters.label')) ?>">
  <?php if ($view === 'past') : ?>
  <input type="hidden" name="view" value="past">
  <?php endif ?>
  <?php if (!$attendance && $selectedAttendance !== null) : ?>
  <input type="hidden" name="attendance" value="<?= esc($selectedAttendance) ?>">
  <?php endif ?>

  <?php if ($categories !== []) : ?>
  <p class="koyomi-filter">
    <label for="koyomi-filter-category"><?= esc(Api::t('filters.category')) ?></label>
    <select id="koyomi-filter-category" name="category">
      <option value=""><?= esc(Api::t('filters.all')) ?></option>
      <?php foreach ($categories as $name => $count) : ?>
      <option value="<?= esc((string)$name) ?>"<?= $selectedCategory !== null && strcasecmp(
	(string)$name,
	$selectedCategory
) === 0 ? ' selected' : '' ?>><?= esc((string)$name) ?> (<?= (int)$count ?>)</option>
      <?php endforeach ?>
    </select>
  </p>
  <?php endif ?>

  <?php if ($attendanceOptions !== []) : ?>
  <p class="koyomi-filter">
    <label for="koyomi-filter-attendance"><?= esc(Api::t('filters.attendance')) ?></label>
    <select id="koyomi-filter-attendance" name="attendance">
      <option value=""><?= esc(Api::t('filters.all')) ?></option>
      <?php foreach ($attendanceOptions as $code => $count) : ?>
      <option value="<?= esc($code) ?>"<?= $selectedAttendance === $code ? ' selected' : '' ?>><?= esc(Api::t('attendance.' . $code)) ?> (<?= $count ?>)</option>
      <?php endforeach ?>
    </select>
  </p>
  <?php endif ?>

  <?php if ($languages !== []) : ?>
  <p class="koyomi-filter">
    <label for="koyomi-filter-language"><?= esc(Api::t('filters.language')) ?></label>
    <select id="koyomi-filter-language" name="language">
      <option value=""><?= esc(Api::t('filters.all')) ?></option>
      <?php foreach ($languages as $code => $count) : ?>
      <option value="<?= esc((string)$code) ?>"<?= $selectedLanguage === (string)$code ? ' selected' : '' ?>><?= esc(\Sayamaapps\Koyomi\Format::languageName(
	(string)$code,
	(string)($customLanguages[$code] ?? '')
)) ?> (<?= (int)$count ?>)</option>
      <?php endforeach ?>
    </select>
  </p>
  <?php endif ?>

  <?php if ($featuredCount > 0) : ?>
  <p class="koyomi-filter">
    <label><input type="checkbox" name="featured" value="1"<?= $featuredChecked ? ' checked' : '' ?>> <?= esc(Api::t('filters.featured')) ?></label>
  </p>
  <?php endif ?>

  <p class="koyomi-filter-actions">
    <button type="submit"><?= esc(Api::t('filters.apply')) ?></button>
    <?php if ($filtered && $resetUrl !== '') : ?>
    <a class="koyomi-filters-reset" href="<?= esc($resetUrl) ?>"><?= esc(Api::t('filters.reset')) ?></a>
    <?php endif ?>
  </p>
</form>

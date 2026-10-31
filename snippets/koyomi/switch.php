<?php

use Sayamaapps\Koyomi\Api;

/**
 * The attendance switch: "All · In person · Online" as plain links (no
 * JavaScript), each with its number of events. A hybrid event counts under
 * both, so whoever wants to come in person, or to join from home, finds it
 * either way. Links keep the other filters and the view (upcoming / past).
 *
 *   <?php snippet('koyomi/switch', ['list' => $list]) ?>
 *   <?php snippet('koyomi/switch', ['calendar' => $page]) ?>   (the list of that calendar)
 *
 * Prints nothing when Koyomi is inactive, when sayamaapps.koyomi.attendanceSwitch is
 * false, or when the view has no in-person or no online event (nothing to
 * switch). The counts are those of the whole view, before other filters.
 *
 * @var array|null $list     Repository::list() shape
 * @var \Kirby\Cms\Page|null $calendar used when $list is not given
 */
if (!Api::active(kirby()) || !\Sayamaapps\Koyomi\Options::attendanceSwitch(\Kirby\Cms\App::instance())) {
	return;
}
Api::i18n(kirby());

if (!isset($list)) {
	$list = ($calendar ?? site()->koyomiCalendar())?->koyomiList();
}
if (!is_array($list)) {
	return;
}

$counts = $list['facets']['attendance'] ?? [];
$offline = (int)($counts[\Sayamaapps\Koyomi\Attendance::OFFLINE] ?? 0);
$online = (int)($counts[\Sayamaapps\Koyomi\Attendance::ONLINE] ?? 0);
$mixed = (int)($counts[\Sayamaapps\Koyomi\Attendance::MIXED] ?? 0);
if ($offline === 0 || $online === 0) {
	return;
}

$filters = ($list['filters'] ?? []) + \Sayamaapps\Koyomi\Filters::EMPTY;
$baseUrl = (string)($list['baseUrl'] ?? '');
$current = $filters['attendance'];
$options = [
	[null, Api::t('switch.all'), $offline + $online - $mixed],
	[\Sayamaapps\Koyomi\Attendance::OFFLINE, Api::t('attendance.offline'), $offline],
	[\Sayamaapps\Koyomi\Attendance::ONLINE, Api::t('attendance.online'), $online],
];
?>
<nav class="koyomi-switch" aria-label="<?= esc(Api::t('switch.label')) ?>
">
  <ul>
    <?php foreach ($options as [$code, $label, $count]) :
		$href = \Sayamaapps\Koyomi\Filters::url($baseUrl, $filters, ['attendance' => $code]);
		$countLabel = Api::t('switch.count', ['count' => (string)$count]);
		$active = $current === $code;
	?>
    <li><a class="koyomi-switch-option<?= $active ? ' koyomi-switch-option--active' : '' ?>
" href="<?= esc($href) ?>
"<?= $active ? ' aria-current="page"' : '' ?>
><?= esc($label) ?>
 <span class="koyomi-switch-count" aria-label="<?= esc($countLabel) ?>
"><?= (int)$count ?>
</span></a></li>
    <?php endforeach ?>
  </ul>
</nav>

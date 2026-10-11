<?php
/**
 * The attendance switch: "All · In person · Online" as plain links (no
 * JavaScript), each with its number of events. A hybrid event counts under
 * both, so whoever wants to come in person, or to join from home, finds it
 * either way. Links keep the other filters and the view (upcoming / past).
 *
 *   <?php snippet('koyomi/switch', ['list' => $list]) ?>
 *   <?php snippet('koyomi/switch', ['calendar' => $page]) ?>   (the list of that calendar)
 *
 * Prints nothing when Koyomi is inactive, when koyomi.attendanceSwitch is
 * false, or when the view has no in-person or no online event (nothing to
 * switch). The counts are those of the whole view, before other filters.
 *
 * @var array|null           $list     Repository::list() shape (SPEC §8.9)
 * @var \Kirby\Cms\Page|null $calendar used when $list is not given
 */
if (!koyomi_active() || !\Koyomi\Options::attendanceSwitch(\Kirby\Cms\App::instance())) {
    return;
}
koyomi_i18n();

if (!isset($list)) {
    $list = (isset($calendar) ? $calendar : site()->koyomiCalendar())?->koyomiList();
}
if (!is_array($list)) {
    return;
}

$counts = $list['facets']['attendance'] ?? [];
$offline = (int) ($counts[\Koyomi\Attendance::OFFLINE] ?? 0);
$online = (int) ($counts[\Koyomi\Attendance::ONLINE] ?? 0);
$mixed = (int) ($counts[\Koyomi\Attendance::MIXED] ?? 0);
if ($offline === 0 || $online === 0) {
    return;
}

$filters = ($list['filters'] ?? []) + \Koyomi\Filters::EMPTY;
$baseUrl = (string) ($list['baseUrl'] ?? '');
$current = $filters['attendance'];
$options = [
    [null, koyomi_t('switch.all'), $offline + $online - $mixed],
    [\Koyomi\Attendance::OFFLINE, koyomi_t('attendance.offline'), $offline],
    [\Koyomi\Attendance::ONLINE, koyomi_t('attendance.online'), $online],
];
?>
<nav class="koyomi-switch" aria-label="<?= esc(koyomi_t('switch.label')) ?>">
  <ul>
    <?php foreach ($options as [$code, $label, $count]) : ?>
    <li><a class="koyomi-switch-option<?= $current === $code ? ' koyomi-switch-option--active' : '' ?>" href="<?= esc(\Koyomi\Filters::url($baseUrl, $filters, ['attendance' => $code])) ?>"<?= $current === $code ? ' aria-current="page"' : '' ?>><?= esc($label) ?> <span class="koyomi-switch-count" aria-label="<?= esc(koyomi_t('switch.count', ['count' => (string) $count])) ?>"><?= (int) $count ?></span></a></li>
    <?php endforeach ?>
  </ul>
</nav>

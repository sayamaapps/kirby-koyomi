<?php
/**
 * One list of events: a heading, the cards and the switch to the other view
 * (upcoming ↔ past). Pass the array of Repository::list() (filters, pagination
 * and URLs come with it), or a plain list of events. Without either, the
 * calendar of the site is used.
 *
 *   <?php snippet('koyomi/list', ['list' => $list]) ?>
 *   <?php snippet('koyomi/list', ['events' => $events, 'title' => 'Next dates', 'level' => 3]) ?>
 *
 * Prints nothing when Koyomi is inactive. Never calls Repository for an
 * event: the calendar is only looked up when no data was passed.
 *
 * @var array|null          $list   Repository::list() shape (SPEC §8.9)
 * @var iterable|null       $events list of Koyomi\Event or event pages
 * @var string|false|null   $title  heading text; false = no heading
 * @var int|null            $level  heading level, 2 by default; the cards use level + 1
 */
if (!koyomi_active()) {
    return;
}
koyomi_i18n();

$list ??= null;
$events ??= null;
if ($list === null && $events === null) {
    $list = site()->koyomiCalendar()?->koyomiList();
}

$view = ($list['view'] ?? 'upcoming') === 'past' ? 'past' : 'upcoming';
$filtered = (bool) ($list['filtered'] ?? false);
$title ??= koyomi_t($view === 'past' ? 'list.past' : 'list.upcoming');
$level = min(6, max(1, (int) ($level ?? 2)));

$items = [];
foreach ($events ?? ($list['events'] ?? []) as $item) {
    $event = koyomi_event($item);
    if ($event !== null) {
        $items[] = $event;
    }
}
koyomi_expires($items);

if ($filtered) {
    $emptyText = koyomi_t('list.emptyFiltered');
} elseif ($view === 'past') {
    $emptyText = koyomi_t('list.pastEmpty');
} else {
    $emptyText = koyomi_t('list.empty');
}

$switchUrl = null;
$switchText = '';
if ($list !== null) {
    $switchUrl = $view === 'past' ? ($list['upcomingUrl'] ?? null) : ($list['pastUrl'] ?? null);
    $switchText = koyomi_t($view === 'past' ? 'list.toUpcoming' : 'list.toPast');
}
?>
<section class="koyomi-list koyomi-list--<?= esc($view) ?>">
  <?php if ($title !== false && $title !== '') : ?>
  <h<?= $level ?> class="koyomi-list-title"><?= esc($title) ?></h<?= $level ?>>
  <?php endif ?>
  <?php if ($items !== []) : ?>
  <ol class="koyomi-events">
    <?php foreach ($items as $event) : ?>
    <li class="koyomi-events-item"><?php snippet('koyomi/card', ['event' => $event, 'level' => $level + 1]) ?></li>
    <?php endforeach ?>
  </ol>
  <?php else : ?>
  <p class="koyomi-empty"><?= esc($emptyText) ?></p>
  <?php endif ?>
  <?php if ($switchUrl !== null && $switchUrl !== '') : ?>
  <p class="koyomi-list-switch"><a href="<?= esc($switchUrl) ?>"><?= esc($switchText) ?></a></p>
  <?php endif ?>
</section>

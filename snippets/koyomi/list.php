<?php

use Sayamaapps\Koyomi\Api;

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
 * @var array|null $list   Repository::list() shape
 * @var iterable|null $events list of Sayamaapps\Koyomi\Event or event pages
 * @var string|false|null $title  heading text; false = no heading
 * @var int|null $level  heading level, 2 by default; months use level + 1, cards level + 2
 * @var string|false|null $group  'month' = timeline with month headings (default from
 *                        sayamaapps.koyomi.groupBy), false = one flat list (cards use level + 1)
 */
if (!Api::active(kirby())) {
	return;
}
Api::i18n(kirby());

$list ??= null;
$events ??= null;
if ($list === null && $events === null) {
	$list = site()->koyomiCalendar()?->koyomiList();
}

$view = ($list['view'] ?? 'upcoming') === 'past' ? 'past' : 'upcoming';
$filtered = (bool)($list['filtered'] ?? false);
$title ??= Api::t($view === 'past' ? 'list.past' : 'list.upcoming');
$level = min(6, max(1, (int)($level ?? 2)));
$group ??= \Sayamaapps\Koyomi\Options::groupBy(\Kirby\Cms\App::instance());
$group = $group === 'month' ? 'month' : false;

$items = [];
foreach ($events ?? ($list['events'] ?? []) as $item) {
	$event = Api::event($item);
	if ($event !== null) {
		$items[] = $event;
	}
}
Api::expires(kirby(), $items);

if ($filtered) {
	$emptyText = Api::t('list.emptyFiltered');
} elseif ($view === 'past') {
	$emptyText = Api::t('list.pastEmpty');
} else {
	$emptyText = Api::t('list.empty');
}

$switchUrl = null;
$switchText = '';
if ($list !== null) {
	$switchUrl = $view === 'past' ? ($list['upcomingUrl'] ?? null) : ($list['pastUrl'] ?? null);
	$switchText = Api::t($view === 'past' ? 'list.toUpcoming' : 'list.toPast');
}
?>
<section class="koyomi-list koyomi-list--<?= esc($view) ?>
">
  <?php if ($title !== false && $title !== '') : ?>
  <h<?= $level ?>
 class="koyomi-list-title"><?= esc($title) ?>
</h<?= $level ?>
>
  <?php endif ?>
  <?php if ($items !== [] && $group === 'month') : ?>
  <div class="koyomi-timeline">
    <?php if ($view === 'upcoming') : $today = Api::now(kirby()); Api::expiresAt(kirby(), \Sayamaapps\Koyomi\Expiry::midnight($today)); ?>
    <p class="koyomi-today"><time datetime="<?= esc($today->format('Y-m-d')) ?>
"><?= esc(Api::t('list.today', ['date' => \Sayamaapps\Koyomi\Format::date($today)])) ?>
</time></p>
    <?php endif ?>
    <?php foreach (\Sayamaapps\Koyomi\Query::byMonth($items) as $month) :
		$monthLevel = min(6, $level + 1);
		$monthKey = esc($month['key']);
		$monthId = esc($month['key'], 'attr');
		$monthTitle = esc(\Sayamaapps\Koyomi\Format::monthTitle($month['first']));
	?>
    <section class="koyomi-month" aria-labelledby="koyomi-month-<?= esc($month['key'], 'attr') ?>
">
      <h<?= $monthLevel ?>
 class="koyomi-month-title" id="koyomi-month-<?= $monthId ?>
"><time datetime="<?= $monthKey ?>
"><?= $monthTitle ?>
</time></h<?= $monthLevel ?>
>
      <ol class="koyomi-events">
        <?php foreach ($month['events'] as $event) : ?>
        <li class="koyomi-events-item"><?php snippet('koyomi/card', ['event' => $event, 'level' => min(6, $level + 2)]) ?>
</li>
        <?php endforeach ?>
      </ol>
    </section>
    <?php endforeach ?>
  </div>
  <?php elseif ($items !== []) : ?>
  <ol class="koyomi-events">
    <?php foreach ($items as $event) : ?>
    <li class="koyomi-events-item"><?php snippet('koyomi/card', ['event' => $event, 'level' => $level + 1]) ?>
</li>
    <?php endforeach ?>
  </ol>
  <?php else : ?>
  <p class="koyomi-empty"><?= esc($emptyText) ?>
</p>
  <?php endif ?>
  <?php if ($switchUrl !== null && $switchUrl !== '') : ?>
  <p class="koyomi-list-switch"><a href="<?= esc($switchUrl) ?>
"><?= esc($switchText) ?>
</a></p>
  <?php endif ?>
</section>

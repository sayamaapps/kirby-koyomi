<?php

use Sayamaapps\Koyomi\Api;

/**
 * Calendar page (template koyomi-calendar): title, intro, filters, the list of
 * the current view (upcoming or past), pagination and the subscription links.
 * All output comes from the koyomi/* snippets, which print nothing while
 * `sayamaapps.koyomi.active` is off. A site replaces the whole page with its own
 * site/templates/koyomi-calendar.php; the .ics/.rss/.json outputs keep working.
 */
Api::i18n(kirby());
$list = Api::active(kirby()) ? $page->koyomiList() : null;
?>
<?php snippet('koyomi/layout', ['page' => $page], slots: true) ?>
<h1><?= esc($page->title()) ?>
</h1>
<?php if ($page->content()->get('intro')->isNotEmpty()) : ?>
<div class="koyomi-intro"><?= $page->content()->get('intro')->kirbytext() ?>
</div>
<?php endif ?>
<?php $switch = \Sayamaapps\Koyomi\Options::attendanceSwitch($kirby) ?>
<?php if ($switch) : ?>
<?php snippet('koyomi/switch', ['list' => $list]) ?>
<?php endif ?>
<?php snippet('koyomi/filters', ['list' => $list, 'attendance' => !$switch]) ?>
<?php snippet('koyomi/list', ['list' => $list]) ?>
<?php snippet('koyomi/pagination', ['list' => $list]) ?>
<?php snippet('koyomi/subscribe', ['calendar' => $page]) ?>
<?php endsnippet() ?>

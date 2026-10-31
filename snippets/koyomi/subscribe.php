<?php

use Sayamaapps\Koyomi\Api;

/**
 * Subscription links of a calendar: the feed as "webcal://" (subscribe in the
 * calendar app, new dates arrive by themselves) and the RSS feed. The URLs
 * are built from the calendar's own URL, nothing else. Each link is printed
 * only when its output switch is on (`sayamaapps.koyomi.feed`, `sayamaapps.koyomi.rss`).
 *
 *   <?php snippet('koyomi/subscribe', ['calendar' => $page]) ?>
 *
 * Prints nothing when Koyomi is inactive or both switches are off.
 *
 * @var \Kirby\Cms\Page|null $calendar (default: the site's calendar)
 */
if (!Api::active(kirby())) {
	return;
}
Api::i18n(kirby());

$kirby = \Kirby\Cms\App::instance();
$feed = \Sayamaapps\Koyomi\Options::route($kirby, 'feed');
$rss = \Sayamaapps\Koyomi\Options::route($kirby, 'rss');
if (!$feed && !$rss) {
	return;
}
if (!isset($calendar)) {
	$calendar = site()->koyomiCalendar();
}
if ($calendar === null) {
	return;
}

$base = (string)$calendar->url();
$ics = $base . '.ics';
$webcal = (string)preg_replace('~^https?://~', 'webcal://', $ics);
$rssUrl = $base . '.rss';
?>
<div class="koyomi-subscribe">
  <?php if ($feed) : ?>
  <p class="koyomi-subscribe-link"><a href="<?= esc($webcal) ?>
"><?= esc(Api::t('calendar.subscribe')) ?>
</a></p>
  <p class="koyomi-subscribe-hint"><?= esc(Api::t('calendar.subscribeHint')) ?>
 <code><?= esc($ics) ?>
</code></p>
  <?php endif ?>
  <?php if ($rss) : ?>
  <p class="koyomi-subscribe-rss"><a href="<?= esc($rssUrl) ?>
" type="application/rss+xml"><?= esc(Api::t('calendar.rss')) ?>
</a></p>
  <?php endif ?>
</div>

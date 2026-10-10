<?php
/**
 * Subscription links of a calendar: the feed as "webcal://" (subscribe in the
 * calendar app, new dates arrive by themselves) and the RSS feed. The URLs
 * are built from the calendar's own URL, nothing else. Each link is printed
 * only when its output switch is on (`koyomi.feed`, `koyomi.rss`).
 *
 *   <?php snippet('koyomi/subscribe', ['calendar' => $page]) ?>
 *
 * Prints nothing when Koyomi is inactive or both switches are off.
 *
 * @var \Kirby\Cms\Page|null $calendar (default: the site's calendar)
 */
if (!koyomi_active()) {
    return;
}
koyomi_i18n();

$kirby = \Kirby\Cms\App::instance();
$feed = \Koyomi\Options::route($kirby, 'feed');
$rss = \Koyomi\Options::route($kirby, 'rss');
if (!$feed && !$rss) {
    return;
}
if (!isset($calendar)) {
    $calendar = site()->koyomiCalendar();
}
if ($calendar === null) {
    return;
}

$base = (string) $calendar->url();
$ics = $base . '.ics';
$webcal = (string) preg_replace('~^https?://~', 'webcal://', $ics);
$rssUrl = $base . '.rss';
?>
<div class="koyomi-subscribe">
  <?php if ($feed) : ?>
  <p class="koyomi-subscribe-link"><a href="<?= esc($webcal) ?>"><?= esc(koyomi_t('calendar.subscribe')) ?></a></p>
  <p class="koyomi-subscribe-hint"><?= esc(koyomi_t('calendar.subscribeHint')) ?> <code><?= esc($ics) ?></code></p>
  <?php endif ?>
  <?php if ($rss) : ?>
  <p class="koyomi-subscribe-rss"><a href="<?= esc($rssUrl) ?>" type="application/rss+xml"><?= esc(koyomi_t('calendar.rss')) ?></a></p>
  <?php endif ?>
</div>

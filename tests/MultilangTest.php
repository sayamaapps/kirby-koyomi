<?php

use Kirby\Cms\App;
use Kirby\Cms\Page;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Koyomi\Mapper;

/** A German (default) and Czech site with one event that has a saved Czech translation. */
function multilang_site(): string
{
    $root = sys_get_temp_dir() . '/koyomi-fixture-' . bin2hex(random_bytes(6));
    foreach (['public', 'site/plugins', 'site/templates', 'site/languages', 'storage', 'content'] as $dir) {
        mkdir($root . '/' . $dir, 0700, true);
    }
    symlink(dirname(__DIR__), $root . '/site/plugins/koyomi');
    file_put_contents($root . '/site/templates/default.php', '<?= $page->title()->escape() ?>');
    file_put_contents($root . '/site/languages/de.php', "<?php return ['code' => 'de', 'default' => true, 'url' => '/', 'locale' => 'de_DE', 'name' => 'Deutsch'];");
    file_put_contents($root . '/site/languages/cs.php', "<?php return ['code' => 'cs', 'default' => false, 'url' => '/cs', 'locale' => 'cs_CZ', 'name' => 'Čeština'];");

    KoyomiFixture::write($root . '/content/site.de.txt', ['Title' => 'Site']);
    KoyomiFixture::write($root . '/content/1_events/koyomi-calendar.de.txt', ['Title' => 'Events']);
    KoyomiFixture::write($root . '/content/1_events/20270312_retreat/koyomi-event.de.txt', [
        'Title' => 'Retreat', 'Uuid' => 'evt-retreat',
        'StartDate' => '2027-03-12', 'StartTime' => '18:00:00', 'EndTime' => '13:00:00', 'Timezone' => 'Europe/Berlin',
        'InPerson' => 'true', 'Online' => 'false', 'EventStatus' => 'scheduled', 'Availability' => 'available',
    ]);
    // A Czech translation as the Panel saves it: the title and the status fields both.
    KoyomiFixture::write($root . '/content/1_events/20270312_retreat/koyomi-event.cs.txt', [
        'Title' => 'Retreat CZ', 'Uuid' => 'evt-retreat',
        'StartDate' => '2027-03-12', 'StartTime' => '18:00:00', 'EndTime' => '13:00:00', 'Timezone' => 'Europe/Berlin',
        'InPerson' => 'true', 'Online' => 'false', 'EventStatus' => 'scheduled', 'Availability' => 'available',
    ]);
    return $root;
}

function multilang_app(string $root, array $options = []): App
{
    return new App([
        'roots' => [
            'index' => $root . '/public', 'base' => $root, 'content' => $root . '/content',
            'site' => $root . '/site', 'storage' => $root . '/storage',
        ],
        'options' => array_merge([
            'url' => 'https://example.test', 'debug' => true, 'koyomi.active' => true, 'koyomi.now' => '2027-03-10 12:00',
        ], $options),
    ]);
}

/**
 * Multi-language sites (review round, findings 2 and 8). The status, dates,
 * price, venue and organizer fields are shared by every language (translate:
 * false), so a change in the default language reaches every translation and
 * the ICS UID stays one event. The duplicate reset covers every translation.
 */
#[CoversNothing]
final class MultilangTest extends TestCase
{
	public function testSuite(): void
	{
		$root = multilang_site();

		try {

		    $app = multilang_app($root);
		    holds($app->multilang(), 'the test site is multi-language');

		    // Finding 8 / 2: cancel and move in the default language; the Czech translation follows.
		    $app->impersonate('kirby');
		    $app->page('events/retreat')->update(['EventStatus' => 'cancelled', 'StartDate' => '2027-04-01'], 'de');

		    $app = multilang_app($root);
		    $app->setCurrentLanguage('cs');
		    $czech = $app->page('events/retreat');
		    expect('Retreat CZ', $czech->title()->value(), 'cs keeps its own title');
		    // The stored Czech file still says "scheduled" and the old date (saved before the field was shared).
		    expect(['scheduled', '2027-03-12'], [$czech->content('cs')->get('eventStatus')->value(), substr($czech->content('cs')->get('startDate')->value(), 0, 10)], 'precondition: the Czech file holds the old values');
		    $czechEvent = Mapper::event($czech);
		    expect(['cancelled', '2027-04-01'], [$czechEvent->status, substr(Mapper::raw($czech)['startDate'], 0, 10)], 'cs: the Event is the cancelled one on the new date (shared fields from the default language)');
		    $czechIcs = (string) $app->render('events/retreat.ics')?->body();
		    holds(str_contains($czechIcs, 'STATUS:CANCELLED') && str_contains($czechIcs, 'DTSTART;TZID=Europe/Berlin:20270401T180000'), 'cs ICS: same status and date as de');

		    $app->setCurrentLanguage('de');
		    $deIcs = (string) multilang_app($root)->render('events/retreat.ics')?->body();
		    holds(str_contains($deIcs, 'STATUS:CANCELLED') && str_contains($deIcs, 'DTSTART;TZID=Europe/Berlin:20270401T180000'), 'de ICS: cancelled on the new date');
		    holds(str_contains($deIcs, 'UID:evt-retreat@example.test') && str_contains($czechIcs, 'UID:evt-retreat@example.test'), 'the same UID in every language');

		    // Finding 2: the duplicate reset reaches every translation of the copy.
		    $app = multilang_app($root);
		    $app->impersonate('kirby');
		    $app->setCurrentLanguage('cs');
		    $app->page('events/retreat')->update(['EventStatus' => 'cancelled', 'StatusNote' => 'Zrušeno'], 'cs');
		    $app->setCurrentLanguage('de');
		    $copy = $app->page('events/retreat')->duplicate('retreat-copy');
		    holds($copy instanceof Page, 'duplicate returns a Page');
		    expect('scheduled', $copy->content()->get('eventStatus')->value(), 'duplicate: de status reset');
		    // A fresh App reads the copy the way a later request does (the duplicate's own page object still holds the pre-reset content).
		    $fresh = multilang_app($root);
		    $fresh->impersonate('kirby');
		    $fresh->setCurrentLanguage('cs');
		    $copyCs = $fresh->page('events/retreat-copy');
		    expect('scheduled', Mapper::event($copyCs)->status, 'duplicate: the Czech translation is reset too (what the outputs read)');
		    expect('', Mapper::raw($copyCs)['statusNote'], 'duplicate: a translatable status note is cleared in every language');
		    $copyCs->delete(true);

		} finally {
		KoyomiFixture::remove($root);
		}	}
}

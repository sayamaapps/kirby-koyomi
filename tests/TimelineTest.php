<?php

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Koyomi\Event;
use Sayamaapps\Koyomi\Format;
use Sayamaapps\Koyomi\I18n;
use Sayamaapps\Koyomi\Query;

/**
 * The timeline: Query::byMonth(), Format::monthTitle/dayBlock/clock, the
 * attendance switch (koyomi/switch) and the filter form without its format
 * select. With the fixture calendar (tests/lib/Fixture.php, now 2027-03-10).
 */
#[CoversNothing]
final class TimelineTest extends TestCase
{
	public function testSuite(): void
	{
		$root = KoyomiFixture::create();

		try {
			$kirby = KoyomiFixture::app($root);
			$event = fn (array $override) => Event::fromArray(koyomi_raw_event($override));

			// ---- byMonth ------------------------------------------------------------
			$a = $event(['startDate' => '2027-03-12', 'endDate' => '']);
			$b = $event(['startDate' => '2027-03-30', 'endDate' => '2027-04-02']);
			$c = $event(['startDate' => '2027-05-01', 'endDate' => '']);
			$groups = Query::byMonth([$a, $b, $c]);
			$this->assertSame(['2027-03', '2027-05'], array_column($groups, 'key'), 'byMonth: one group per start month, multi-day event under its start month');
			$this->assertSame(2, count($groups[0]['events']), 'byMonth: both March events in March');
			$this->assertSame(['2027-05', '2027-03'], array_column(Query::byMonth([$c, $a]), 'key'), 'byMonth: keeps the given order (past view, newest first)');
			$this->assertSame([], Query::byMonth([]), 'byMonth: empty');

			// ---- Format ------------------------------------------------------------
			I18n::setLanguage('en');
			$this->assertSame('March 2027', Format::monthTitle($a->start), 'monthTitle en');
			$this->assertSame(['weekday' => 'Fri', 'day' => '12', 'month' => 'Mar', 'until' => ''], Format::dayBlock($a), 'dayBlock: single day');
			$this->assertSame(['weekday' => 'Tue', 'day' => '30', 'month' => 'Mar', 'until' => 'until Apr 2'], Format::dayBlock($b), 'dayBlock: across months');
			$this->assertSame('18:00–20:00', Format::clock($a), 'clock: single day with end');
			$allDay = $event(['allDay' => 'true', 'startDate' => '2027-03-12', 'endDate' => '2027-03-14', 'startTime' => '', 'endTime' => '']);
			$this->assertSame('all day', Format::clock($allDay), 'clock: all day');
			$this->assertSame('12–14', Format::dayBlock($allDay)['day'], 'dayBlock: days of a range in one month');
			$prague = $event(['timezone' => 'Europe/London']);
			$this->assertTrue(str_contains(Format::clock($prague), '(Europe/London)'), 'clock: zone added when it differs from the site zone');

			I18n::setLanguage('de');
			$this->assertSame('März 2027', Format::monthTitle($a->start), 'monthTitle de');
			$this->assertSame(['weekday' => 'Di', 'day' => '30.', 'month' => 'März', 'until' => 'bis 2. Apr.'], Format::dayBlock($b), 'dayBlock de: across months');
			$this->assertSame('18:00–20:00 Uhr', Format::clock($a), 'clock de');
			$this->assertSame('Di 18:00 – Fr 20:00 Uhr', Format::clock($b), 'clock de: multi-day with end time');
			$this->assertSame('ganztägig', Format::clock($allDay), 'clock de: all day');
			I18n::setLanguage('en');

			// ---- attendance switch -------------------------------------------------
			$calendar = $kirby->page('events');
			$list = $calendar->koyomiList();
			$counts = $list['facets']['attendance'];
			$html = (string)$kirby->snippet('koyomi/switch', ['list' => $list], true);
			$this->assertTrue(str_contains($html, '<nav class="koyomi-switch" aria-label="Format">'), 'switch: nav with label');
			$this->assertTrue(substr_count($html, 'class="koyomi-switch-option') === 3, 'switch: All, In person, Online');
			$total = $counts['offline'] + $counts['online'] - $counts['mixed'];
			$this->assertTrue(
				str_contains($html, 'aria-current="page">All <span class="koyomi-switch-count" aria-label="' . $total . ' events">' . $total . '</span>'),
				'switch: All is current, hybrid counted once'
			);
			$this->assertTrue(str_contains($html, 'href="' . $calendar->url() . '?attendance=online"'), 'switch: online link');
			$this->assertTrue(!str_contains($html, 'attendance=mixed'), 'switch: no separate hybrid option');

			$online = $calendar->koyomiList(['attendance' => 'online']);
			$html = (string)$kirby->snippet('koyomi/switch', ['list' => $online], true);
			$this->assertTrue(
				str_contains($html, 'koyomi-switch-option--active" href="' . $calendar->url() . '?attendance=online" aria-current="page"'),
				'switch: active option marked'
			);
			$this->assertTrue(str_contains($html, 'href="' . $calendar->url() . '"'), 'switch: All drops the attendance filter');
			$hybrid = array_values(array_filter($online['events'], fn (Event $e) => $e->effectiveAttendance() === 'mixed'));
			$this->assertTrue($hybrid !== [], 'switch online: hybrid events are included');
			$offline = $calendar->koyomiList(['attendance' => 'offline']);
			$this->assertTrue(
				array_values(array_filter($offline['events'], fn (Event $e) => $e->effectiveAttendance() === 'mixed')) !== [],
				'switch in person: hybrid events are included'
			);

			$past = $calendar->koyomiList(['view' => 'past', 'attendance' => 'online']);
			$html = (string)$kirby->snippet('koyomi/switch', ['list' => $past], true);
			$this->assertTrue($html === '' || str_contains($html, 'view=past'), 'switch in the past view keeps view=past (or hides when nothing to switch)');

			$onlyOne = $list;
			$onlyOne['facets']['attendance'] = ['offline' => 3, 'online' => 0, 'mixed' => 0];
			$this->assertSame('', trim((string)$kirby->snippet('koyomi/switch', ['list' => $onlyOne], true)), 'switch: hidden when everything is in person');

			// ---- filters without the format select ---------------------------------
			$form = (string)$kirby->snippet('koyomi/filters', ['list' => $online, 'attendance' => false], true);
			$this->assertTrue(!str_contains($form, 'name="attendance">') && !str_contains($form, 'koyomi-filter-attendance'), 'filters attendance false: no format select');
			$this->assertTrue(str_contains($form, '<input type="hidden" name="attendance" value="online">'), 'filters: chosen format kept as hidden field');
			$form = (string)$kirby->snippet('koyomi/filters', ['list' => $list], true);
			$this->assertTrue(str_contains($form, 'koyomi-filter-attendance'), 'filters default: format select still there');

			// ---- calendar page: switch above the filters ---------------------------
			$page = (string)$calendar->render();
			$this->assertTrue(
				strpos($page, 'koyomi-switch') !== false && strpos($page, 'koyomi-switch') < strpos($page, 'koyomi-filters'),
				'calendar page: switch before the filter form'
			);
			$this->assertTrue(!str_contains($page, 'id="koyomi-filter-attendance"'), 'calendar page: no second format select');
			$this->assertTrue(str_contains($page, 'class="koyomi-timeline"'), 'calendar page: timeline');

			// Last: a new App replaces App::instance(), which the snippets read.
			$off = KoyomiFixture::app($root, ['koyomi.attendanceSwitch' => false]);
			$this->assertSame('', trim((string)$off->snippet('koyomi/switch', ['list' => $list], true)), 'switch: off by option');
			$this->assertTrue(str_contains((string)$off->page('events')->render(), 'id="koyomi-filter-attendance"'), 'switch off: the filter form shows the format select again');
			$inactive = KoyomiFixture::app($root, ['koyomi.active' => false]);
			$this->assertSame('', trim((string)$inactive->snippet('koyomi/switch', ['list' => $list], true)), 'switch: nothing while inactive');
		} finally {
			KoyomiFixture::remove($root);
		}
	}
}

<?php

use Kirby\Cms\App;
use Kirby\Filesystem\Dir;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Koyomi\Event;
use Sayamaapps\Koyomi\Time;

/**
 * A site that keeps its dates in a structure field of its own and hands the
 * rows to Event::fromArray(). The content file is written as Kirby 5 wrote it,
 * the dates unquoted: Kirby 5 reads them as text, Kirby 6 as a Unix time.
 * Koyomi's own event pages store dates in page fields, which stay text.
 */
#[CoversClass(Event::class)]
#[RunTestsInSeparateProcesses]
final class StructureDateTest extends TestCase
{
	public function testUnquotedDatesFromAContentFile(): void
	{
		$tmp = sys_get_temp_dir() . '/koyomi-structure-' . bin2hex(random_bytes(6));
		mkdir($tmp . '/content/home', 0700, true);
		file_put_contents($tmp . '/content/home/home.txt', "Title: Home\n\n----\n\nTermine:\n\n- startDate: 2026-11-21\n  endDate: 2026-11-22\n");

		try {
			$kirby = new App(['roots' => ['index' => $tmp . '/public', 'content' => $tmp . '/content', 'site' => $tmp . '/site']]);
			$row = $kirby->page('home')->content()->get('termine')->yaml()[0];
			$event = Event::fromArray(['id' => 'evt'] + $row);

			$this->assertSame(['2026-11-21', '2026-11-22'], [Time::date($event->start), Time::date($event->lastDay())]);
		} finally {
			Dir::remove($tmp);
		}
	}
}

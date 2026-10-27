<?php

use Kirby\Cms\Page;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Duplicate hook (Sayamaapps\Koyomi\Hooks::duplicateAfter, duplicate hook): duplicating an
 * event resets its status fields and keeps the dates; off switches and other
 * templates leave the copy alone; the hook always hands back a page.
 */
#[CoversNothing]
final class HooksTest extends TestCase
{
	public function testSuite(): void
	{
		$root = KoyomiFixture::create();

		try {

			// Active, duplicateReset on (defaults): the cancelled workshop becomes a scheduled draft.
			$kirby = KoyomiFixture::app($root);
			$kirby->impersonate('kirby');
			$original = $kirby->page('events/workshop-cancelled');
			$this->assertTrue($original !== null, 'fixture event exists');
			$this->assertSame('cancelled', $original->content()->get('eventStatus')->value(), 'original is cancelled');

			$copy = $original->duplicate('workshop-copy');
			$this->assertTrue($copy instanceof Page, 'duplicate returns a Page');
			$this->assertSame('scheduled', $copy->content()->get('eventStatus')->value(), 'copy: eventStatus reset to scheduled');
			$this->assertSame('', $copy->content()->get('statusNote')->value(), 'copy: statusNote cleared');
			$this->assertSame('', $copy->content()->get('previousStart')->value(), 'copy: previousStart cleared');
			$this->assertSame('available', $copy->content()->get('availability')->value(), 'copy: availability reset to available');
			$this->assertSame('2027-03-25', substr($copy->content()->get('startDate')->value(), 0, 10), 'copy: startDate kept');
			$this->assertSame('Breathing Workshop', $copy->content()->get('title')->value(), 'copy: title kept');
			$this->assertSame('90', $copy->content()->get('price')->value(), 'copy: price kept');
			$this->assertSame('draft', $copy->status(), 'copy stays a draft');
			$this->assertSame('koyomi-event', $copy->intendedTemplate()->name(), 'copy keeps the event template');
			$copy->delete(true);

			// The original is untouched by the copy.
			$this->assertSame('cancelled', $kirby->page('events/workshop-cancelled')->content()->get('eventStatus')->value(), 'original still cancelled after duplicate');

			// A rescheduled event: previousStart is removed from the copy as well.
			$rescheduled = $kirby->page('events/concert');
			$copy = $rescheduled->duplicate('concert-copy');
			$this->assertSame('scheduled', $copy->content()->get('eventStatus')->value(), 'rescheduled copy: status reset');
			$this->assertSame('', $copy->content()->get('previousStart')->value(), 'rescheduled copy: previousStart cleared');
			$copy->delete(true);

			// duplicateReset off: the copy keeps every status field.
			$kirby = KoyomiFixture::app($root, ['sayamaapps.koyomi.duplicateReset' => false]);
			$kirby->impersonate('kirby');
			$copy = $kirby->page('events/workshop-cancelled')->duplicate('workshop-copy-kept');
			$this->assertTrue($copy instanceof Page, 'duplicateReset off still returns a Page');
			$this->assertSame('cancelled', $copy->content()->get('eventStatus')->value(), 'duplicateReset false keeps the status');
			$this->assertSame('Wegen Krankheit abgesagt.', $copy->content()->get('statusNote')->value(), 'duplicateReset false keeps the note');
			$copy->delete(true);

			// Master switch off: the hook does nothing, whatever duplicateReset says.
			$kirby = KoyomiFixture::app($root, ['sayamaapps.koyomi.active' => false]);
			$kirby->impersonate('kirby');
			$copy = $kirby->page('events/workshop-cancelled')->duplicate('workshop-copy-inactive');
			$this->assertTrue($copy instanceof Page, 'inactive still returns a Page');
			$this->assertSame('cancelled', $copy->content()->get('eventStatus')->value(), 'koyomi.active false keeps the status');
			$this->assertSame('Wegen Krankheit abgesagt.', $copy->content()->get('statusNote')->value(), 'koyomi.active false keeps the note');
			$copy->delete(true);

			// Another template (a venue): untouched, still a Page.
			$kirby = KoyomiFixture::app($root);
			$kirby->impersonate('kirby');
			$venue = $kirby->page('venues/stadthalle');
			$this->assertTrue($venue !== null, 'fixture venue exists');
			$copy = $venue->duplicate('stadthalle-copy');
			$this->assertTrue($copy instanceof Page, 'duplicating a venue returns a Page');
			$this->assertSame('koyomi-venue', $copy->intendedTemplate()->name(), 'venue copy keeps its template');
			$this->assertSame('Stadthalle Musterstadt', $copy->content()->get('title')->value(), 'venue copy keeps its title');
			$this->assertSame('Musterstraße 1', $copy->content()->get('street')->value(), 'venue copy keeps its address');
			$this->assertTrue($copy->content()->get('eventStatus')->isEmpty(), 'venue copy gets no event status');
			$copy->delete(true);

			// A non-Koyomi page is untouched too (the hook only looks at koyomi-event).
			$copy = $kirby->page('events')->duplicate('events-copy', ['children' => false, 'files' => false]);
			$this->assertTrue($copy instanceof Page, 'duplicating the calendar returns a Page');
			$this->assertSame('koyomi-calendar', $copy->intendedTemplate()->name(), 'calendar copy keeps its template');
			$copy->delete(true);

		} finally {
			KoyomiFixture::remove($root);
		}
	}
}

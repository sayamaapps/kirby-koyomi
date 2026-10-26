<?php

use Kirby\Cms\Page;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Duplicate hook (Koyomi\Hooks::duplicateAfter, SPEC §8.13): duplicating an
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
		    holds($original !== null, 'fixture event exists');
		    expect('cancelled', $original->content()->get('eventStatus')->value(), 'original is cancelled');

		    $copy = $original->duplicate('workshop-copy');
		    holds($copy instanceof Page, 'duplicate returns a Page');
		    expect('scheduled', $copy->content()->get('eventStatus')->value(), 'copy: eventStatus reset to scheduled');
		    expect('', $copy->content()->get('statusNote')->value(), 'copy: statusNote cleared');
		    expect('', $copy->content()->get('previousStart')->value(), 'copy: previousStart cleared');
		    expect('available', $copy->content()->get('availability')->value(), 'copy: availability reset to available');
		    expect('2027-03-25', substr($copy->content()->get('startDate')->value(), 0, 10), 'copy: startDate kept');
		    expect('Breathing Workshop', $copy->content()->get('title')->value(), 'copy: title kept');
		    expect('90', $copy->content()->get('price')->value(), 'copy: price kept');
		    expect('draft', $copy->status(), 'copy stays a draft');
		    expect('koyomi-event', $copy->intendedTemplate()->name(), 'copy keeps the event template');
		    $copy->delete(true);

		    // The original is untouched by the copy.
		    expect('cancelled', $kirby->page('events/workshop-cancelled')->content()->get('eventStatus')->value(), 'original still cancelled after duplicate');

		    // A rescheduled event: previousStart is removed from the copy as well.
		    $rescheduled = $kirby->page('events/concert');
		    $copy = $rescheduled->duplicate('concert-copy');
		    expect('scheduled', $copy->content()->get('eventStatus')->value(), 'rescheduled copy: status reset');
		    expect('', $copy->content()->get('previousStart')->value(), 'rescheduled copy: previousStart cleared');
		    $copy->delete(true);

		    // duplicateReset off: the copy keeps every status field.
		    $kirby = KoyomiFixture::app($root, ['koyomi.duplicateReset' => false]);
		    $kirby->impersonate('kirby');
		    $copy = $kirby->page('events/workshop-cancelled')->duplicate('workshop-copy-kept');
		    holds($copy instanceof Page, 'duplicateReset off still returns a Page');
		    expect('cancelled', $copy->content()->get('eventStatus')->value(), 'duplicateReset false keeps the status');
		    expect('Wegen Krankheit abgesagt.', $copy->content()->get('statusNote')->value(), 'duplicateReset false keeps the note');
		    $copy->delete(true);

		    // Master switch off: the hook does nothing, whatever duplicateReset says.
		    $kirby = KoyomiFixture::app($root, ['koyomi.active' => false]);
		    $kirby->impersonate('kirby');
		    $copy = $kirby->page('events/workshop-cancelled')->duplicate('workshop-copy-inactive');
		    holds($copy instanceof Page, 'inactive still returns a Page');
		    expect('cancelled', $copy->content()->get('eventStatus')->value(), 'koyomi.active false keeps the status');
		    expect('Wegen Krankheit abgesagt.', $copy->content()->get('statusNote')->value(), 'koyomi.active false keeps the note');
		    $copy->delete(true);

		    // Another template (a venue): untouched, still a Page.
		    $kirby = KoyomiFixture::app($root);
		    $kirby->impersonate('kirby');
		    $venue = $kirby->page('venues/stadthalle');
		    holds($venue !== null, 'fixture venue exists');
		    $copy = $venue->duplicate('stadthalle-copy');
		    holds($copy instanceof Page, 'duplicating a venue returns a Page');
		    expect('koyomi-venue', $copy->intendedTemplate()->name(), 'venue copy keeps its template');
		    expect('Stadthalle Musterstadt', $copy->content()->get('title')->value(), 'venue copy keeps its title');
		    expect('Musterstraße 1', $copy->content()->get('street')->value(), 'venue copy keeps its address');
		    holds($copy->content()->get('eventStatus')->isEmpty(), 'venue copy gets no event status');
		    $copy->delete(true);

		    // A non-Koyomi page is untouched too (the hook only looks at koyomi-event).
		    $copy = $kirby->page('events')->duplicate('events-copy', ['children' => false, 'files' => false]);
		    holds($copy instanceof Page, 'duplicating the calendar returns a Page');
		    expect('koyomi-calendar', $copy->intendedTemplate()->name(), 'calendar copy keeps its template');
		    $copy->delete(true);

		} finally {
		KoyomiFixture::remove($root);
		}	}
}

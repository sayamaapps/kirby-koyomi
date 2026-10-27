<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Koyomi\GoogleLink;
use Sayamaapps\Koyomi\Ics;
use Sayamaapps\Koyomi\JsonLd;
use Sayamaapps\Koyomi\Rss;

/**
 * output of Koyomi on the fixture site — HTML pages, every snippet, every
 * content representation (.ics, .rss, .json), the data arrays — in English
 * and German, and asserts that no online meeting link (`onlineUrl`) and no
 * hidden organizer email appears anywhere. Also checks the outputs are not
 * trivially empty, so "prints nothing" cannot pass.
 */
#[CoversClass(GoogleLink::class)]
#[CoversClass(Ics::class)]
#[CoversClass(JsonLd::class)]
#[CoversClass(Rss::class)]
final class PrivacyTest extends TestCase
{
	public function testSuite(): void
	{
		$root = KoyomiFixture::create();

		try {

			$forbidden = [...KoyomiFixture::SECRETS, ...KoyomiFixture::SECRET_FRAGMENTS, KoyomiFixture::HIDDEN_EMAIL, 'private-anna'];
			$check = function (string $label, string $output) use ($forbidden): void {
				foreach ($forbidden as $needle) {
					// Also catch JSON-escaped and HTML-escaped variants.
					foreach ([$needle, str_replace('/', '\\/', $needle), htmlspecialchars($needle, ENT_QUOTES)] as $variant) {
						$this->assertTrue(!str_contains($output, $variant), "$label must not contain $needle");
					}
				}
			};

			foreach (['en' => [], 'de' => ['koyomi.language' => 'de']] as $language => $extra) {
				$kirby = KoyomiFixture::app($root, ['koyomi.json' => true, 'koyomi.google' => true] + $extra);
				$all = '';

				// Content representations.
				$paths = ['events.ics', 'events.rss', 'events.json'];
				foreach ([
					'retreat',
					'zoom-session',
					'concert',
					'workshop-cancelled',
					'past-workshop',
					'ongoing-retreat',
					'series-part-1',
					'series-part-2',
					'unlisted-event'
				] as $slug) {
					$paths[] = 'events/' . $slug . '.ics';
					$paths[] = 'events/' . $slug;
				}
				// HTML pages, incl. filtered and past views.
				array_push($paths, 'events', 'venues/beispielhof', 'venues/stadthalle', 'organizers/anna', 'organizers/sayama');
				foreach ($paths as $path) {
					$response = $kirby->render($path);
					$this->assertTrue($response !== null && $response->code() === 200, "$language: $path renders (got " . ($response?->code() ?? 'null') . ')');
					$body = (string)$response->body();
					$this->assertTrue(trim($body) !== '', "$language: $path is not empty");
					$check("$language: $path", $body);
					$all .= $body;
				}
				foreach (['view=past', 'attendance=online', 'category=Retreat', 'language=cs', 'featured=1', 'page=2'] as $query) {
					parse_str($query, $params);
					$html = (string)$kirby->snippet('koyomi/list', ['list' => $kirby->page('events')->koyomiList($params)], true);
					$check("$language: list ?$query", $html);
				}

				// Every snippet with every listed and unlisted event.
				$calendar = $kirby->page('events');
				foreach ($calendar->childrenAndDrafts() as $page) {
					foreach (['koyomi/card', 'koyomi/event', 'koyomi/jsonld', 'koyomi/past'] as $snippet) {
						$html = (string)$kirby->snippet($snippet, ['event' => $page, 'page' => $page], true);
						$check("$language: $snippet " . $page->slug(), $html);
						$all .= $html;
					}
					// The plain arrays for own markup.
					$check("$language: koyomiData " . $page->slug(), json_encode($page->koyomiData()));
					$event = $page->koyomi();
					if ($event !== null) {
						$check("$language: toArray " . $page->slug(), json_encode($event->toArray()));
						$check("$language: JsonLd " . $page->slug(), \Sayamaapps\Koyomi\JsonLd::encode(\Sayamaapps\Koyomi\JsonLd::event($event)));
						$check("$language: Ics " . $page->slug(), \Sayamaapps\Koyomi\Ics::event($event, ['host' => 'example.test']));
						$check("$language: Google " . $page->slug(), \Sayamaapps\Koyomi\GoogleLink::url($event, $language));
						$check(
							"$language: Rss " . $page->slug(),
							\Sayamaapps\Koyomi\Rss::feed([$event], ['title' => 'T', 'link' => KoyomiFixture::URL, 'self' => KoyomiFixture::URL . '/events.rss'])
						);
					}
				}
				foreach (['koyomi/list', 'koyomi/filters', 'koyomi/pagination', 'koyomi/subscribe'] as $snippet) {
					$html = (string)$kirby->snippet($snippet, ['page' => $calendar, 'calendar' => $calendar], true);
					$check("$language: $snippet", $html);
					$all .= $html;
				}
				foreach (['beispielhof', 'stadthalle'] as $slug) {
					$html = (string)$kirby->snippet('koyomi/venue', ['venue' => $kirby->page('venues/' . $slug)], true);
					$check("$language: koyomi/venue $slug", $html);
				}
				$check("$language: koyomiList", json_encode(array_map(fn ($e) => $e->toArray(), $calendar->koyomiList()['events'])));

				// Not trivially empty: the real content is there.
				$this->assertTrue(str_contains($all, 'BEGIN:VEVENT'), "$language: ICS has events");
				$this->assertTrue(str_contains($all, '<item>'), "$language: RSS has items");
				$this->assertTrue(str_contains($all, '"@type":"Event"'), "$language: JSON-LD present");
				$this->assertTrue(str_contains($all, 'Silent Retreat') && str_contains($all, 'Seminarhaus Beispielhof'), "$language: titles and venues shown");
				$this->assertTrue(str_contains($all, 'Zoom'), "$language: the platform NAME may be shown");
				$this->assertTrue(str_contains($all, KoyomiFixture::PUBLIC_EMAIL), "$language: a public organizer email is shown somewhere");
			}

		} finally {
			KoyomiFixture::remove($root);
		}
	}
}

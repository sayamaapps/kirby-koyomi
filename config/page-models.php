<?php

use Sayamaapps\Koyomi\Models\CalendarPage;
use Sayamaapps\Koyomi\Models\EventPage;
use Sayamaapps\Koyomi\Models\OrganizerPage;
use Sayamaapps\Koyomi\Models\VenuePage;

return [
	'koyomi-calendar' => CalendarPage::class,
	'koyomi-event' => EventPage::class,
	'koyomi-venue' => VenuePage::class,
	'koyomi-organizer' => OrganizerPage::class,
];

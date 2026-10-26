<?php

/**
 * Class map for Kirby's F::loadClasses() (AUT-2). Keys are lowercase class names,
 * values are paths relative to the plugin root. tests/AutoloadTest.php checks the
 * map against src/.
 */

return [
	'sayamaapps\\koyomi\\attendance' => 'src/Attendance.php',
	'sayamaapps\\koyomi\\availability' => 'src/Availability.php',
	'sayamaapps\\koyomi\\blueprints' => 'src/Blueprints.php',
	'sayamaapps\\koyomi\\event' => 'src/Event.php',
	'sayamaapps\\koyomi\\expiry' => 'src/Expiry.php',
	'sayamaapps\\koyomi\\filters' => 'src/Filters.php',
	'sayamaapps\\koyomi\\format' => 'src/Format.php',
	'sayamaapps\\koyomi\\googlelink' => 'src/GoogleLink.php',
	'sayamaapps\\koyomi\\hooks' => 'src/Hooks.php',
	'sayamaapps\\koyomi\\i18n' => 'src/I18n.php',
	'sayamaapps\\koyomi\\ics' => 'src/Ics.php',
	'sayamaapps\\koyomi\\jsonld' => 'src/JsonLd.php',
	'sayamaapps\\koyomi\\mapper' => 'src/Mapper.php',
	'sayamaapps\\koyomi\\models\\calendarpage' => 'src/Models/CalendarPage.php',
	'sayamaapps\\koyomi\\models\\eventpage' => 'src/Models/EventPage.php',
	'sayamaapps\\koyomi\\models\\organizerpage' => 'src/Models/OrganizerPage.php',
	'sayamaapps\\koyomi\\models\\venuepage' => 'src/Models/VenuePage.php',
	'sayamaapps\\koyomi\\normalize' => 'src/Normalize.php',
	'sayamaapps\\koyomi\\options' => 'src/Options.php',
	'sayamaapps\\koyomi\\organizer' => 'src/Organizer.php',
	'sayamaapps\\koyomi\\output' => 'src/Output.php',
	'sayamaapps\\koyomi\\query' => 'src/Query.php',
	'sayamaapps\\koyomi\\repository' => 'src/Repository.php',
	'sayamaapps\\koyomi\\rss' => 'src/Rss.php',
	'sayamaapps\\koyomi\\status' => 'src/Status.php',
	'sayamaapps\\koyomi\\time' => 'src/Time.php',
	'sayamaapps\\koyomi\\venue' => 'src/Venue.php',
	'sayamaapps\\koyomi\\vtimezone' => 'src/VTimezone.php',
];

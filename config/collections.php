<?php

use Kirby\Cms\App;
use Sayamaapps\Koyomi\Repository;

// Queries for the Panel pages fields: kirby.collection("koyomi/venues") …
return [
	'koyomi/events' => function () {
		return Repository::eventPages(Repository::calendar(App::instance()));
	},
	'koyomi/venues' => function () {
		return Repository::venuePages(App::instance());
	},
	'koyomi/organizers' => function () {
		return Repository::organizerPages(App::instance());
	},
];

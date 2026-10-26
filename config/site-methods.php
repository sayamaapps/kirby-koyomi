<?php

use Kirby\Cms\Page;
use Sayamaapps\Koyomi\Repository;

return [
	// The calendar page (`sayamaapps.koyomi.calendar`, else the first koyomi-calendar page), or null.
	'koyomiCalendar' => function (): Page|null {
		return Repository::calendar($this->kirby());
	},
];

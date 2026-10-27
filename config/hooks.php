<?php

use Kirby\Cms\Page;
use Sayamaapps\Koyomi\Hooks;

return [
	// A copy of an event keeps its dates (it is a draft anyway) but must not inherit
	// "cancelled", "sold out" or a status note.
	'page.duplicate:after' => function (Page $duplicatePage, Page $originalPage) {
		return Hooks::duplicateAfter($duplicatePage, $originalPage);
	},
];

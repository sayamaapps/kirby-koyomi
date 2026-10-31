<?php

// Options Koyomi reads, relative to the plugin: `sayamaapps.koyomi.<key>`.
// Each value is null on purpose. Options::get() gives the default at every read,
// and a default here would hide the 1.x spelling `koyomi.<key>` (fallback).
return [
	'active'           => null,
	'attendanceSwitch' => null,
	'calendar'         => null,
	'country'          => null,
	'currencies'       => null,
	'currency'         => null,
	'defaultDuration'  => null,
	'duplicateReset'   => null,
	'feedPastDays'     => null,
	'formats'          => null,
	'google'           => null,
	'groupBy'          => null,
	'hideCancelled'    => null,
	'language'         => null,
	'languages'        => null,
	'mapUrl'           => null,
	'now'              => null,
	'organizers'       => null,
	'perPage'          => null,
	'timezone'         => null,
	'timezones'        => null,
	'venues'           => null,
	'ics'              => null,
	'feed'             => null,
	'rss'              => null,
	'json'             => null,
];

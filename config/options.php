<?php

// Options the plugin reads, relative to the plugin: `sayamaapps.koyomi.<key>` (OPT-1).
// Each value is null on purpose. Options::get() gives the default at every read,
// and a default here would hide the 1.x spelling `koyomi.<key>` (fallback).
return [
	'active'          => null,
	'attendanceSwitch' => null,
	'calendar'        => null,
	'country'         => null,
	'currencies'      => null,
	'currency'        => null,
	'defaultDuration' => null,
	'duplicateReset'  => null,
	'feedPastDays'    => null,
	'feed'            => null,
	'formats'         => null,
	'google'          => null,
	'groupBy'         => null,
	'hideCancelled'   => null,
	'ics'             => null,
	'json'            => null,
	'language'        => null,
	'languages'       => null,
	'mapUrl'          => null,
	'now'             => null,
	'organizers'      => null,
	'perPage'         => null,
	'rss'             => null,
	'timezone'        => null,
	'timezones'       => null,
	'venues'          => null,
];
